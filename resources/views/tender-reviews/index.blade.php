<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📋 مراجعة الكراسات</div>
                <div class="mz-page-sub">فحص كراسات الشروط والمواصفات للتحقق من مطابقتها لنظام المنافسات والمشتريات الحكومية</div>
            </div>
            <a href="{{ route('tender-reviews.create') }}" class="mz-btn mz-btn-gold">+ رفع كراسة للمراجعة</a>
        </div>

        <div class="mz-card">
            <div class="mz-card-body" style="padding:8px">
                @forelse ($docs as $doc)
                    @php
                        $status = $doc->metadata['analysis_status'] ?? null;
                        $a = $doc->analysis ?? [];
                        $score = $a['compliance_score'] ?? null;
                        $findings = count($a['findings'] ?? []);
                        $ready = $a['ready_for_tender'] ?? null;
                    @endphp
                    <a href="{{ route('tender-reviews.show', $doc) }}"
                       style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $doc->title }}</div>
                            <div style="font-size:11px;color:var(--mute)">
                                {{ $doc->uploader?->name ?? '—' }} · {{ $doc->created_at->diffForHumans() }}
                                @if ($doc->file_name) · {{ $doc->file_name }} @endif
                                @if ($doc->metadata['tender_type'] ?? null) · {{ $doc->metadata['tender_type'] }} @endif
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if ($status === 'ready' && $score !== null)
                                {{-- Compliance score circle --}}
                                @php
                                    $color = $score >= 80 ? '#3dbf8a' : ($score >= 60 ? '#c8a94b' : '#e05555');
                                @endphp
                                <div style="width:44px;height:44px;border-radius:50%;border:3px solid {{ $color }};display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:{{ $color }}">
                                    {{ $score }}%
                                </div>
                                <div style="text-align:center;min-width:60px">
                                    <div style="font-size:12px;font-weight:700;color:var(--cream)">{{ $findings }} ملاحظة</div>
                                    @if ($ready === true)
                                        <div style="font-size:10px;color:#3dbf8a">✓ جاهزة للطرح</div>
                                    @elseif ($ready === false)
                                        <div style="font-size:10px;color:#e05555">⚠ تحتاج مراجعة</div>
                                    @endif
                                </div>
                            @elseif ($status === 'failed')
                                <span class="mz-badge mz-badge-red">فشل</span>
                            @else
                                <span class="mz-badge mz-badge-grey">⏳ قيد المراجعة</span>
                            @endif
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:48px 16px;color:var(--mute)">
                        <div style="font-size:48px;margin-bottom:12px">📋</div>
                        <div style="font-size:14px;margin-bottom:8px">لا توجد كراسات مرفوعة للمراجعة</div>
                        <div style="font-size:12px">ارفع كراسة شروط ومواصفات وسيقوم النظام بفحصها مقابل نظام المنافسات والمشتريات</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($docs->hasPages())
            <div style="margin-top:16px">{{ $docs->links() }}</div>
        @endif
    </div>
</x-app-layout>
