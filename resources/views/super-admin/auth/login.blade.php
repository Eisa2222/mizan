<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل دخول المدير العام · {{ config('app.name', 'Mizaan SaaS') }}</title>

    @vite(['resources/css/app.css'])

    <style>
        body { font-family: 'Cairo', 'Tajawal', sans-serif; background: linear-gradient(135deg, #0b1220 0%, #1a2338 100%); min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .login-card { width: 100%; max-width: 420px; background: #fff; border-radius: 16px; padding: 32px 28px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        .login-brand { text-align: center; margin-bottom: 26px; }
        .login-logo { width: 56px; height: 56px; margin: 0 auto 12px; display: grid; place-items: center; font-size: 28px; background: linear-gradient(135deg, #c8a94b, #d9bc5c); border-radius: 14px; }
        .login-title { font-size: 20px; font-weight: 800; color: #0b1220; margin: 0 0 4px; }
        .login-sub { font-size: 13px; color: #6a7289; margin: 0; }
        .form-group { margin-bottom: 16px; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #485068; margin-bottom: 6px; }
        .form-input { width: 100%; padding: 10px 14px; border: 1px solid #d4d9e3; border-radius: 8px; font-size: 14px; font-family: inherit; background: #fafbfd; transition: all .15s; }
        .form-input:focus { outline: none; border-color: #c8a94b; background: #fff; box-shadow: 0 0 0 3px rgba(200,169,75,.12); }
        .form-error { color: #c75c5c; font-size: 12px; margin-top: 4px; }
        .form-check { display: inline-flex; align-items: center; gap: 8px; font-size: 13px; color: #485068; }
        .login-btn { width: 100%; padding: 12px; background: #c8a94b; color: #0b1220; border: none; border-radius: 8px; font-weight: 700; font-size: 15px; cursor: pointer; margin-top: 8px; font-family: inherit; }
        .login-btn:hover { background: #b99a3e; }
        .login-footer { text-align: center; font-size: 11px; color: #8a91a4; margin-top: 18px; padding-top: 16px; border-top: 1px solid #eef1f6; }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-brand">
            <div class="login-logo">⚙</div>
            <h1 class="login-title">لوحة المدير العام</h1>
            <p class="login-sub">دخول مُدارى SaaS — {{ config('app.name', 'ميزان') }}</p>
        </div>

        <form method="POST" action="{{ route('super-admin.login.store') }}">
            @csrf

            <div class="form-group">
                <label for="email" class="form-label">البريد الإلكتروني</label>
                <input id="email" type="email" name="email" dir="ltr" required autofocus autocomplete="username"
                       value="{{ old('email') }}" class="form-input">
                @error('email') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label for="password" class="form-label">كلمة المرور</label>
                <input id="password" type="password" name="password" required autocomplete="current-password" class="form-input">
                @error('password') <div class="form-error">{{ $message }}</div> @enderror
            </div>

            <div class="form-group">
                <label class="form-check">
                    <input type="checkbox" name="remember" value="1">
                    <span>تذكّرني على هذا الجهاز</span>
                </label>
            </div>

            <button type="submit" class="login-btn">تسجيل الدخول</button>
        </form>

        <div class="login-footer">
            محمي بطبقة أمان مستقلة عن جلسات المستأجرين · {{ date('Y') }}
        </div>
    </div>
</body>
</html>
