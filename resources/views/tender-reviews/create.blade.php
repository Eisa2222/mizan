<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📋 رفع كراسة للمراجعة</div>
                <div class="mz-page-sub">سيتم فحص الكراسة مقابل نظام المنافسات والمشتريات الحكومية ولائحته التنفيذية</div>
            </div>
            <a href="{{ route('tender-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('tender-reviews.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:760px;padding:24px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">عنوان الكراسة *</label>
                <input type="text" name="title" required value="{{ old('title') }}" class="mz-inp" placeholder="مثال: كراسة منافسة صيانة شبكة الحاسب الآلي">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="mz-form-group">
                    <label class="mz-flabel">نوع المنافسة</label>
                    <select name="tender_type" class="mz-inp">
                        <option value="">— اختر —</option>
                        <option value="عامة">منافسة عامة</option>
                        <option value="محدودة">منافسة محدودة</option>
                        <option value="شراء مباشر">شراء مباشر</option>
                        <option value="إطارية">اتفاقية إطارية</option>
                        <option value="مسابقة">مسابقة</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
                <div class="mz-form-group">
                    <label class="mz-flabel">القطاع</label>
                    <select name="sector" class="mz-inp">
                        <option value="">— اختر —</option>
                        <option value="تقنية المعلومات">تقنية المعلومات</option>
                        <option value="الإنشاءات">الإنشاءات</option>
                        <option value="الصيانة والتشغيل">الصيانة والتشغيل</option>
                        <option value="الاستشارات">الاستشارات</option>
                        <option value="التوريدات">التوريدات</option>
                        <option value="الخدمات">الخدمات</option>
                        <option value="أخرى">أخرى</option>
                    </select>
                </div>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">ملاحظات للمراجع (اختياري)</label>
                <textarea name="summary" rows="3" class="mz-inp mz-textarea" placeholder="مثال: يرجى التركيز على بنود الضمانات ومعايير التقييم">{{ old('summary') }}</textarea>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">ملف الكراسة * (PDF / Word — حتى 40 ميجا)</label>
                <input type="file" name="file" required accept=".pdf,.doc,.docx" class="mz-inp">
            </div>

            <div style="background:var(--card2);border:1px solid var(--borderl);border-radius:10px;padding:14px 16px;margin:14px 0">
                <div style="font-size:12px;font-weight:700;color:var(--gold);margin-bottom:8px">ماذا سيفحص النظام؟</div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;font-size:11px;color:var(--dim)">
                    <div>📜 <strong>الفحص النظامي</strong> — مطابقة نظام المنافسات</div>
                    <div>📋 <strong>الفحص الشكلي</strong> — اكتمال الأقسام والترقيم</div>
                    <div>⚖ <strong>الفحص الموضوعي</strong> — تعارضات ومخاطر</div>
                    <div>🏛 <strong>فحص العدالة</strong> — شروط مقيّدة للمنافسة</div>
                </div>
            </div>

            <div style="display:flex;gap:10px">
                <button type="submit" class="mz-btn mz-btn-gold">📋 رفع وبدء المراجعة</button>
                <a href="{{ route('tender-reviews.index') }}" class="mz-btn mz-btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</x-app-layout>
