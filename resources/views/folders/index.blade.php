<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">المجلدات</div>
                <div class="mz-page-sub">{{ $folders->count() }} مجلد · مشاركة المستندات مع فريقك</div>
            </div>
            <a href="{{ route('folders.create') }}" class="mz-btn mz-btn-gold">+ مجلد جديد</a>
        </div>

        <div class="mz-g3">
            @forelse ($folders as $folder)
                <a href="{{ route('folders.show', $folder) }}" class="mz-card" style="text-decoration:none;color:inherit;padding:18px;display:block">
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
                        <div style="width:44px;height:44px;border-radius:11px;background:rgba(200,169,75,.1);display:grid;place-items:center;font-size:22px">📁</div>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;font-weight:700;color:var(--cream)">{{ $folder->name }}</div>
                            <div style="font-size:11px;color:var(--mute)">بواسطة {{ $folder->owner?->name }}</div>
                        </div>
                    </div>
                    @if ($folder->description)
                        <p style="font-size:12px;color:var(--dim);line-height:1.6;margin-bottom:12px">{{ Str::limit($folder->description, 80) }}</p>
                    @endif
                    <div style="display:flex;gap:6px;flex-wrap:wrap;font-size:11px;color:var(--mute)">
                        <span class="mz-badge mz-badge-grey">📄 {{ $folder->documents_count }}</span>
                        <span class="mz-badge mz-badge-grey">👥 {{ $folder->members_count }}</span>
                        @if ($folder->children_count > 0)
                            <span class="mz-badge mz-badge-grey">📂 {{ $folder->children_count }}</span>
                        @endif
                    </div>
                </a>
            @empty
                <div class="mz-card" style="padding:48px;text-align:center;color:var(--mute);grid-column:1/-1">
                    <div style="font-size:48px;margin-bottom:12px">📁</div>
                    <div style="font-size:14px">لا توجد مجلدات بعد</div>
                </div>
            @endforelse
        </div>
    </div>
</x-app-layout>
