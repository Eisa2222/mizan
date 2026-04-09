<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📝 رفع عقد للمراجعة</div>
                <div class="mz-page-sub">ارفع العقد وسيتم تحليله تلقائياً: كشف المخاطر، المخالفات، التعديلات المقترحة</div>
            </div>
            <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('contract-reviews.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:680px;padding:24px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">عنوان العقد *</label>
                <input type="text" name="title" required value="{{ old('title') }}" class="mz-inp" placeholder="مثال: عقد توظيف مدير مبيعات">
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">ملاحظات أولية</label>
                <textarea name="summary" rows="3" class="mz-inp mz-textarea" placeholder="(اختياري) أي ملاحظات تود أن يركّز عليها التحليل">{{ old('summary') }}</textarea>
            </div>

            <div class="mz-form-group">
                <label class="mz-flabel">ملف العقد * (PDF / Word)</label>
                <input type="file" name="file" required accept=".pdf,.doc,.docx" class="mz-inp">
            </div>

            <div style="display:flex;gap:10px;margin-top:14px">
                <button type="submit" class="mz-btn mz-btn-gold">📝 رفع ومراجعة</button>
                <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</x-app-layout>
