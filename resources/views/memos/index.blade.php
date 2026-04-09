<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📄 مسودات المذكرات</div>
                <div class="mz-page-sub">ارفع مسودة مذكرة قانونية وسيقوم النظام بتحليلها وتقديم توصيات</div>
            </div>
            <a href="{{ route('memos.create') }}" class="mz-btn mz-btn-gold">+ رفع مسودة</a>
        </div>

        <div class="mz-card">
            <div class="mz-card-body" style="padding:8px">
                @forelse ($docs as $doc)
                    @php
                        $status = $doc->metadata['analysis_status'] ?? null;
                        $assessment = $doc->analysis['overall_assessment'] ?? null;
                        $score = $doc->analysis['persuasiveness_score'] ?? null;
                    @endphp
                    <a href="{{ route('memos.show', $doc) }}"
                       style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $doc->title }}</div>
                            <div style="font-size:11px;color:var(--mute)">
                                {{ $doc->uploader?->name ?? '—' }} · {{ $doc->created_at->diffForHumans() }}
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if ($status === 'ready' && $assessment)
                                <span class="mz-badge {{ match($assessment) { 'ممتازة' => 'mz-badge-green', 'جيدة' => 'mz-badge-gold', 'ضعيفة' => 'mz-badge-red', default => 'mz-badge-grey' } }}">{{ $assessment }}</span>
                                @if ($score)<span style="font-size:11px;color:var(--mute)">{{ $score }}/10</span>@endif
                            @elseif ($status === 'failed')
                                <span class="mz-badge mz-badge-red">فشل</span>
                            @elseif ($status === null)
                                <span class="mz-badge mz-badge-grey">⏳ قيد المعالجة</span>
                            @endif
                            <span class="mz-badge mz-badge-grey">📄 مذكرة</span>
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:48px 16px;color:var(--mute)">
                        <div style="font-size:48px;margin-bottom:12px">📄</div>
                        <div style="font-size:14px;margin-bottom:8px">لا توجد مسودات مذكرات</div>
                        <div style="font-size:12px">ارفع مسودة وسيقوم النظام بتحليل الحجج القانونية وتقديم توصيات</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($docs->hasPages())
            <div style="margin-top:16px">{{ $docs->links() }}</div>
        @endif
    </div>
</x-app-layout>
