<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إضافة مستخدم جديد</div>
                <div class="mz-page-sub">إنشاء حساب مستخدم وتعيين الجهة والصلاحية</div>
            </div>
            <a href="{{ route('admin.users') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        @if ($errors->any())
            <div style="background:rgba(220,60,60,.12);border:1px solid rgba(220,60,60,.4);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#e55">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.users.store') }}" class="mz-card" style="padding:20px;max-width:600px">
            @csrf

            <div class="mz-form-group">
                <label class="mz-flabel">الاسم <span style="color:var(--red)">*</span></label>
                <input type="text" name="name" value="{{ old('name') }}" required class="mz-inp">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">البريد الإلكتروني <span style="color:var(--red)">*</span></label>
                <input type="email" name="email" value="{{ old('email') }}" required class="mz-inp" dir="ltr">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">كلمة المرور <span style="color:var(--red)">*</span></label>
                <input type="password" name="password" required class="mz-inp" dir="ltr" minlength="8">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">الجهة <span style="color:var(--red)">*</span></label>
                <select name="org_id" required class="mz-inp">
                    <option value="">اختر الجهة...</option>
                    @foreach ($orgs as $id => $name)
                        <option value="{{ $id }}" {{ old('org_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">الصلاحية <span style="color:var(--red)">*</span></label>
                <select name="role" required class="mz-inp">
                    @foreach ($roles as $role)
                        @if ($role !== \App\Enums\UserRole::SuperAdmin)
                            <option value="{{ $role->value }}" {{ old('role', 'OrgUser') === $role->value ? 'selected' : '' }}>
                                {{ $role->label() }} — {{ $role->description() }}
                            </option>
                        @endif
                    @endforeach
                </select>
            </div>

            <div style="margin-top:20px;text-align:left">
                <button type="submit" class="mz-btn mz-btn-gold">إنشاء المستخدم</button>
            </div>
        </form>
    </div>
</x-app-layout>
