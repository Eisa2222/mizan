<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <p style="font-size:12.5px;color:var(--mute);margin:0 0 16px;line-height:1.7">
        استخدم كلمة مرور طويلة وعشوائية للحفاظ على أمان حسابك.
    </p>

    <div class="mz-form-grid">
        <div class="mz-form-group" style="grid-column:1/-1">
            <label for="update_password_current_password" class="mz-flabel">كلمة المرور الحالية</label>
            <input id="update_password_current_password" type="password" name="current_password"
                   autocomplete="current-password" class="mz-inp" dir="ltr">
            @foreach ($errors->updatePassword->get('current_password') as $message)
                <div class="mz-form-error">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mz-form-group">
            <label for="update_password_password" class="mz-flabel">كلمة المرور الجديدة</label>
            <input id="update_password_password" type="password" name="password"
                   autocomplete="new-password" class="mz-inp" dir="ltr">
            @foreach ($errors->updatePassword->get('password') as $message)
                <div class="mz-form-error">{{ $message }}</div>
            @endforeach
        </div>

        <div class="mz-form-group">
            <label for="update_password_password_confirmation" class="mz-flabel">تأكيد كلمة المرور</label>
            <input id="update_password_password_confirmation" type="password" name="password_confirmation"
                   autocomplete="new-password" class="mz-inp" dir="ltr">
            @foreach ($errors->updatePassword->get('password_confirmation') as $message)
                <div class="mz-form-error">{{ $message }}</div>
            @endforeach
        </div>
    </div>

    <div class="mz-form-actions">
        @if (session('status') === 'password-updated')
            <span x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)"
                  style="font-size:12px;color:#3dbf8a;margin-left:auto">✓ تم تحديث كلمة المرور</span>
        @endif
        <button type="submit" class="mz-btn mz-btn-gold">تحديث كلمة المرور</button>
    </div>
</form>
