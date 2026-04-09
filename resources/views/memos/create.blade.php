<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📄 رفع مسودة مذكرة</div>
                <div class="mz-page-sub">ارفع الملف أو اكتب النص مباشرة — سيتم تحليل الحجج والصياغة تلقائياً</div>
            </div>
            <a href="{{ route('memos.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('memos.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:760px;padding:24px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">عنوان المذكرة *</label>
                <input type="text" name="title" required value="{{ old('title') }}" class="mz-inp" placeholder="مثال: مذكرة دفاع في قضية عمالية">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">نص المذكرة</label>
                <textarea name="content" rows="12" class="mz-inp mz-textarea" placeholder="يمكنك كتابة النص مباشرة هنا أو رفع ملف PDF/Word">{{ old('content') }}</textarea>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">أو ارفع ملف (PDF / Word)</label>
                <input type="file" name="file" accept=".pdf,.doc,.docx" class="mz-inp">
                <p style="font-size:11px;color:var(--mute);margin-top:6px">إذا رفعت ملفاً وكتبت نصاً، سيُستخدم النص المكتوب.</p>
            </div>

            <div style="display:flex;gap:10px;margin-top:14px">
                <button type="submit" class="mz-btn mz-btn-gold">📄 رفع وتحليل</button>
                <a href="{{ route('memos.index') }}" class="mz-btn mz-btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</x-app-layout>
