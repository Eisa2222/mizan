@section('title', 'تسجيل الدخول')

<x-guest-layout>
    <x-auth-session-status class="mz-guest-status" :status="session('status')" />

    <h1 class="mz-guest-title">أهلاً بعودتك</h1>
    <p class="mz-guest-sub">سجّل الدخول للمتابعة إلى منصة ميزان</p>

    <form method="POST" action="{{ route('login') }}" class="mz-guest-form">
        @csrf

        <div class="mz-form-group">
            <label for="email" class="mz-flabel">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username" dir="ltr"
                   class="mz-inp" placeholder="you@example.com">
            @error('email')
                <div class="mz-form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="mz-form-group" x-data="{ show: false }">
            <label for="password" class="mz-flabel">كلمة المرور</label>
            <div class="mz-pwd-wrap">
                <input id="password" name="password" required autocomplete="current-password"
                       :type="show ? 'text' : 'password'"
                       type="password"
                       class="mz-inp mz-pwd-input"
                       placeholder="••••••••">
                <button type="button" class="mz-pwd-toggle"
                        @click="show = !show"
                        :aria-label="show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور'"
                        tabindex="-1">
                    <span x-show="!show" x-cloak>👁</span>
                    <span x-show="show" x-cloak>🙈</span>
                </button>
            </div>
            @error('password')
                <div class="mz-form-error">{{ $message }}</div>
            @enderror
        </div>

        <div class="mz-guest-options">
            <label class="mz-guest-remember">
                <input type="checkbox" name="remember">
                <span>تذكرني</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="mz-guest-forgot">هل نسيت كلمة المرور؟</a>
            @endif
        </div>

        <button type="submit" class="mz-btn mz-btn-gold mz-guest-submit">
            تسجيل الدخول
        </button>
    </form>
</x-guest-layout>
