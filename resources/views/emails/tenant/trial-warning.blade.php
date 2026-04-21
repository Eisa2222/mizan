<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>تنبيه: تنتهي تجربتك قريباً</title>
    <style>
        body { font-family: Tahoma, Arial, sans-serif; background: #f5f7fb; margin: 0; padding: 24px; color: #1a2138; }
        .card { max-width: 560px; margin: 0 auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 16px rgba(0,0,0,.06); }
        .head { background: #fff8e6; padding: 24px; text-align: center; border-radius: 12px 12px 0 0; }
        .head h1 { margin: 0; font-size: 20px; color: #8a5a0f; }
        .body { padding: 26px; line-height: 1.9; }
        .cta { display: inline-block; padding: 12px 28px; background: #c8a94b; color: #0b1220 !important; text-decoration: none; border-radius: 8px; font-weight: 700; }
        .foot { padding: 18px; background: #f5f7fb; text-align: center; color: #6a7289; font-size: 12px; border-radius: 0 0 12px 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="head">
            <h1>⏰ تنتهي تجربتك خلال {{ $daysRemaining }} {{ $daysRemaining === 1 ? 'يوم' : ($daysRemaining === 2 ? 'يومين' : 'أيام') }}</h1>
        </div>
        <div class="body">
            <p>مرحباً {{ $tenant->owner_name }},</p>
            <p>تجربة <strong>{{ $tenant->company_name }}</strong> على باقة <strong>{{ $subscription->plan->name }}</strong> تنتهي يوم <strong>{{ $subscription->trial_ends_at?->translatedFormat('d F Y') }}</strong>.</p>
            <p>للاستمرار بدون انقطاع، فعّل الاشتراك من لوحة التحكم:</p>
            <p style="text-align:center;margin:22px 0">
                <a href="https://{{ $tenant->domains->first()?->domain }}/billing" class="cta">فعّل اشتراكي الآن ←</a>
            </p>
            <p style="font-size:13px;color:#6a7289">إن لم تتمّ الترقية، سيُعلَّق حسابك تلقائياً عند انتهاء الفترة ويمكنك التفعيل لاحقاً.</p>
        </div>
        <div class="foot">
            © {{ date('Y') }} {{ config('app.name') }}
        </div>
    </div>
</body>
</html>
