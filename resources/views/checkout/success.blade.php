<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تم الاشتراك بنجاح · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: linear-gradient(135deg, #0b1220, #1a2338); min-height: 100vh; margin: 0; display: grid; place-items: center; padding: 24px; color: #fff; }
        .card { background: #fff; color: #0b1220; max-width: 520px; width: 100%; border-radius: 16px; padding: 40px 32px; text-align: center; }
        .icon { width: 72px; height: 72px; margin: 0 auto 18px; background: linear-gradient(135deg, #1e6c44, #2f9e6a); color: #fff; font-size: 40px; display: grid; place-items: center; border-radius: 50%; }
        h1 { margin: 0 0 8px; font-size: 26px; }
        p { color: #4c5571; line-height: 1.8; }
        code { background: #f5f7fb; padding: 4px 10px; border-radius: 6px; font-family: monospace; direction: ltr; display: inline-block; }
        .btn { display: inline-block; padding: 12px 28px; background: #c8a94b; color: #0b1220; text-decoration: none; border-radius: 8px; font-weight: 700; margin-top: 18px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✓</div>
        <h1>تم الاشتراك بنجاح!</h1>
        <p>شكراً لاشتراكك. سنُرسل إلى بريدك رابط تهيئة كلمة المرور خلال دقيقة — جاري إنشاء بيئتك الخاصة في الخلفية.</p>
        @if ($tenant)
            <p>بريد التأكيد سيصل إلى:<br><code>{{ $tenant->owner_email }}</code></p>
        @endif
        <a href="{{ route('landing') }}" class="btn">العودة للصفحة الرئيسية</a>
    </div>
</body>
</html>
