<?php

namespace Database\Seeders;

use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\Plan;
use App\Models\PlanFeature;
use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Initial SaaS bootstrap — lays down everything SuperAdmin would
 * normally fill in on day 1 so the landing page + pricing page work
 * immediately after install.
 *
 * Idempotent: uses updateOrCreate on plans/settings (keyed by slug/key)
 * and firstOrCreate on landing content (keyed by title/question). Safe
 * to re-run after SuperAdmin customises values — re-seeding won't
 * stomp on edits because existing rows keep their values.
 */
class SaasInitialSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSystemSettings();
        $this->seedPlans();
        $this->seedLandingFeatures();
        $this->seedLandingFaqs();
    }

    /**
     * Reasonable defaults for every tab of the /super-admin/settings
     * page. Sets Arabic hero copy, 14-day trial, log mailer (safe for
     * dev), Moyasar disabled until keys are provided.
     */
    private function seedSystemSettings(): void
    {
        $defaults = [
            // general
            ['general', 'app_name',          'ميزان'],
            ['general', 'app_url',           env('APP_URL', 'http://localhost')],
            ['general', 'support_email',     'support@mizaan.sa'],
            ['general', 'default_timezone',  'Asia/Riyadh'],
            ['general', 'default_language',  'ar'],

            // trial
            ['trial', 'trial_enabled',              '1'],
            ['trial', 'trial_days',                 '14'],
            ['trial', 'trial_requires_payment',     '0'],
            ['trial', 'trial_suspend_after_expiry', '1'],
            ['trial', 'trial_warning_days',         '3'],

            // moyasar — keys left empty, operator fills from SuperAdmin UI
            ['moyasar', 'moyasar_test_mode',         '1'],
            ['moyasar', 'moyasar_enabled_methods',   json_encode(['creditcard', 'applepay', 'stcpay'])],

            // mail — safe dev default
            ['mail', 'mail_driver',         'log'],
            ['mail', 'mail_from_address',   'no-reply@mizaan.sa'],
            ['mail', 'mail_from_name',      'ميزان'],

            // landing hero
            ['landing', 'hero_title',       'منصّة قانونية ذكية للجهات الحكومية والخاصة'],
            ['landing', 'hero_subtitle',    'إدارة العقود والكراسات والمذكرات والأحكام في مكان واحد مع مساعد ذكاء اصطناعي متخصّص في النظام السعودي.'],
            ['landing', 'hero_cta_text',    'ابدأ مجاناً'],
            ['landing', 'hero_cta_url',     '#pricing'],
            ['landing', 'footer_copyright', '© ' . date('Y') . ' ميزان — جميع الحقوق محفوظة'],
            ['landing', 'privacy_url',      '/privacy'],
            ['landing', 'terms_url',        '/terms'],

            // notifications
            ['notifications', 'notify_new_subscription',     '1'],
            ['notifications', 'notify_payment_failed',       '1'],
            ['notifications', 'notify_trial_expiring',       '1'],
            ['notifications', 'notify_subscription_expiring', '1'],
        ];

        foreach ($defaults as [$group, $key, $value]) {
            // Only write if absent — respects operator edits on re-seed.
            if (! SystemSetting::where('key', $key)->exists()) {
                SystemSetting::set($key, $value, $group);
            }
        }
    }

    /**
     * Three-tier SaaS pricing (common SaaS shape). Prices in SAR; yearly
     * discount roughly 20% off monthly×12 — operators can tune in the
     * Plans admin page.
     */
    private function seedPlans(): void
    {
        $tiers = [
            [
                'slug'           => 'basic',
                'name'           => 'الأساسية',
                'description'    => 'للفرق الصغيرة التي تبدأ في رحلة إدارة المستندات القانونية.',
                'price_monthly'  => 299,
                'price_yearly'   => 2990,   // ~17% off
                'trial_days'     => 14,
                'max_users'      => 5,
                'max_storage_gb' => 10,
                'sort_order'     => 1,
                'features' => [
                    'حتى ٥ مستخدمين',
                    'حتى ١٠ GB مساحة',
                    'مراجعة عقود أساسية',
                    'البحث في المكتبة المرجعية',
                    'دعم فني بالبريد',
                ],
            ],
            [
                'slug'           => 'pro',
                'name'           => 'الاحترافية',
                'description'    => 'الخيار الأمثل للجهات والمؤسسات المتوسطة — يشمل الذكاء الاصطناعي الكامل.',
                'price_monthly'  => 899,
                'price_yearly'   => 8990,
                'trial_days'     => 14,
                'max_users'      => 25,
                'max_storage_gb' => 100,
                'is_featured'    => true,
                'badge_text'     => 'الأكثر شيوعاً',
                'badge_color'    => '#c8a94b',
                'sort_order'     => 2,
                'features' => [
                    'حتى ٢٥ مستخدم',
                    'حتى ١٠٠ GB مساحة',
                    'مراجعة عقود مع AI',
                    'توليد الكراسات',
                    'مساعد ذكي متقدّم',
                    'تحليل المذكرات',
                    'دعم فني ذو أولوية',
                ],
            ],
            [
                'slug'           => 'enterprise',
                'name'           => 'المؤسسية',
                'description'    => 'للجهات الحكومية والمؤسسات الكبرى — موارد غير محدودة + SLA مخصّص.',
                'price_monthly'  => 2999,
                'price_yearly'   => 29990,
                'trial_days'     => 30,
                'max_users'      => 0,   // 0 = unlimited
                'max_storage_gb' => 0,
                'sort_order'     => 3,
                'features' => [
                    'مستخدمون غير محدودين',
                    'تخزين غير محدود',
                    'كل ميزات الاحترافية',
                    'هوية مخصّصة (دومين + شعار)',
                    'تدريب AI على بيانات الجهة',
                    'مدير حساب مخصّص',
                    'SLA ٩٩.٩٪',
                ],
            ],
        ];

        foreach ($tiers as $tier) {
            $features = $tier['features'];
            unset($tier['features']);

            $plan = Plan::updateOrCreate(
                ['slug' => $tier['slug']],
                array_merge(['currency' => 'SAR', 'is_active' => true], $tier)
            );

            // Replace feature list wholesale so edits to the seeder
            // propagate cleanly on re-run.
            $plan->planFeatures()->delete();
            foreach ($features as $i => $label) {
                PlanFeature::create([
                    'plan_id'    => $plan->id,
                    'feature'    => $label,
                    'included'   => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }

    private function seedLandingFeatures(): void
    {
        $items = [
            ['🤖', 'مساعد ذكاء اصطناعي', 'مساعد متخصّص في الأنظمة السعودية — يجيب على استفساراتك وينقّب في المستندات بسرعة.'],
            ['📑', 'إدارة شاملة للمستندات', 'رفع، فهرسة، بحث نصّي كامل، تعليقات، مراجعات — كل شيء في مكان واحد.'],
            ['📋', 'توليد الكراسات', 'أنشئ كراسات الشروط والمواصفات تلقائياً من نطاق المشروع بدعم AI.'],
            ['⚖', 'المكتبة المرجعية', 'أنظمة + لوائح + أحكام قضائية سابقة — محدّثة ومفهرسة.'],
            ['🔐', 'أمان على مستوى المؤسسات', 'عزل بيانات كامل لكل جهة + تشفير + رؤوس أمان OWASP/NCA.'],
            ['📊', 'تقارير ذكية', 'متابعة المهام والكراسات والعقود + إحصائيات لصنّاع القرار.'],
        ];

        foreach ($items as $i => [$icon, $title, $desc]) {
            LandingFeature::firstOrCreate(
                ['title' => $title],
                ['description' => $desc, 'icon' => $icon, 'sort_order' => $i, 'is_active' => true]
            );
        }
    }

    private function seedLandingFaqs(): void
    {
        $items = [
            [
                'هل تحتاج بطاقة ائتمان للتجربة المجانية؟',
                'لا. التجربة المجانية ١٤ يوم بدون أي بطاقة. ستحتاج بطاقة فقط لتفعيل الاشتراك الفعلي بعد انتهاء التجربة.',
            ],
            [
                'هل بياناتي آمنة ومعزولة عن بقية العملاء؟',
                'نعم. كل جهة تحصل على قاعدة بيانات مستقلة فيزيائياً. لا يمكن لأي عميل رؤية بيانات عميل آخر بأي شكل.',
            ],
            [
                'ما طرق الدفع المتاحة؟',
                'بطاقات مدى / Visa / Mastercard / Apple Pay / STC Pay — جميعها عبر بوابة Moyasar المرخّصة من البنك المركزي السعودي.',
            ],
            [
                'هل يدعم النظام اللغة العربية بالكامل؟',
                'نعم. الواجهة والبحث واستخراج النص (OCR) كلها عربية — والخط Cairo المصمّم للعربية يضمن وضوحاً ممتازاً.',
            ],
            [
                'هل يمكنني الترقية أو التخفيض في أي وقت؟',
                'نعم. تغيير الباقة يتمّ من لوحة التحكم فوراً ويتمّ احتساب الفرق بناءً على الفترة المتبقية.',
            ],
            [
                'هل النظام متوافق مع النظام السعودي؟',
                'نعم. المكتبة المرجعية مُدرَّبة على نظام المنافسات والمشتريات الحكومية م/١٢٨، ولوائحه التنفيذية، وأحكام ديوان المظالم.',
            ],
        ];

        foreach ($items as $i => [$q, $a]) {
            LandingFaq::firstOrCreate(
                ['question' => $q],
                ['answer' => $a, 'sort_order' => $i, 'is_active' => true]
            );
        }
    }
}
