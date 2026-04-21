<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Convert a legacy central-DB `organizations` row into a first-class SaaS
 * tenant. Intended to be run ONCE per pre-SaaS organization during the
 * cutover window — existing data in the central tables gets copied into
 * the fresh tenant DB, then the legacy central rows can be deleted in a
 * follow-up release once verification passes.
 *
 * Shape:
 *   1. Create Tenant row (stancl's TenantCreated pipeline provisions
 *      the DB + runs tenant migrations automatically).
 *   2. Attach a primary subdomain derived from name_ar slug.
 *   3. Open tenancy context and copy rows from every per-tenant table
 *      that has an `org_id` column matching our source org.
 *   4. Create an Active subscription on the specified plan.
 *
 * Safety:
 *   - `--dry-run` prints the migration plan without writing anything.
 *   - `--plan=SLUG` (required) picks the subscription plan.
 *   - `--subdomain=NAME` overrides the auto-generated subdomain.
 *   - Wrapped in a DB transaction; failure rolls back Tenant creation
 *     (but NOT stancl's DB-create job — if that fails mid-way the
 *     operator must drop the orphan tenant DB manually).
 */
class ConvertOrgToTenantCommand extends Command
{
    protected $signature = 'saas:convert-org-to-tenant
                            {org_id : ID of the row in organizations table}
                            {--plan= : Plan slug to subscribe the tenant to (required)}
                            {--cycle=monthly : billing cycle (monthly|yearly)}
                            {--subdomain= : Override the auto-generated subdomain}
                            {--dry-run : Print what would happen without writing}';

    protected $description = 'Convert a legacy `organizations` row into a real SaaS Tenant with its own DB.';

    /** @var array<int, string> per-tenant tables with an `org_id` column */
    private const PER_TENANT_TABLES = [
        'users', 'legal_documents', 'tasks', 'task_activities', 'task_comments',
        'task_assignments', 'folders', 'folder_documents', 'folder_members',
        'watchlists', 'annotations', 'discussions', 'discussion_replies',
        'document_chunks', 'article_updates', 'document_versions',
        'document_relations', 'ai_conversations', 'ai_messages',
        'tenders', 'tender_sections', 'tender_clauses', 'tender_reviews',
        'tender_similarity_results', 'tender_similarity_ignores',
        'app_notifications',
    ];

    public function handle(): int
    {
        $orgId = (int) $this->argument('org_id');
        $planSlug = (string) $this->option('plan');
        $cycle = (string) $this->option('cycle');
        $dryRun = (bool) $this->option('dry-run');

        if ($planSlug === '') {
            $this->error('--plan=SLUG is required. Use `php artisan tinker` to list: Plan::pluck(\'slug\', \'id\')');
            return self::FAILURE;
        }

        $org = DB::table('organizations')->find($orgId);
        if (! $org) {
            $this->error("Organization #{$orgId} not found in central DB.");
            return self::FAILURE;
        }

        $plan = Plan::where('slug', $planSlug)->first();
        if (! $plan) {
            $this->error("Plan '{$planSlug}' not found.");
            return self::FAILURE;
        }

        $this->info("Converting organization #{$orgId}: {$org->name_ar}");
        $this->info("  Target plan: {$plan->name} ({$cycle})");

        // Preflight — how many rows will move?
        $this->line('');
        $this->line('Row counts to migrate:');
        $totals = [];
        foreach (self::PER_TENANT_TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'org_id')) {
                continue;
            }
            $n = DB::table($table)->where('org_id', $orgId)->count();
            if ($n > 0) {
                $totals[$table] = $n;
                $this->line(sprintf('  %-28s %d', $table, $n));
            }
        }
        $this->line(sprintf('  %-28s %d', 'TOTAL', array_sum($totals)));

        if ($dryRun) {
            $this->warn('Dry run — no writes performed.');
            return self::SUCCESS;
        }

        if (! $this->confirm('Proceed with conversion? This cannot be undone without manual tenant DB drop.')) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        // Derive subdomain
        $subdomain = (string) ($this->option('subdomain')
            ?: Str::slug($org->name_en ?: $org->name_ar, '-')
            ?: 'org-' . $orgId);

        $baseHost = parse_url(config('app.url') ?? 'mizaan.local', PHP_URL_HOST) ?: 'mizaan.local';
        $domain = "{$subdomain}.{$baseHost}";

        try {
            $tenant = DB::transaction(function () use ($org, $plan, $cycle, $domain) {
                $tenant = Tenant::create([
                    'id'           => (string) Str::uuid(),
                    'company_name' => $org->name_ar,
                    'owner_name'   => 'مدير ' . $org->name_ar,
                    'owner_email'  => 'admin@' . parse_url(config('app.url'), PHP_URL_HOST),
                    'status'       => Tenant::STATUS_ACTIVE,
                    'timezone'     => 'Asia/Riyadh',
                    'language'     => 'ar',
                ]);

                $tenant->domains()->create(['domain' => $domain]);

                Subscription::create([
                    'tenant_id'     => $tenant->id,
                    'plan_id'       => $plan->id,
                    'billing_cycle' => $cycle,
                    'status'        => Subscription::STATUS_ACTIVE,
                    'starts_at'     => now(),
                    'ends_at'       => now()->addMonths($cycle === 'yearly' ? 12 : 1),
                    'amount'        => $plan->priceFor($cycle),
                    'currency'      => $plan->currency,
                    'metadata'      => ['converted_from_org_id' => $org->id],
                ]);

                return $tenant;
            });
        } catch (Throwable $e) {
            $this->error("Tenant creation failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info("✓ Tenant created: {$tenant->id} at https://{$domain}");

        // Stancl's TenantCreated pipeline created the DB + ran tenant
        // migrations by this point. Now copy data.
        $this->info('Copying per-tenant tables into the new tenant DB...');

        // Two-phase copy: read EVERY table from central first (we're
        // still in central context here), then switch to tenant context
        // to write. Avoids juggling central/tenant context inside the
        // loop — `DB::connection('central')` isn't a separately-named
        // connection under stancl's multi-DB setup; the active
        // connection IS the central one until $tenant->run() swaps it.
        $snapshots = [];
        foreach ($totals as $table => $expected) {
            try {
                $snapshots[$table] = DB::table($table)
                    ->where('org_id', $orgId)
                    ->get()
                    ->map(fn ($r) => (array) $r)
                    ->toArray();
            } catch (Throwable $e) {
                $this->warn(sprintf('  ✗ %s (central read): %s', $table, $e->getMessage()));
            }
        }

        $copied = 0;
        $tenant->run(function () use (&$copied, $snapshots) {
            foreach ($snapshots as $table => $rows) {
                if (empty($rows)) continue;

                try {
                    // Insert in chunks of 200 to avoid oversized statements.
                    foreach (array_chunk($rows, 200) as $batch) {
                        DB::table($table)->insert($batch);
                    }
                    $this->line(sprintf('  ✓ %-28s copied %d rows', $table, count($rows)));
                    $copied += count($rows);
                } catch (Throwable $e) {
                    $this->warn(sprintf('  ✗ %s (tenant write): %s', $table, $e->getMessage()));
                }
            }
        });

        $this->info("Done — {$copied} rows copied into tenant DB.");
        $this->warn('NOTE: central-DB rows for org_id=' . $orgId . ' are NOT deleted yet. Verify the tenant first, then remove them in a follow-up release.');

        return self::SUCCESS;
    }
}
