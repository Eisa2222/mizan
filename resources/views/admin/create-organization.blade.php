<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إنشاء جهة جديدة</div>
                <div class="mz-page-sub">سجّل جهة جديدة مع حساب المسؤول الأول</div>
            </div>
            <a href="{{ route('admin.organizations') }}" class="mz-btn mz-btn-ghost mz-btn-sm"><span class="mz-back-arrow">←</span> العودة</a>
        </div>

        @if ($errors->any())
            <div style="background:rgba(220,60,60,.12);border:1px solid rgba(220,60,60,.4);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#e55">
                @foreach ($errors->all() as $e)<div>{{ $e }}</div>@endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('admin.organizations.store') }}">
            @csrf

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                {{-- Organization info --}}
                <div class="mz-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;color:var(--gold);font-size:14px">بيانات الجهة</h3>

                    <div class="mz-form-group">
                        <label class="mz-flabel">اسم الجهة (عربي) <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name_ar" value="{{ old('name_ar') }}" required class="mz-inp" placeholder="مثال: وزارة المالية">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">اسم الجهة (إنجليزي)</label>
                        <input type="text" name="name_en" value="{{ old('name_en') }}" class="mz-inp" dir="ltr" placeholder="Ministry of Finance">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">النطاق (Domain) <span style="color:var(--red)">*</span></label>
                        <input type="text" name="domain" value="{{ old('domain') }}" required class="mz-inp" dir="ltr" placeholder="mof.gov.sa">
                        <div style="font-size:10px;color:var(--mute);margin-top:4px">معرّف فريد للجهة — يُستخدم للتسجيل التلقائي</div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">الهاتف</label>
                            <input type="text" name="phone" value="{{ old('phone') }}" class="mz-inp" dir="ltr">
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">البريد</label>
                            <input type="email" name="email" value="{{ old('email') }}" class="mz-inp" dir="ltr">
                        </div>
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">الموقع الإلكتروني</label>
                        <input type="text" name="website" value="{{ old('website') }}" class="mz-inp" dir="ltr">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">العنوان</label>
                        <input type="text" name="address" value="{{ old('address') }}" class="mz-inp">
                    </div>
                </div>

                {{-- Admin user --}}
                <div class="mz-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;color:var(--gold);font-size:14px">حساب مسؤول الجهة</h3>

                    <div class="mz-form-group">
                        <label class="mz-flabel">اسم المسؤول <span style="color:var(--red)">*</span></label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required class="mz-inp">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">البريد الإلكتروني <span style="color:var(--red)">*</span></label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" required class="mz-inp" dir="ltr">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">كلمة المرور <span style="color:var(--red)">*</span></label>
                        <input type="password" name="admin_password" required class="mz-inp" dir="ltr" minlength="8">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">الصلاحية <span style="color:var(--red)">*</span></label>
                        <select name="admin_role" required class="mz-inp">
                            @foreach (\App\Enums\UserRole::cases() as $role)
                                @if ($role !== \App\Enums\UserRole::SuperAdmin)
                                    <option value="{{ $role->value }}" {{ $role === \App\Enums\UserRole::OrgAdmin ? 'selected' : '' }}>
                                        {{ $role->label() }} — {{ $role->description() }}
                                    </option>
                                @endif
                            @endforeach
                        </select>
                    </div>

                    {{-- Role description card --}}
                    <div style="background:rgba(200,169,75,.08);border:1px solid rgba(200,169,75,.3);border-radius:10px;padding:14px;margin-top:16px;font-size:12px;color:var(--cream);line-height:1.7">
                        <div style="font-weight:700;margin-bottom:8px">شرح الصلاحيات:</div>
                        @foreach (\App\Enums\UserRole::cases() as $role)
                            @if ($role !== \App\Enums\UserRole::SuperAdmin)
                                <div style="margin-bottom:4px"><span style="color:var(--gold)">{{ $role->label() }}:</span> {{ $role->description() }}</div>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;text-align:left">
                <button type="submit" class="mz-btn mz-btn-gold">إنشاء الجهة والحساب</button>
            </div>
        </form>
    </div>
</x-app-layout>
