<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>حساب موقوف</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: linear-gradient(135deg,#0b1220,#1a2338); min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; color: #e5e9f3; }
        .card { background: #fff; color: #0b1220; max-width: 520px; width: 100%; border-radius: 16px; padding: 44px 32px; text-align: center; }
        .icon { width: 76px; height: 76px; margin: 0 auto 20px; background: #fbe7e7; color: #c75c5c; font-size: 40px; display: grid; place-items: center; border-radius: 50%; }
        h1 { margin: 0 0 10px; font-size: 26px; }
        p { color: #4c5571; line-height: 1.8; margin: 0 0 14px; }
        code { background: #f5f7fb; padding: 4px 10px; border-radius: 6px; font-family: monospace; font-size: 13px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">⏸</div>
        <h1>الحساب موقوف مؤقتاً</h1>
        <p>تم تعليق حساب <strong>{{ $tenant->company_name ?? '' }}</strong> من قبل مدير النظام.</p>
        <p>للاستفسار أو إعادة التفعيل، يُرجى التواصل مع الدعم الفني:</p>
        @if ($support = \App\Models\SystemSetting::get('support_email'))
            <p><code dir="ltr">{{ $support }}</code></p>
        @endif
    </div>
</body>
</html>
