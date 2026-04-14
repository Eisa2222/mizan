<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📄 رفع مسودة مذكرة</div>
                <div class="mz-page-sub">ارفع الملف أو اكتب النص مباشرة — سيتم تحليل الحجج والصياغة تلقائياً</div>
            </div>
            <a href="{{ route('memos.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('memos.store') }}" enctype="multipart/form-data" class="mz-card" style="max-width:760px;padding:24px"
              x-data="{ uploading: false }" @submit="uploading = true">
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
                <button type="submit" class="mz-btn mz-btn-gold" :disabled="uploading" x-text="uploading ? 'جاري الرفع والتحليل...' : '📄 رفع وتحليل'"></button>
                <a href="{{ route('memos.index') }}" class="mz-btn mz-btn-ghost" x-show="!uploading">إلغاء</a>
            </div>

            <template x-if="uploading">
                <div style="position:fixed;inset:0;background:rgba(10,12,18,.92);z-index:9999;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:20px">
                    <div style="width:50px;height:50px;border:4px solid var(--borderl);border-top:4px solid var(--gold);border-radius:50%;animation:spin 1s linear infinite"></div>
                    <div style="font-size:18px;font-weight:700;color:var(--cream)">جاري تحليل المذكرة...</div>
                    <div style="font-size:13px;color:var(--mute);max-width:450px;text-align:center;line-height:1.7">
                        يقوم الذكاء الاصطناعي بتحليل الحجج القانونية وتقييم الإقناع. قد تستغرق العملية حتى دقيقتين.
                    </div>
                </div>
            </template>
            <style>@keyframes spin { to { transform: rotate(360deg) } }</style>
        </form>
    </div>
</x-app-layout>
