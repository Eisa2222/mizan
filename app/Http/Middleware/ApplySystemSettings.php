<?php

namespace App\Http\Middleware;

use App\Models\SystemSetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pull SaaS-wide settings from `system_settings` and apply them to the
 * Laravel `Config` repository at the start of every request — so mail
 * credentials, Moyasar keys, and app_name reflect whatever SuperAdmin
 * last saved without needing a deploy.
 *
 * Runs on CENTRAL routes only (registered in bootstrap/app.php via
 * group('web')). Tenant routes have their own settings surface inside
 * each tenant's DB; the settings here are SaaS-operator level.
 *
 * Secrets (moyasar_secret_key, mail_password) are stored encrypted —
 * we decrypt before writing to Config. Plain settings are passed as-is.
 */
class ApplySystemSettings
{
    /** @var array<string, string> key in system_settings → dotted Config path */
    private const MAP = [
        'app_name'                => 'app.name',
        'support_email'           => 'mail.from.address',
        'mail_from_name'          => 'mail.from.name',
        'mail_driver'             => 'mail.default',
        'mail_host'               => 'mail.mailers.smtp.host',
        'mail_port'               => 'mail.mailers.smtp.port',
        'mail_encryption'         => 'mail.mailers.smtp.encryption',
        'mail_username'           => 'mail.mailers.smtp.username',
        'moyasar_publishable_key' => 'services.moyasar.publishable_key',
        'moyasar_test_mode'       => 'services.moyasar.test_mode',
    ];

    /** @var array<int, string> keys stored encrypted */
    private const ENCRYPTED_KEYS = [
        'moyasar_secret_key',
        'mail_password',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::MAP as $settingKey => $configKey) {
            $value = SystemSetting::get($settingKey);
            if ($value !== null && $value !== '') {
                Config::set($configKey, $value);
            }
        }

        foreach (self::ENCRYPTED_KEYS as $key) {
            $raw = SystemSetting::get($key);
            if ($raw === null || $raw === '') continue;

            try {
                $decrypted = decrypt($raw);
            } catch (\Throwable) {
                continue; // silently skip — a malformed secret shouldn't 500 the app
            }

            match ($key) {
                'moyasar_secret_key' => Config::set('services.moyasar.secret_key', $decrypted),
                'mail_password'      => Config::set('mail.mailers.smtp.password', $decrypted),
            };
        }

        return $next($request);
    }
}
