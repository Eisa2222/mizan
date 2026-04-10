<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">✨ توليد الكراسات الذكي</div>
                <div class="mz-page-sub">أدخل وصف المشروع وسيقوم النظام بتوليد كراسة شروط ومواصفات كاملة جاهزة للطرح</div>
            </div>
            <a href="{{ route('tenders.create') }}" class="mz-btn mz-btn-gold">+ كراسة جديدة</a>
        </div>

        <div class="mz-card">
            <div class="mz-card-body" style="padding:8px">
                @forelse ($tenders as $tender)
                    @php
                        $review = $tender->review;
                        $score = $review?->compliance_score;
                    @endphp
                    <a href="{{ route('tenders.show', $tender) }}"
                       style="display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $tender->title }}</div>
                            <div style="font-size:11px;color:var(--mute)">
                                {{ $tender->creator?->name ?? '—' }} · {{ $tender->created_at->diffForHumans() }}
                                · {{ $tender->type_label }}
                                @if ($tender->duration) · {{ $tender->duration }} @endif
                            </div>
                        </div>
                        <div style="display:flex;gap:8px;align-items:center">
                            @if ($score !== null)
                                @php $color = $score >= 80 ? '#3dbf8a' : ($score >= 60 ? '#c8a94b' : '#e05555'); @endphp
                                <div style="width:44px;height:44px;border-radius:50%;border:3px solid {{ $color }};display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;color:{{ $color }}">
                                    {{ $score }}%
                                </div>
                            @endif
                            <span class="mz-badge {{ $tender->status === 'finalized' ? 'mz-badge-green' : ($tender->status === 'ready' ? 'mz-badge-gold' : 'mz-badge-grey') }}">
                                {{ $tender->status_label }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div style="text-align:center;padding:48px 16px;color:var(--mute)">
                        <div style="font-size:48px;margin-bottom:12px">✨</div>
                        <div style="font-size:14px;margin-bottom:8px">لا توجد كراسات مولّدة بعد</div>
                        <div style="font-size:12px">أنشئ كراسة جديدة وسيقوم النظام بتوليدها بالكامل خلال ثوانٍ</div>
                    </div>
                @endforelse
            </div>
        </div>

        @if ($tenders->hasPages())
            <div style="margin-top:16px">{{ $tenders->links() }}</div>
        @endif
    </div>
</x-app-layout>
