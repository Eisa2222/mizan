<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>مرحباً بك في {{ config('app.name') }}</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1a2138; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
        .head { background: linear-gradient(135deg, #0b1220, #1a2338); color: #c8a94b; padding: 28px; text-align: center; }
        .head h1 { margin: 0; font-size: 22px; }
        .body { padding: 28px; line-height: 1.9; }
        .cta { display: inline-block; padding: 12px 28px; background: #c8a94b; color: #0b1220 !important; text-decoration: none; border-radius: 8px; font-weight: 700; margin: 12px 0; }
        .info { background: #f5f7fb; border-radius: 8px; padding: 14px 18px; margin: 18px 0; font-size: 14px; }
        .info strong { color: #0b1220; }
        .foot { padding: 20px; background: #f5f7fb; text-align: center; color: #6a7289; font-size: 12px; }
        code { background: #eef1f6; padding: 2px 6px; border-radius: 4px; font-size: 12px; direction: ltr; display: inline-block; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>مرحباً، {{ $tenant->owner_name }} 👋</h1>
            <p style="margin:8px 0 0;color:#c0c6d7;font-size:13px">تم إنشاء حساب {{ $tenant->company_name }} بنجاح</p>
        </div>

        <div class="body">
            <p>شكراً لاشتراكك في <strong>{{ config('app.name') }}</strong> — تمّ تجهيز كل شيء وبيئتك الخاصة جاهزة للعمل.</p>

            <div class="info">
                <div>الباقة: <strong>{{ $subscription->plan->name }}</strong> · {{ $subscription->billing_cycle === 'yearly' ? 'سنوي' : 'شهري' }}</div>
                @if ($subscription->isTrialing())
                    <div>تجربة مجانية تنتهي في: <strong>{{ $subscription->trial_ends_at?->translatedFormat('d F Y') }}</strong></div>
                @else
                    <div>ينتهي الاشتراك: <strong>{{ $subscription->ends_at?->translatedFormat('d F Y') }}</strong></div>
                @endif
                <div>نطاقك: <code>{{ $tenant->domains->first()?->domain }}</code></div>
            </div>

            <p><strong>الخطوة التالية:</strong> أنشئ كلمة مرور لحسابك (الرابط صالح 48 ساعة):</p>

            <p style="text-align:center">
                <a href="{{ $passwordSetupUrl }}" class="cta">تهيئة كلمة المرور ←</a>
            </p>

            <p style="font-size:12px;color:#6a7289;text-align:center;word-break:break-all">إذا لم يعمل الزر، انسخ هذا الرابط:<br><code>{{ $passwordSetupUrl }}</code></p>

            <p>بمجرد تهيئة كلمة المرور، ادخل من نطاقك: <code>https://{{ $tenant->domains->first()?->domain }}</code></p>
        </div>

        <div class="foot">
            @if ($supportEmail = \App\Models\SystemSetting::get('support_email'))
                للاستفسارات: <a href="mailto:{{ $supportEmail }}" style="color:#c8a94b">{{ $supportEmail }}</a><br>
            @endif
            © {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
