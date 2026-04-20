<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', 'لوحة التحكم') · {{ config('app.name', 'ميزان') }}</title>
    <meta name="description" content="منصة ميزان للبحث القانوني الذكي — مراجعة عقود وكراسات ومذكرات مع دعم AI.">

    {{-- Fonts are self-hosted via resources/css/fonts.css (audit #11 — PDPL). --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="mz-app" x-data="{ sidebarOpen: false }">
        {{-- Hamburger (mobile only) --}}
        <button type="button"
                class="mz-mobile-toggle"
                @click="sidebarOpen = !sidebarOpen"
                :aria-expanded="sidebarOpen"
                aria-controls="mz-sidebar"
                aria-label="فتح القائمة الجانبية">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round">
                <line x1="3" y1="6" x2="21" y2="6"/>
                <line x1="3" y1="12" x2="21" y2="12"/>
                <line x1="3" y1="18" x2="21" y2="18"/>
            </svg>
        </button>

        {{-- Sidebar backdrop (mobile only) --}}
        <div class="mz-sidebar-backdrop"
             x-show="sidebarOpen"
             x-cloak
             x-transition.opacity
             @click="sidebarOpen = false"
             aria-hidden="true"></div>

        @include('layouts.topbar')
        @include('layouts.sidebar')

        <main class="mz-main" role="main">
            {{ $slot }}
        </main>

        <x-toaster />
    </div>
</body>
</html>
