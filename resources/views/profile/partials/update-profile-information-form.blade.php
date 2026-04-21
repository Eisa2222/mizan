<form method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <p style="font-size:12.5px;color:var(--mute);margin:0 0 16px;line-height:1.7">
        حدّث اسمك وبريدك الإلكتروني. تغيير البريد يستدعي إعادة تحقق.
    </p>

    <div class="mz-form-grid">
        <div class="mz-form-group">
            <label for="name" class="mz-flabel">الاسم الكامل</label>
            <input id="name" type="text" name="name" value="{{ old('name', $user->name) }}"
                   required autofocus autocomplete="name"
                   class="mz-inp">
            @error('name')<div class="mz-form-error">{{ $message }}</div>@enderror
        </div>

        <div class="mz-form-group">
            <label for="email" class="mz-flabel">البريد الإلكتروني</label>
            <input id="email" type="email" name="email" value="{{ old('email', $user->email) }}"
                   required autocomplete="username" dir="ltr"
                   class="mz-inp">
            @error('email')<div class="mz-form-error">{{ $message }}</div>@enderror

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div style="font-size:11px;color:var(--gold);margin-top:6px">
                    ⚠ بريدك الإلكتروني غير مُوثَّق.
                    <form method="POST" action="{{ route('verification.send') }}" style="display:inline">
                        @csrf
                        <button type="submit" style="background:none;border:none;color:var(--gold);text-decoration:underline;cursor:pointer;font-family:inherit;font-size:inherit">
                            أعد إرسال رابط التحقق
                        </button>
                    </form>
                </div>
                @if (session('status') === 'verification-link-sent')
                    <div style="font-size:11px;color:#3dbf8a;margin-top:6px">✓ تم إرسال رابط تحقق جديد إلى بريدك.</div>
                @endif
            @endif
        </div>
    </div>

    <div class="mz-form-actions">
        @if (session('status') === 'profile-updated')
            <span x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 2500)"
                  style="font-size:12px;color:#3dbf8a;margin-left:auto">✓ تم الحفظ</span>
        @endif
        <button type="submit" class="mz-btn mz-btn-gold">حفظ التعديلات</button>
    </div>
</form>
