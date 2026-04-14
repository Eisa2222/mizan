<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📝 رفع عقد للمراجعة</div>
                <div class="mz-page-sub">ارفع العقد وسيتم تحليله تلقائياً: كشف المخاطر، المخالفات، التعديلات المقترحة</div>
            </div>
            <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('contract-reviews.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:680px;padding:24px"
              x-data="{ uploading: false }" @submit="uploading = true">
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
                <button type="submit" class="mz-btn mz-btn-gold" :disabled="uploading" x-text="uploading ? 'جاري الرفع والمراجعة...' : '📝 رفع ومراجعة'"></button>
                <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost" x-show="!uploading">إلغاء</a>
            </div>

            <template x-if="uploading">
                <div style="position:fixed;inset:0;background:rgba(10,12,18,.92);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px">
                    <div style="width:50px;height:50px;border:4px solid var(--borderl);border-top:4px solid var(--gold);border-radius:50%;animation:spin 1s linear infinite"></div>
                    <div style="font-size:18px;font-weight:700;color:var(--cream)">جاري رفع العقد ومراجعته...</div>
                    <div style="font-size:13px;color:var(--mute);max-width:450px;text-align:center;line-height:1.7">
                        يقوم الذكاء الاصطناعي بتحليل العقد واكتشاف المخاطر القانونية. قد تستغرق العملية حتى دقيقتين.
                    </div>
                </div>
            </template>
            <style>@keyframes spin { to { transform: rotate(360deg) } }</style>
        </form>
    </div>
</x-app-layout>
