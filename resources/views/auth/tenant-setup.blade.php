<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تهيئة كلمة المرور · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
    <style>
        body { font-family: 'Cairo','Tajawal',sans-serif; background: linear-gradient(135deg,#0b1220,#1a2338); min-height: 100vh; display: grid; place-items: center; margin: 0; padding: 24px; }
        .card { background: #fff; max-width: 420px; width: 100%; border-radius: 14px; padding: 32px; }
        h1 { margin: 0 0 8px; font-size: 20px; color: #0b1220; }
        p.sub { color: #6a7289; font-size: 13px; margin: 0 0 22px; }
        label { display: block; font-size: 12px; font-weight: 600; color: #485068; margin-bottom: 4px; }
        input { width: 100%; padding: 10px 12px; border: 1px solid #d4d9e3; border-radius: 8px; box-sizing: border-box; font-family: inherit; margin-bottom: 14px; }
        .btn { width: 100%; padding: 12px; background: #c8a94b; color: #0b1220; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-family: inherit; }
        .err { color: #c75c5c; font-size: 12px; margin: -8px 0 10px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>تهيئة كلمة المرور</h1>
        <p class="sub">أنشئ كلمة مرور لحسابك — ستستخدمها للدخول من هذا النطاق.</p>

        <form method="POST" action="{{ url('/password/setup') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <label for="email">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email', $email) }}" dir="ltr" required readonly>
            @error('email') <div class="err">{{ $message }}</div> @enderror

            <label for="password">كلمة المرور الجديدة</label>
            <input id="password" type="password" name="password" required autocomplete="new-password">
            @error('password') <div class="err">{{ $message }}</div> @enderror

            <label for="password_confirmation">تأكيد كلمة المرور</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">

            <button type="submit" class="btn">حفظ كلمة المرور والدخول</button>
        </form>
    </div>
</body>
</html>
