<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📝 مراجعة العقود</div>
                <div class="mz-page-sub">ارفع عقداً وسيقوم النظام بمراجعته وكشف المخاطر والمخالفات</div>
            </div>
            <a href="{{ route('contract-reviews.create') }}" class="mz-btn mz-btn-gold">+ رفع عقد للمراجعة</a>
        </div>

        <div class="mz-card">
            <div class="mz-card-body" style="padding:8px">
                @forelse ($docs as $doc)
                    @php
                        $status = $doc->metadata['analysis_status'] ?? null;
                        $rating = $doc->analysis['overall_rating'] ?? null;
                    @endphp
                    <a href="{{ route('contract-reviews.show', $doc) }}"
                       style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $doc->title }}</div>
                            <div style="font-size:11px;color:var(--mute)">
                                {{ $doc->uploader?->name ?? '—' }} · {{ $doc->created_at->diffForHumans() }}
                                @if ($doc->file_name) · {{ $doc->file_name }} @endif
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if ($status === 'ready' && $rating)
                                <span class="mz-badge {{ match($rating) { 'ممتاز' => 'mz-badge-green', 'جيد' => 'mz-badge-gold', 'خطير' => 'mz-badge-red', default => 'mz-badge-grey' } }}">{{ $rating }}</span>
                            @elseif ($status === 'failed')
                                <span class="mz-badge mz-badge-red">فشل</span>
                            @elseif ($status === null)
                                <span class="mz-badge mz-badge-grey">⏳ قيد المعالجة</span>
                            @endif
                            <span class="mz-badge mz-badge-grey">📝 مراجعة</span>
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:48px 16px;color:var(--mute)">
                        <div style="font-size:48px;margin-bottom:12px">📝</div>
                        <div style="font-size:14px;margin-bottom:8px">لا توجد عقود مرفوعة للمراجعة</div>
                        <div style="font-size:12px">ارفع عقداً وسيقوم النظام بتحليله وكشف المخاطر تلقائياً</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($docs->hasPages())
            <div style="margin-top:16px">{{ $docs->links() }}</div>
        @endif
    </div>
</x-app-layout>
