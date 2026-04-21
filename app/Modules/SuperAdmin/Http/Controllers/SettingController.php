<?php

namespace Modules\SuperAdmin\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

/**
 * Unified Settings page — one form, tabs per `group`. Values live in
 * system_settings and are applied at request boot by ApplySystemSettings
 * middleware.
 *
 * Secrets (`moyasar_secret_key`, `mail_password`) are encrypted at write
 * time and decrypted only by ApplySystemSettings before use.
 */
class SettingController extends Controller
{
    /** @var array<string, array<int, string>> keys grouped by tab */
    private const GROUPS = [
        'general' => [
            'app_name', 'app_logo', 'app_url', 'support_email', 'support_phone',
            'default_timezone', 'default_language',
        ],
        'trial' => [
            'trial_enabled', 'trial_days', 'trial_requires_payment',
            'trial_suspend_after_expiry', 'trial_warning_days',
        ],
        'moyasar' => [
            'moyasar_publishable_key', 'moyasar_secret_key', 'moyasar_enabled_methods',
            'moyasar_test_mode', 'moyasar_webhook_secret',
        ],
        'mail' => [
            'mail_driver', 'mail_host', 'mail_port', 'mail_encryption',
            'mail_username', 'mail_password', 'mail_from_address', 'mail_from_name',
        ],
        'landing' => [
            'hero_title', 'hero_subtitle', 'hero_cta_text', 'hero_cta_url', 'hero_image',
            'footer_copyright', 'privacy_url', 'terms_url',
        ],
        'notifications' => [
            'notify_new_subscription', 'notify_payment_failed',
            'notify_trial_expiring', 'notify_subscription_expiring',
            'admin_notification_email',
        ],
    ];

    private const ENCRYPTED_KEYS = ['moyasar_secret_key', 'mail_password'];

    public function index(): View
    {
        $all = SystemSetting::query()->pluck('value', 'key')->toArray();

        // Decrypt secrets so the form can preload them as masked.
        foreach (self::ENCRYPTED_KEYS as $key) {
            if (! empty($all[$key])) {
                try {
                    $all[$key] = decrypt($all[$key]);
                } catch (\Throwable) {
                    $all[$key] = '';
                }
            }
        }

        return view('super-admin.settings.index', [
            'groups' => self::GROUPS,
            'values' => $all,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $group = (string) $request->input('group', 'general');
        if (! array_key_exists($group, self::GROUPS)) {
            return back()->with('error', 'مجموعة إعدادات غير معروفة.');
        }

        $data = $request->only(self::GROUPS[$group]);

        // Encrypt secrets before persisting. Skip if unchanged/empty.
        foreach (self::ENCRYPTED_KEYS as $key) {
            if (! isset($data[$key])) continue;
            $plain = (string) $data[$key];
            $data[$key] = $plain === '' ? null : encrypt($plain);
        }

        // Coerce checkboxes — unchecked inputs don't submit, so force false.
        foreach (self::GROUPS[$group] as $key) {
            if (str_starts_with($key, 'trial_') || str_starts_with($key, 'notify_') || $key === 'moyasar_test_mode') {
                $data[$key] = $request->boolean($key) ? '1' : '0';
            }
        }

        SystemSetting::setMany($data, $group);

        return back()->with('success', 'تم حفظ الإعدادات.');
    }

    /**
     * Send a test email using the current mail settings. Call via
     * POST /super-admin/settings/test-mail with `to` address.
     */
    public function testMail(Request $request): JsonResponse
    {
        $to = (string) $request->validate([
            'to' => ['required', 'email'],
        ])['to'];

        try {
            // ApplySystemSettings middleware already put current creds into Config.
            Mail::raw('هذه رسالة اختبار من لوحة إدارة Mizaan SaaS.', function ($m) use ($to) {
                $m->to($to)->subject('اختبار البريد الإلكتروني');
            });

            return response()->json(['ok' => true, 'message' => "تم إرسال رسالة تجريبية إلى {$to}."]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'فشل الإرسال: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Ping Moyasar API with the current secret key — confirms the key is
     * active and the network path is open. Does not charge anything.
     */
    public function testMoyasar(): JsonResponse
    {
        $secret = Config::get('services.moyasar.secret_key');
        if (empty($secret)) {
            return response()->json(['ok' => false, 'message' => 'Moyasar secret key غير معدّ.'], 400);
        }

        try {
            $response = Http::withBasicAuth($secret, '')
                ->timeout(10)
                ->get('https://api.moyasar.com/v1/payments', ['page' => 1]);

            return response()->json([
                'ok'      => $response->successful(),
                'message' => $response->successful()
                    ? 'الاتصال بـ Moyasar ناجح.'
                    : 'فشل الاتصال: HTTP ' . $response->status(),
            ], $response->successful() ? 200 : 502);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => 'فشل الاتصال: ' . $e->getMessage()], 502);
        }
    }
}
