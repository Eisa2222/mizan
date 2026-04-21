<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>انتهى الاشتراك</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: linear-gradient(135deg,#0b1220,#1a2338); min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; }
        .card { background: #fff; color: #0b1220; max-width: 560px; width: 100%; border-radius: 16px; padding: 44px 36px; text-align: center; }
        .icon { width: 76px; height: 76px; margin: 0 auto 20px; background: #fff2d6; color: #8a5a0f; font-size: 40px; display: grid; place-items: center; border-radius: 50%; }
        h1 { margin: 0 0 10px; font-size: 26px; }
        p { color: #4c5571; line-height: 1.8; margin: 0 0 14px; }
        .btn { display: inline-block; padding: 12px 28px; background: #c8a94b; color: #0b1220; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 14px; }
        .info { background: #f5f7fb; border-radius: 8px; padding: 12px 16px; margin: 18px 0; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏰</div>
        <h1>انتهى اشتراكك</h1>
        <p>انتهت صلاحية اشتراك <strong>{{ $tenant->company_name ?? '' }}</strong>.</p>

        @if ($subscription)
            <div class="info">
                الباقة: <strong>{{ $subscription->plan?->name ?? '—' }}</strong><br>
                انتهت في: <strong>{{ $subscription->ends_at?->translatedFormat('d F Y') }}</strong>
            </div>
        @endif

        <p>للاستمرار، جدّد اشتراكك من صفحة الباقات:</p>
        <a href="{{ url('/') }}" class="btn">عرض الباقات ←</a>
    </div>
</body>
</html>
