<!DOCTYPE html>
<html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@yield('title', __('auth.page_title_login')) · {{ config('app.name', 'ميزان') }}</title>

        {{-- Fonts are self-hosted via resources/css/fonts.css (audit #11 — PDPL). --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased mz-guest-body">
        <div class="mz-guest-shell">
            <div class="mz-guest-brand">
                <div class="mz-guest-mark">⚖️</div>
                <div class="mz-guest-wordmark">
                    <div class="mz-guest-name">ميزان</div>
                    <div class="mz-guest-tag">Legal Research</div>
                </div>
            </div>

            <div class="mz-guest-card">
                {{ $slot }}
            </div>

            <div class="mz-guest-footer">
                منصة ميزان للبحث القانوني · v1.0
            </div>
        </div>
    </body>
</html>
