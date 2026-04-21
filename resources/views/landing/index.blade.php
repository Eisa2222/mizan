<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $settings['app_name'] }} — منصّة SaaS</title>
    <meta name="description" content="{{ $settings['hero_subtitle'] }}">

    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: #f7f8fc; color: #1a2138; margin: 0; line-height: 1.7; }
        .ld-container { max-width: 1180px; margin: 0 auto; padding: 0 24px; }
        .ld-nav { padding: 18px 0; background: rgba(255,255,255,.9); backdrop-filter: blur(8px); border-bottom: 1px solid #e3e7f0; position: sticky; top: 0; z-index: 30; }
        .ld-nav-row { display: flex; align-items: center; justify-content: space-between; }
        .ld-logo { font-weight: 800; font-size: 20px; color: #0b1220; display: inline-flex; align-items: center; gap: 10px; }
        .ld-logo-mark { width: 34px; height: 34px; border-radius: 10px; background: linear-gradient(135deg, #c8a94b, #d9bc5c); display: grid; place-items: center; font-size: 17px; color: #0b1220; }
        .ld-nav-links { display: flex; gap: 22px; align-items: center; font-size: 14px; }
        .ld-nav-links a { color: #485068; text-decoration: none; }
        .ld-nav-links a:hover { color: #c8a94b; }

        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 22px; border-radius: 10px; font-weight: 700; font-size: 14px; text-decoration: none; transition: all .15s; cursor: pointer; border: none; font-family: inherit; }
        .btn-primary { background: #c8a94b; color: #0b1220; }
        .btn-primary:hover { background: #b99a3e; transform: translateY(-1px); }
        .btn-ghost { background: transparent; color: #485068; border: 1px solid #d4d9e3; }
        .btn-ghost:hover { background: #f0f3f8; }

        .hero { padding: 80px 0 64px; }
        .hero-grid { display: grid; grid-template-columns: 1.1fr 0.9fr; gap: 48px; align-items: center; }
        @media (max-width: 860px) { .hero-grid { grid-template-columns: 1fr; text-align: center; } }
        .hero-title { font-size: 44px; font-weight: 800; color: #0b1220; margin: 0 0 18px; line-height: 1.25; }
        .hero-sub { font-size: 18px; color: #4c5571; margin: 0 0 28px; }
        .hero-ctas { display: flex; gap: 12px; }
        @media (max-width: 860px) { .hero-ctas { justify-content: center; } }
        .hero-image { text-align: center; }
        .hero-image img { max-width: 100%; border-radius: 16px; box-shadow: 0 30px 60px rgba(0,0,0,.12); }
        .hero-placeholder { aspect-ratio: 4/3; background: linear-gradient(135deg, #0b1220, #1a2338); border-radius: 16px; display: grid; place-items: center; font-size: 96px; color: #c8a94b; box-shadow: 0 30px 60px rgba(0,0,0,.2); }

        .section { padding: 72px 0; }
        .section-title { font-size: 32px; font-weight: 800; color: #0b1220; text-align: center; margin: 0 0 12px; }
        .section-sub { font-size: 15px; color: #6a7289; text-align: center; margin: 0 auto 48px; max-width: 640px; }

        .feat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 22px; }
        @media (max-width: 860px) { .feat-grid { grid-template-columns: 1fr; } }
        .feat-card { background: #fff; border: 1px solid #e3e7f0; border-radius: 14px; padding: 28px 24px; transition: all .2s; }
        .feat-card:hover { border-color: #c8a94b; transform: translateY(-4px); box-shadow: 0 12px 28px rgba(200,169,75,.15); }
        .feat-icon { font-size: 32px; margin-bottom: 14px; }
        .feat-title { font-size: 18px; font-weight: 700; color: #0b1220; margin: 0 0 8px; }
        .feat-desc { font-size: 14px; color: #6a7289; margin: 0; }

        .pricing-toggle { display: inline-flex; background: #fff; padding: 4px; border-radius: 10px; border: 1px solid #d4d9e3; margin-bottom: 36px; }
        .pricing-toggle button { padding: 8px 22px; border: none; background: transparent; font-weight: 600; font-size: 13px; color: #485068; cursor: pointer; border-radius: 7px; font-family: inherit; }
        .pricing-toggle button.active { background: #0b1220; color: #c8a94b; }
        .pricing-toggle .save-badge { background: #dff6e8; color: #1e6c44; padding: 2px 8px; border-radius: 10px; font-size: 10px; margin-inline-start: 6px; }

        .pricing-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 22px; }
        .price-card { background: #fff; border: 1px solid #e3e7f0; border-radius: 16px; padding: 32px 28px; position: relative; }
        .price-card.featured { border-color: #c8a94b; border-width: 2px; transform: scale(1.03); box-shadow: 0 20px 40px rgba(200,169,75,.15); }
        .price-badge { position: absolute; top: -12px; inset-inline-end: 20px; padding: 4px 14px; border-radius: 16px; font-size: 11px; font-weight: 700; background: #c8a94b; color: #0b1220; }
        .price-name { font-size: 20px; font-weight: 700; color: #0b1220; margin: 0 0 8px; }
        .price-desc { font-size: 13px; color: #6a7289; min-height: 38px; margin: 0 0 20px; }
        .price-amount { font-size: 38px; font-weight: 800; color: #0b1220; line-height: 1; margin: 0 0 6px; }
        .price-amount .price-cur { font-size: 16px; color: #6a7289; font-weight: 600; margin-inline-start: 4px; }
        .price-cycle { font-size: 12px; color: #8a91a4; margin: 0 0 24px; }
        .price-features { list-style: none; padding: 0; margin: 0 0 24px; }
        .price-features li { padding: 6px 0; font-size: 13px; color: #2b344a; display: flex; align-items: center; gap: 8px; }
        .price-features li .check { color: #1e6c44; }
        .price-features li.excluded { color: #a1a8bb; }
        .price-features li.excluded .check { color: #c4c9d6; }
        .price-cta { display: block; text-align: center; }

        .faq-list { max-width: 780px; margin: 0 auto; }
        .faq-item { background: #fff; border: 1px solid #e3e7f0; border-radius: 10px; margin-bottom: 10px; overflow: hidden; }
        .faq-q { padding: 18px 22px; font-weight: 600; color: #0b1220; cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-size: 15px; }
        .faq-q .arr { transition: transform .2s; }
        .faq-item.open .faq-q .arr { transform: rotate(180deg); }
        .faq-a { max-height: 0; overflow: hidden; transition: max-height .25s ease; padding: 0 22px; }
        .faq-item.open .faq-a { max-height: 500px; padding: 0 22px 18px; }
        .faq-a p { color: #4c5571; font-size: 14px; margin: 0; line-height: 1.85; }

        .footer { background: #0b1220; color: #c0c6d7; padding: 48px 0 32px; margin-top: 64px; }
        .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 36px; margin-bottom: 32px; }
        @media (max-width: 700px) { .footer-grid { grid-template-columns: 1fr; } }
        .footer h4 { color: #fff; font-size: 14px; margin: 0 0 14px; }
        .footer a { color: #8a91a4; text-decoration: none; display: block; padding: 4px 0; font-size: 13px; }
        .footer a:hover { color: #c8a94b; }
        .footer-copy { padding-top: 22px; border-top: 1px solid rgba(255,255,255,.08); font-size: 12px; color: #6a7289; text-align: center; }
    </style>
</head>
<body>
    <nav class="ld-nav">
        <div class="ld-container ld-nav-row">
            <a href="/" class="ld-logo">
                <span class="ld-logo-mark">⚖</span>
                <span>{{ $settings['app_name'] }}</span>
            </a>
            <div class="ld-nav-links">
                @if (count($features) > 0)  <a href="#features">المميزات</a>  @endif
                <a href="#pricing">الأسعار</a>
                @if (count($faqs) > 0)  <a href="#faq">الأسئلة</a>  @endif
                <a href="{{ route('super-admin.login') }}" class="btn btn-ghost" style="padding:8px 16px">دخول المدير</a>
            </div>
        </div>
    </nav>

    {{-- ══════════ Hero ══════════ --}}
    <section class="hero ld-container">
        <div class="hero-grid">
            <div>
                <h1 class="hero-title">{{ $settings['hero_title'] }}</h1>
                <p class="hero-sub">{{ $settings['hero_subtitle'] }}</p>
                <div class="hero-ctas">
                    <a href="{{ $settings['hero_cta_url'] }}" class="btn btn-primary">{{ $settings['hero_cta_text'] }} ←</a>
                    <a href="#pricing" class="btn btn-ghost">عرض الأسعار</a>
                </div>
            </div>
            <div class="hero-image">
                @if ($settings['hero_image'])
                    <img src="{{ $settings['hero_image'] }}" alt="{{ $settings['app_name'] }}">
                @else
                    <div class="hero-placeholder">⚖</div>
                @endif
            </div>
        </div>
    </section>

    {{-- ══════════ Features ══════════ --}}
    @if (count($features) > 0)
        <section class="section" id="features" style="background:#fff;border-top:1px solid #e3e7f0;border-bottom:1px solid #e3e7f0">
            <div class="ld-container">
                <h2 class="section-title">لماذا {{ $settings['app_name'] }}؟</h2>
                <p class="section-sub">كل ما تحتاجه لإدارة العمل القانوني، في مكان واحد.</p>
                <div class="feat-grid">
                    @foreach ($features as $feature)
                        <div class="feat-card">
                            @if ($feature->icon)
                                <div class="feat-icon">{!! str_starts_with(trim($feature->icon), '<') ? $feature->icon : e($feature->icon) !!}</div>
                            @endif
                            <h3 class="feat-title">{{ $feature->title }}</h3>
                            <p class="feat-desc">{{ $feature->description }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ Pricing ══════════ --}}
    <section class="section" id="pricing">
        <div class="ld-container" style="text-align:center">
            <h2 class="section-title">أسعار واضحة، بلا مفاجآت</h2>
            <p class="section-sub">اختر الباقة المناسبة لفريقك — بلا التزام، يمكنك الإلغاء في أي وقت.</p>

            <div x-data="{ cycle: 'monthly' }">
                <div class="pricing-toggle">
                    <button type="button" :class="cycle === 'monthly' ? 'active' : ''" @click="cycle = 'monthly'">شهري</button>
                    <button type="button" :class="cycle === 'yearly' ? 'active' : ''" @click="cycle = 'yearly'">
                        سنوي
                        @if ($plans->max('yearly_savings_percent') > 0)
                            <span class="save-badge">وفّر حتى {{ $plans->max('yearly_savings_percent') }}%</span>
                        @endif
                    </button>
                </div>

                <div class="pricing-grid">
                    @foreach ($plans as $plan)
                        <div class="price-card {{ $plan->is_featured ? 'featured' : '' }}">
                            @if ($plan->badge_text)
                                <div class="price-badge" style="background: {{ $plan->badge_color ?: '#c8a94b' }}">{{ $plan->badge_text }}</div>
                            @endif

                            <h3 class="price-name">{{ $plan->name }}</h3>
                            <p class="price-desc">{{ $plan->description }}</p>

                            <div class="price-amount" x-show="cycle === 'monthly'">
                                {{ number_format($plan->price_monthly, 0) }}<span class="price-cur">{{ $plan->currency }}</span>
                            </div>
                            <div class="price-amount" x-show="cycle === 'yearly'" x-cloak>
                                {{ number_format($plan->price_yearly, 0) }}<span class="price-cur">{{ $plan->currency }}</span>
                            </div>

                            <p class="price-cycle" x-show="cycle === 'monthly'">لكل شهر</p>
                            <p class="price-cycle" x-show="cycle === 'yearly'" x-cloak>
                                لكل سنة · يعادل {{ number_format($plan->price_yearly / 12, 0) }} ريال/شهر
                            </p>

                            <ul class="price-features">
                                @foreach ($plan->planFeatures as $pf)
                                    <li class="{{ ! $pf->included ? 'excluded' : '' }}">
                                        <span class="check">{{ $pf->included ? '✓' : '✗' }}</span>
                                        <span>{{ $pf->feature }}{{ $pf->limit ? ' — '.$pf->limit : '' }}</span>
                                    </li>
                                @endforeach
                                @if ($plan->trial_days > 0)
                                    <li><span class="check">✓</span><span>تجربة مجانية {{ $plan->trial_days }} يوم</span></li>
                                @endif
                            </ul>

                            <a :href="`{{ url('/checkout') }}/{{ $plan->id }}?cycle=${cycle}`"
                               class="btn btn-primary price-cta">ابدأ الآن ←</a>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ══════════ FAQ ══════════ --}}
    @if (count($faqs) > 0)
        <section class="section" id="faq" style="background:#fff;border-top:1px solid #e3e7f0">
            <div class="ld-container">
                <h2 class="section-title">الأسئلة الشائعة</h2>
                <p class="section-sub">إن لم تجد إجابتك — تواصل معنا مباشرة.</p>
                <div class="faq-list">
                    @foreach ($faqs as $faq)
                        <div class="faq-item" x-data="{ open: false }" :class="open ? 'open' : ''">
                            <div class="faq-q" @click="open = !open">
                                <span>{{ $faq->question }}</span>
                                <span class="arr">▾</span>
                            </div>
                            <div class="faq-a"><p>{{ $faq->answer }}</p></div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ══════════ Footer ══════════ --}}
    <footer class="footer">
        <div class="ld-container">
            <div class="footer-grid">
                <div>
                    <h4>{{ $settings['app_name'] }}</h4>
                    <p style="color:#6a7289;font-size:13px;max-width:380px;line-height:1.8">{{ $settings['hero_subtitle'] }}</p>
                </div>
                <div>
                    <h4>روابط</h4>
                    <a href="#features">المميزات</a>
                    <a href="#pricing">الأسعار</a>
                    <a href="#faq">الأسئلة الشائعة</a>
                </div>
                <div>
                    <h4>قانوني وتواصل</h4>
                    @if ($settings['privacy_url']) <a href="{{ $settings['privacy_url'] }}">سياسة الخصوصية</a> @endif
                    @if ($settings['terms_url'])   <a href="{{ $settings['terms_url'] }}">شروط الاستخدام</a>  @endif
                    @if ($settings['support_email']) <a href="mailto:{{ $settings['support_email'] }}" dir="ltr">{{ $settings['support_email'] }}</a> @endif
                    @if ($settings['support_phone']) <a href="tel:{{ $settings['support_phone'] }}" dir="ltr">{{ $settings['support_phone'] }}</a> @endif
                </div>
            </div>
            <div class="footer-copy">{{ $settings['footer_copyright'] }}</div>
        </div>
    </footer>

    <script src="//cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>
