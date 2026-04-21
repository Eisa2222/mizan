<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">رفع مستند جديد</div>
                <div class="mz-page-sub">أضف مستنداً قانونياً للمكتبة</div>
            </div>
            <a href="{{ route('documents.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm"><span class="mz-back-arrow">←</span> العودة</a>
        </div>

        <form method="POST" action="{{ route('documents.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:760px;padding:24px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">العنوان (عربي) *</label>
                <input type="text" name="title" required value="{{ old('title') }}" class="mz-inp">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">العنوان (إنجليزي)</label>
                <input type="text" name="title_en" value="{{ old('title_en') }}" dir="ltr" class="mz-inp">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="mz-form-group">
                    <label class="mz-flabel">النوع *</label>
                    <select name="type" required class="mz-inp">
                        @foreach (\App\Models\LegalDocument::TYPES as $id => $label)
                            <option value="{{ $id }}" @selected(old('type') == $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mz-form-group">
                    <label class="mz-flabel">تاريخ الإصدار</label>
                    <input type="date" name="issued_at" value="{{ old('issued_at') }}" class="mz-inp">
                </div>
            </div>
            <p style="font-size:11px;color:var(--mute);margin:-8px 0 14px;line-height:1.6">
                💡 لمراجعة العقود أو تحليل المذكرات، استخدم الصفحات المخصصة في القائمة الجانبية.
            </p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="mz-form-group">
                    <label class="mz-flabel">رقم المرجع</label>
                    <input type="text" name="reference_number" value="{{ old('reference_number') }}" class="mz-inp">
                </div>
                <div class="mz-form-group">
                    <label class="mz-flabel">الجهة المُصدِرة</label>
                    <input type="text" name="source_entity" value="{{ old('source_entity') }}" class="mz-inp" placeholder="مثال: وزارة الموارد البشرية">
                </div>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">الملخص</label>
                <textarea name="summary" rows="3" class="mz-inp mz-textarea">{{ old('summary') }}</textarea>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">المحتوى</label>
                <textarea name="content" rows="6" class="mz-inp mz-textarea">{{ old('content') }}</textarea>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">الملف (PDF / Word / صور)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.tiff,.tif,.webp" class="mz-inp">
                <p style="font-size:11px;color:var(--mute);margin-top:6px">
                    💡 الصور والـ PDFs الممسوحة ضوئياً تُعالج تلقائياً عبر OCR في الخلفية — قد يستغرق دقائق بعد الرفع.
                </p>
            </div>

            <div style="display:flex;gap:10px;margin-top:8px">
                <button type="submit" class="mz-btn mz-btn-gold">رفع المستند</button>
                <a href="{{ route('documents.index') }}" class="mz-btn mz-btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</x-app-layout>
