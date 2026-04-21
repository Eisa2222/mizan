{{--
    Shared scaffold for branded error screens (404/403/500/429 + SaaS
    business errors like suspended/expired). Sections:
      @yield('code')    — three-digit HTTP-ish label
      @yield('icon')    — single emoji or SVG
      @yield('title')   — Arabic heading
      @yield('message') — 1-2 sentence body
      @yield('action')  — optional CTA button
--}}
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'خطأ') · {{ config('app.name', 'ميزان') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: linear-gradient(135deg,#0b1220,#1a2338); min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; }
        .card { background: #fff; color: #0b1220; max-width: 540px; width: 100%; border-radius: 16px; padding: 48px 36px; text-align: center; box-shadow: 0 30px 80px rgba(0,0,0,.4); }
        .code { font-size: 13px; font-weight: 800; letter-spacing: 3px; color: #c8a94b; text-transform: uppercase; margin: 0 0 10px; }
        .icon { width: 88px; height: 88px; margin: 0 auto 20px; font-size: 44px; display: grid; place-items: center; border-radius: 50%; }
        h1 { margin: 0 0 10px; font-size: 26px; line-height: 1.4; }
        p { color: #4c5571; line-height: 1.85; margin: 0 0 14px; font-size: 15px; }
        .btn { display: inline-block; padding: 12px 28px; background: #c8a94b; color: #0b1220; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 14px; }
        .btn:hover { background: #b99a3e; }
        .btn-ghost { background: transparent; color: #485068; border: 1px solid #d4d9e3; margin-inline-start: 8px; }
        code { background: #f5f7fb; padding: 3px 8px; border-radius: 4px; font-family: monospace; font-size: 12px; direction: ltr; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        @hasSection('code') <p class="code">@yield('code')</p> @endif
        @hasSection('icon') <div class="icon" style="@yield('icon_style', 'background:#fff2d6;color:#8a5a0f')">@yield('icon')</div> @endif

        <h1>@yield('title')</h1>
        @yield('message')
        @yield('action')
    </div>
</body>
</html>
