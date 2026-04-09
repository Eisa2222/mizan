<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إنشاء مجلد جديد</div>
                <div class="mz-page-sub">نظّم مستنداتك وشاركها مع فريقك</div>
            </div>
            <a href="{{ route('folders.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
        </div>

        <form method="POST" action="{{ route('folders.store') }}" class="mz-card" style="max-width:600px;padding:24px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">اسم المجلد *</label>
                <input type="text" name="name" required value="{{ old('name') }}" class="mz-inp" placeholder="مثال: عقود 2026">
            </div>
            <div class="mz-form-group">
                <label class="mz-flabel">الوصف</label>
                <textarea name="description" rows="3" class="mz-inp mz-textarea">{{ old('description') }}</textarea>
            </div>
            <div style="display:flex;gap:10px">
                <button type="submit" class="mz-btn mz-btn-gold">إنشاء المجلد</button>
                <a href="{{ route('folders.index') }}" class="mz-btn mz-btn-ghost">إلغاء</a>
            </div>
        </form>
    </div>
</x-app-layout>
