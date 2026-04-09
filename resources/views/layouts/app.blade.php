<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'ميزان') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="mz-app">
        @include('layouts.topbar')
        @include('layouts.sidebar')

        <main class="mz-main">
            @if (session('success'))
                <div style="padding: 16px 24px 0">
                    <div class="mz-alert mz-alert-success">{{ session('success') }}</div>
                </div>
            @endif
            @if (session('error') || $errors->any())
                <div style="padding: 16px 24px 0">
                    <div class="mz-alert mz-alert-error">{{ session('error') ?? $errors->first() }}</div>
                </div>
            @endif

            {{ $slot }}
        </main>
    </div>
</body>
</html>
