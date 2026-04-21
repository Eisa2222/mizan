@extends('super-admin.layouts.app')

@section('title', 'الملف الشخصي')
@section('heading', 'الملف الشخصي')

@section('content')
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;max-width:900px">
        {{-- ─── Profile info ─── --}}
        <form method="POST" action="{{ route('super-admin.profile.update') }}" class="sa-card">
            @csrf @method('PATCH')
            <h3 class="sa-card-title">بيانات الحساب</h3>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">الاسم</label>
                <input name="name" required value="{{ old('name', $admin->name) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('name') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">البريد الإلكتروني</label>
                <input type="email" name="email" required dir="ltr" value="{{ old('email', $admin->email) }}"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('email') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>

            <div style="font-size:12px;color:#8a91a4;margin-bottom:14px">
                آخر دخول: {{ $admin->updated_at?->diffForHumans() ?? '—' }}
            </div>

            <button type="submit" class="sa-btn">حفظ التعديلات</button>
        </form>

        {{-- ─── Password change ─── --}}
        <form method="POST" action="{{ route('super-admin.profile.password') }}" class="sa-card">
            @csrf @method('PATCH')
            <h3 class="sa-card-title">تغيير كلمة المرور</h3>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">كلمة المرور الحالية</label>
                <input type="password" name="current_password" required autocomplete="current-password"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('current_password', 'default') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">كلمة المرور الجديدة (٨ أحرف على الأقل)</label>
                <input type="password" name="password" required autocomplete="new-password" minlength="8"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                @error('password') <div style="color:#c75c5c;font-size:11px;margin-top:2px">{{ $message }}</div> @enderror
            </div>

            <div style="margin-bottom:14px">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">تأكيد كلمة المرور</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"
                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>

            <button type="submit" class="sa-btn">تحديث كلمة المرور</button>
        </form>
    </div>
@endsection
