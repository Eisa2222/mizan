<?php

namespace App\Http\Controllers;

use App\Models\LandingFaq;
use App\Models\LandingFeature;
use App\Models\Plan;
use App\Models\SystemSetting;
use Illuminate\View\View;

/**
 * Public marketing + pricing page served on central domains. Everything
 * (hero copy, features, FAQ, footer links) is loaded from the DB so
 * SuperAdmin can change it live without a deploy.
 */
class LandingController extends Controller
{
    public function __invoke(): View
    {
        $settings = [
            'app_name'          => SystemSetting::get('app_name', config('app.name', 'ميزان')),
            'app_logo'          => SystemSetting::get('app_logo'),
            'hero_title'        => SystemSetting::get('hero_title', 'منصّة قانونية ذكية للجهات الحكومية والخاصة'),
            'hero_subtitle'     => SystemSetting::get('hero_subtitle', 'إدارة العقود والكراسات والمذكرات والأحكام في مكان واحد مع مساعد ذكاء اصطناعي متخصّص.'),
            'hero_cta_text'     => SystemSetting::get('hero_cta_text', 'ابدأ مجاناً'),
            'hero_cta_url'      => SystemSetting::get('hero_cta_url', '#pricing'),
            'hero_image'        => SystemSetting::get('hero_image'),
            'footer_copyright'  => SystemSetting::get('footer_copyright', '© ' . date('Y') . ' — جميع الحقوق محفوظة'),
            'privacy_url'       => SystemSetting::get('privacy_url', '#'),
            'terms_url'         => SystemSetting::get('terms_url', '#'),
            'support_email'     => SystemSetting::get('support_email'),
            'support_phone'     => SystemSetting::get('support_phone'),
        ];

        return view('landing.index', [
            'plans'    => Plan::active()->with('planFeatures')->get(),
            'features' => LandingFeature::active()->get(),
            'faqs'     => LandingFaq::active()->get(),
            'settings' => $settings,
        ]);
    }
}
