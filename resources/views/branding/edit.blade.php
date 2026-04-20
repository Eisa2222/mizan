@section('title', 'هوية المؤسسة')

<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">هوية المؤسسة</div>
                <div class="mz-page-sub">إعدادات الشعار والترويسة والتذييل للكراسات والمستندات المصدّرة</div>
            </div>
        </div>

        @if (session('success'))
            <div style="background:rgba(80,200,120,.12);border:1px solid rgba(80,200,120,.4);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#5c8">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('branding.update') }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

                {{-- Left: Logo + Preview --}}
                <div class="mz-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;color:var(--gold);font-size:14px">الشعار</h3>

                    <div style="text-align:center;margin-bottom:16px">
                        @if ($org->logo_url)
                            <img src="{{ $org->logo_url }}" alt="شعار المؤسسة" style="max-width:200px;max-height:120px;border-radius:8px;background:#fff;padding:8px">
                            <div style="margin-top:8px">
                                <form method="POST" action="{{ route('branding.remove-logo') }}" style="display:inline" onsubmit="return confirm('حذف الشعار؟')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm" style="color:var(--red);font-size:11px">حذف الشعار</button>
                                </form>
                            </div>
                        @else
                            <div style="width:200px;height:120px;border:2px dashed var(--borderl);border-radius:10px;display:flex;align-items:center;justify-content:center;margin:0 auto;color:var(--mute);font-size:12px">
                                لا يوجد شعار
                            </div>
                        @endif
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">رفع شعار جديد <span style="font-size:10px;color:var(--mute)">(PNG, JPG, SVG — حد أقصى 2MB)</span></label>
                        <input type="file" name="logo" accept="image/png,image/jpeg,image/svg+xml,image/webp" class="mz-inp" style="padding:8px">
                        @error('logo') <div style="font-size:11px;color:var(--red);margin-top:4px">{{ $message }}</div> @enderror
                    </div>

                    {{-- Preview Card --}}
                    <h3 style="margin:24px 0 12px;color:var(--gold);font-size:14px">معاينة الترويسة</h3>
                    <div style="background:#fff;border-radius:8px;padding:16px;color:#1a1a1a;direction:rtl" id="preview-box">
                        <div style="display:flex;align-items:center;justify-content:space-between;border-bottom:2px solid;padding-bottom:12px;margin-bottom:8px" id="preview-header">
                            <div style="display:flex;align-items:center;gap:12px">
                                @if ($org->logo_url)
                                    <img src="{{ $org->logo_url }}" style="height:50px" id="preview-logo">
                                @else
                                    <div style="width:50px;height:50px;background:#eee;border-radius:6px" id="preview-logo-placeholder"></div>
                                @endif
                                <div>
                                    <div style="font-weight:700;font-size:14px" id="preview-name">{{ $org->name_ar }}</div>
                                    <div style="font-size:10px;color:#888" id="preview-header-text">{{ $org->header_text ?? 'كراسة الشروط والمواصفات' }}</div>
                                </div>
                            </div>
                        </div>
                        <div style="text-align:center;font-size:9px;color:#888;padding-top:8px;border-top:1px solid #eee" id="preview-footer">
                            {{ $org->footer_text ?? $org->phone . ' · ' . $org->email }}
                        </div>
                    </div>
                </div>

                {{-- Right: Info fields --}}
                <div class="mz-card" style="padding:20px">
                    <h3 style="margin:0 0 16px;color:var(--gold);font-size:14px">بيانات المؤسسة</h3>

                    <div class="mz-form-group">
                        <label class="mz-flabel">اسم المؤسسة (عربي) <span style="color:var(--red)">*</span></label>
                        <input type="text" name="name_ar" value="{{ old('name_ar', $org->name_ar) }}" required class="mz-inp">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">اسم المؤسسة (إنجليزي) <span style="font-size:10px;color:var(--mute)">(اختياري)</span></label>
                        <input type="text" name="name_en" value="{{ old('name_en', $org->name_en) }}" class="mz-inp" dir="ltr">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">الهاتف</label>
                            <input type="text" name="phone" value="{{ old('phone', $org->phone) }}" class="mz-inp" dir="ltr" placeholder="+966...">
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">البريد الإلكتروني</label>
                            <input type="email" name="email" value="{{ old('email', $org->email) }}" class="mz-inp" dir="ltr">
                        </div>
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">الموقع الإلكتروني</label>
                        <input type="text" name="website" value="{{ old('website', $org->website) }}" class="mz-inp" dir="ltr" placeholder="https://...">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">العنوان</label>
                        <input type="text" name="address" value="{{ old('address', $org->address) }}" class="mz-inp">
                    </div>

                    <h3 style="margin:20px 0 12px;color:var(--gold);font-size:14px">الترويسة والتذييل</h3>

                    <div class="mz-form-group">
                        <label class="mz-flabel">نص الترويسة <span style="font-size:10px;color:var(--mute)">(يظهر أعلى كل صفحة في الكراسة)</span></label>
                        <input type="text" name="header_text" value="{{ old('header_text', $org->header_text) }}" class="mz-inp" placeholder="مثال: كراسة الشروط والمواصفات — سري">
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">نص التذييل <span style="font-size:10px;color:var(--mute)">(يظهر أسفل كل صفحة)</span></label>
                        <input type="text" name="footer_text" value="{{ old('footer_text', $org->footer_text) }}" class="mz-inp" placeholder="مثال: جميع الحقوق محفوظة — المؤسسة العامة">
                    </div>

                    <h3 style="margin:20px 0 12px;color:var(--gold);font-size:14px">الألوان</h3>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">اللون الرئيسي</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" name="primary_color" value="{{ old('primary_color', $org->primary_color ?? '#1a3a52') }}" style="width:40px;height:36px;border:none;cursor:pointer;background:none">
                                <input type="text" value="{{ $org->primary_color ?? '#1a3a52' }}" class="mz-inp" style="flex:1" dir="ltr" readonly>
                            </div>
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">اللون الثانوي (التمييز)</label>
                            <div style="display:flex;gap:8px;align-items:center">
                                <input type="color" name="accent_color" value="{{ old('accent_color', $org->accent_color ?? '#c8a94b') }}" style="width:40px;height:36px;border:none;cursor:pointer;background:none">
                                <input type="text" value="{{ $org->accent_color ?? '#c8a94b' }}" class="mz-inp" style="flex:1" dir="ltr" readonly>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="margin-top:20px;text-align:left">
                <button type="submit" class="mz-btn mz-btn-gold">حفظ الهوية</button>
            </div>
        </form>
    </div>
</x-app-layout>
