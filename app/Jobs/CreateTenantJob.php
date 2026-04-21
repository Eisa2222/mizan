<?php

namespace App\Jobs;

use App\Mail\TenantWelcomeMail;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;

/**
 * Async tenant provisioning. Fires after CheckoutController::callback()
 * has persisted the Tenant shell. This job handles the slow bits:
 *
 *   1. Pick a subdomain from the tenant's company_name and attach it
 *      (central `domains` row).
 *   2. stancl's JobPipeline (registered in TenancyServiceProvider) runs
 *      CreateDatabase + MigrateDatabase automatically when the Domain
 *      is saved + TenantCreated fires. Nothing else to call here.
 *   3. Inside the new tenant DB, create the initial admin User.
 *   4. Generate a 48-hour signed URL for password setup (no password
 *      sent in clear via email) and send TenantWelcomeMail.
 *   5. Optionally notify SuperAdmin (if notify_new_subscription=true).
 *
 * Retries: 2 attempts with 30s backoff. The DB-creation step is not
 * idempotent — a failure after DB exists triggers delete on the next
 * retry (stancl's job pipeline handles this via DatabaseManager::deleteDatabase
 * on TenantDeleted, but here we mostly just fail safely and leave
 * cleanup to the admin).
 */
class CreateTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 30;
    public int $timeout = 300;

    public function __construct(
        public Tenant $tenant,
        public Subscription $subscription,
    ) {}

    public function handle(): void
    {
        $this->attachPrimaryDomain();

        // At this point stancl's TenantCreated listeners have already
        // fired CreateDatabase + MigrateDatabase. We run any post-seed
        // work inside the tenant context.
        $this->tenant->run(function () {
            $this->createAdminUser();
        });

        $this->sendWelcomeMail();
        $this->notifySuperAdmins();
    }

    /**
     * Derive a subdomain from the company name — slugified + collision
     * suffix. The full hostname is "{slug}.{app_base}" where app_base
     * comes from APP_URL, stripped of scheme.
     */
    private function attachPrimaryDomain(): void
    {
        if ($this->tenant->domains()->exists()) {
            return; // idempotent on retry
        }

        $baseHost = parse_url(config('app.url') ?? 'mizaan.local', PHP_URL_HOST) ?: 'mizaan.local';

        $slug = \Illuminate\Support\Str::slug($this->tenant->company_name, '-');
        if ($slug === '') {
            $slug = 't-' . substr($this->tenant->id, 0, 8);
        }

        $candidate = "{$slug}.{$baseHost}";
        $i = 1;
        while (\Stancl\Tenancy\Database\Models\Domain::where('domain', $candidate)->exists()) {
            $candidate = "{$slug}-{$i}.{$baseHost}";
            $i++;
        }

        $this->tenant->domains()->create(['domain' => $candidate]);
    }

    /**
     * Inside tenant DB: create the Admin user row. No password is set
     * yet — we'll email a signed setup link.
     */
    private function createAdminUser(): int
    {
        $user = \App\Models\User::create([
            'name'     => $this->tenant->owner_name,
            'email'    => $this->tenant->owner_email,
            'password' => bcrypt(\Illuminate\Support\Str::random(32)), // placeholder
            'role'     => 'SuperAdmin', // enum value — tenant-internal, not SaaS SuperAdmin
            'org_id'   => null,
            'email_verified_at' => now(),
        ]);

        return $user->id;
    }

    private function sendWelcomeMail(): void
    {
        $domain = $this->tenant->domains->first()?->domain;
        if (! $domain) {
            Log::warning('CreateTenantJob: no domain to send welcome mail', ['tenant' => $this->tenant->id]);
            return;
        }

        $token = Password::broker('tenants')->createToken(
            new class($this->tenant->owner_email) {
                public function __construct(public string $email) {}
                public function getEmailForPasswordReset(): string { return $this->email; }
            }
        );

        $scheme = config('app.env') === 'local' ? 'http' : 'https';
        $base   = "{$scheme}://{$domain}";
        $setupUrl = URL::temporarySignedRoute(
            'tenant.password.setup',
            now()->addHours(48),
            ['token' => $token, 'email' => $this->tenant->owner_email],
            false,
        );
        // The signed-route helper builds a central-relative URL; prepend
        // the tenant subdomain host.
        $setupUrl = $base . $setupUrl;

        try {
            Mail::to($this->tenant->owner_email)->send(
                new TenantWelcomeMail($this->tenant, $this->subscription, $setupUrl)
            );
        } catch (\Throwable $e) {
            // Mail is not transactional with tenant creation — a failure
            // here is logged, not rolled back.
            Log::warning('TenantWelcomeMail send failed', [
                'tenant' => $this->tenant->id,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    private function notifySuperAdmins(): void
    {
        if (! SystemSetting::get('notify_new_subscription')) {
            return;
        }

        $to = (string) SystemSetting::get('admin_notification_email');
        if ($to === '') return;

        try {
            Mail::raw(
                "اشتراك جديد:\nالشركة: {$this->tenant->company_name}\nالمالك: {$this->tenant->owner_name} <{$this->tenant->owner_email}>",
                fn ($m) => $m->to($to)->subject('اشتراك جديد - ' . $this->tenant->company_name)
            );
        } catch (\Throwable $e) {
            Log::warning('SuperAdmin new-sub notification failed', ['error' => $e->getMessage()]);
        }
    }
}
