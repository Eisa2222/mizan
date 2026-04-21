<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'لوحة المدير العام') · {{ config('app.name', 'Mizaan SaaS') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .sa-shell { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; background: #f5f7fb; }
        .sa-side {
            background: #0b1220; color: #e5e9f3;
            padding: 22px 18px; border-inline-end: 1px solid rgba(255,255,255,.05);
        }
        .sa-brand { font-weight: 800; font-size: 18px; color: #c8a94b; margin-bottom: 6px; }
        .sa-brand-sub { font-size: 11px; color: #7a8196; margin-bottom: 24px; letter-spacing: 2px; text-transform: uppercase; }
        .sa-navlink {
            display: flex; align-items: center; gap: 10px;
            padding: 10px 12px; border-radius: 8px;
            color: #c0c6d7; text-decoration: none; font-size: 14px;
            transition: all .15s;
        }
        .sa-navlink:hover { background: rgba(255,255,255,.05); color: #fff; }
        .sa-navlink.active { background: #c8a94b; color: #0b1220; font-weight: 700; }
        .sa-nav-title { font-size: 10px; letter-spacing: 2px; color: #5b617a; margin: 20px 10px 6px; text-transform: uppercase; }

        .sa-main { padding: 28px 32px; overflow-y: auto; }
        .sa-topbar {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 22px; padding-bottom: 18px;
            border-bottom: 1px solid #e3e7f0;
        }
        .sa-page-title { font-size: 22px; font-weight: 700; color: #0b1220; margin: 0; }
        .sa-topbar-actions { display: flex; gap: 10px; align-items: center; font-size: 13px; }
        .sa-user-chip { display: inline-flex; align-items: center; gap: 8px; color: #485068; }

        .sa-card { background: #fff; border: 1px solid #e3e7f0; border-radius: 12px; padding: 18px 22px; box-shadow: 0 1px 2px rgba(11,18,32,.03); }
        .sa-card-title { font-size: 14px; font-weight: 700; color: #0b1220; margin: 0 0 14px; }

        .sa-stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 24px; }
        @media (max-width: 1024px) { .sa-stat-grid { grid-template-columns: repeat(2, 1fr); } }
        .sa-stat { background: #fff; border: 1px solid #e3e7f0; border-radius: 12px; padding: 18px; }
        .sa-stat-label { font-size: 12px; color: #6a7289; font-weight: 500; }
        .sa-stat-value { font-size: 28px; font-weight: 800; color: #0b1220; margin-top: 4px; font-variant-numeric: tabular-nums; }
        .sa-stat-delta { font-size: 11px; color: #2f9e6a; margin-top: 6px; }
        .sa-stat-delta.neg { color: #c75c5c; }

        .sa-flash { padding: 12px 16px; border-radius: 8px; margin-bottom: 18px; font-size: 13px; }
        .sa-flash.success { background: #e6f6ee; color: #1e6c44; border: 1px solid #b8e5ca; }
        .sa-flash.error   { background: #fbe7e7; color: #933; border: 1px solid #f4c1c1; }

        .sa-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 8px 14px; border-radius: 8px;
            background: #c8a94b; color: #0b1220; border: none;
            font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none;
            font-family: inherit;
        }
        .sa-btn:hover { background: #b99a3e; }
        .sa-btn-ghost { background: #fff; color: #485068; border: 1px solid #d4d9e3; }
        .sa-btn-ghost:hover { background: #f0f3f8; }
        .sa-btn-danger { background: #e05555; color: #fff; }
        .sa-btn-danger:hover { background: #c94646; }

        .sa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .sa-table th { text-align: start; padding: 10px 12px; background: #f5f7fb; font-weight: 600; color: #485068; border-bottom: 1px solid #e3e7f0; }
        .sa-table td { padding: 12px; border-bottom: 1px solid #eef1f6; color: #2b344a; }
        .sa-table tbody tr:hover { background: #fafbfd; }

        .sa-badge { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; }
        .sa-badge-green { background: #dff6e8; color: #1e6c44; }
        .sa-badge-red { background: #fbe7e7; color: #933; }
        .sa-badge-amber { background: #fff2d6; color: #8a5a0f; }
        .sa-badge-grey { background: #eef1f6; color: #485068; }

        body { font-family: 'Cairo', 'Tajawal', sans-serif; background: #f5f7fb; }
    </style>
</head>
<body class="antialiased">
    <div class="sa-shell">
        <aside class="sa-side" role="navigation">
            <div class="sa-brand">{{ config('app.name', 'ميزان') }}</div>
            <div class="sa-brand-sub">SaaS Admin</div>

            <a href="{{ route('super-admin.dashboard') }}" class="sa-navlink {{ request()->routeIs('super-admin.dashboard') ? 'active' : '' }}">📊 الرئيسية</a>

            <div class="sa-nav-title">العملاء</div>
            <a href="{{ route('super-admin.tenants.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.tenants.*') ? 'active' : '' }}">🏢 المستأجرون</a>
            <a href="{{ route('super-admin.subscriptions.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.subscriptions.*') ? 'active' : '' }}">📝 الاشتراكات</a>
            <a href="{{ route('super-admin.payments.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.payments.*') ? 'active' : '' }}">💳 المدفوعات</a>

            <div class="sa-nav-title">التسعير</div>
            <a href="{{ route('super-admin.plans.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.plans.*') ? 'active' : '' }}">📦 الباقات</a>
            <a href="{{ route('super-admin.coupons.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.coupons.*') ? 'active' : '' }}">🏷 الكوبونات</a>

            <div class="sa-nav-title">المحتوى</div>
            <a href="{{ route('super-admin.landing.features.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.landing.features.*') ? 'active' : '' }}">✨ المميزات</a>
            <a href="{{ route('super-admin.landing.faqs.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.landing.faqs.*') ? 'active' : '' }}">❓ الأسئلة الشائعة</a>

            <div class="sa-nav-title">النظام</div>
            <a href="{{ route('super-admin.settings.index') }}" class="sa-navlink {{ request()->routeIs('super-admin.settings.*') ? 'active' : '' }}">⚙ الإعدادات</a>
        </aside>

        <main class="sa-main">
            <div class="sa-topbar">
                <h1 class="sa-page-title">@yield('heading', 'لوحة المدير العام')</h1>
                <div class="sa-topbar-actions">
                    <span class="sa-user-chip">👤 {{ auth('super_admin')->user()?->name }}</span>
                    <form method="POST" action="{{ route('super-admin.logout') }}" style="display:inline">
                        @csrf
                        <button type="submit" class="sa-btn sa-btn-ghost">تسجيل خروج</button>
                    </form>
                </div>
            </div>

            @if (session('success'))
                <div class="sa-flash success">✓ {{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="sa-flash error">✗ {{ session('error') }}</div>
            @endif

            @yield('content')
        </main>
    </div>

    @stack('scripts')
</body>
</html>
