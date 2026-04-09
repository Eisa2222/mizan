<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">المستندات القانونية</div>
                <div class="mz-page-sub">{{ $documents->total() }} مستند · بحث ذكي وفلترة</div>
            </div>
            <a href="{{ route('documents.create') }}" class="mz-btn mz-btn-gold">+ رفع مستند</a>
        </div>

        {{-- Search box --}}
        <div class="mz-card" style="padding:18px">
            <form method="GET" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                <div style="flex:1;min-width:200px;display:flex;align-items:center;gap:8px;background:var(--card2);border:1px solid var(--borderl);border-radius:10px;padding:8px 14px">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="color:var(--gold)"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    <input name="q" value="{{ request('q') }}"
                           placeholder="ابحث... مثال: شروط إنهاء عقد العمل"
                           style="flex:1;background:transparent;border:none;outline:none;color:var(--cream);font-family:inherit;font-size:14px;direction:rtl">
                </div>
                <select name="type" class="mz-inp" style="width:auto">
                    <option value="">كل الأنواع</option>
                    @foreach (\App\Models\LegalDocument::TYPES as $id => $label)
                        <option value="{{ $id }}" @selected(request('type') == $id)>{{ $label }}</option>
                    @endforeach
                </select>
                <button type="submit" class="mz-btn mz-btn-gold">بحث</button>
            </form>
        </div>

        <div class="mz-page-sub">عدد النتائج: {{ $documents->total() }}</div>

        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse ($documents as $doc)
                <a href="{{ route('documents.show', $doc) }}" class="mz-result-card">
                    <div class="mz-rc-head">
                        <div class="mz-rc-title">{{ $doc->title }}</div>
                        <span class="mz-badge mz-badge-gold">{{ $doc->type_label }}</span>
                    </div>
                    @if ($doc->title_en)
                        <div style="font-size:12px;color:var(--mute);margin-bottom:8px;direction:ltr;text-align:right">{{ $doc->title_en }}</div>
                    @endif
                    @if ($doc->summary)
                        <div class="mz-rc-body">{{ Str::limit($doc->summary, 200) }}</div>
                    @endif
                    <div class="mz-rc-footer">
                        @if ($doc->issued_at)<span>📅 {{ $doc->issued_at->format('Y-m-d') }}</span>@endif
                        @if ($doc->reference_number)<span>📋 {{ $doc->reference_number }}</span>@endif
                        @if ($doc->file_path)<span>📎 ملف مرفق</span>@endif
                    </div>
                </a>
            @empty
                <div class="mz-card" style="padding:48px;text-align:center;color:var(--mute)">
                    <div style="font-size:40px;margin-bottom:12px">📭</div>
                    <div style="font-size:14px">لا توجد مستندات</div>
                </div>
            @endforelse
        </div>

        @if ($documents->hasPages())
            <div style="margin-top:8px">{{ $documents->withQueryString()->links() }}</div>
        @endif
    </div>
</x-app-layout>
