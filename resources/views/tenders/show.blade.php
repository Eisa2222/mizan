<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $tender->title }}</div>
                <div class="mz-page-sub">
                    ✨ كراسة مولّدة · {{ $tender->type_label }} · {{ $tender->status_label }}
                    @if ($tender->duration) · {{ $tender->duration }} @endif
                </div>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="POST" action="{{ route('tenders.review', $tender) }}" style="display:inline">
                    @csrf
                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm">⚖ مراجعة الامتثال</button>
                </form>
                <form method="POST" action="{{ route('tenders.regenerate', $tender) }}" style="display:inline"
                      onsubmit="return confirm('سيتم استبدال جميع الأقسام والبنود. هل أنت متأكد؟')">
                    @csrf
                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm">🔄 إعادة التوليد</button>
                </form>
                <a href="{{ route('tenders.export.pdf', $tender) }}" class="mz-btn mz-btn-ghost mz-btn-sm">📄 PDF</a>
                <a href="{{ route('tenders.export.docx', $tender) }}" class="mz-btn mz-btn-ghost mz-btn-sm">📝 Word</a>
                <a href="{{ route('tenders.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة</a>
            </div>
        </div>

        @if ($tender->review)
            @php
                $review = $tender->review;
                $score = $review->compliance_score;
                $color = $score >= 80 ? '#3dbf8a' : ($score >= 60 ? '#c8a94b' : '#e05555');
                $stats = $review->statistics ?? [];
            @endphp
            <div class="mz-card" style="margin-bottom:16px">
                <div class="mz-card-body" style="display:flex;gap:18px;align-items:center;padding:18px">
                    <div style="width:80px;height:80px;border-radius:50%;border:5px solid {{ $color }};display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:900;color:{{ $color }};flex-shrink:0">
                        {{ $score }}%
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:13px;font-weight:700;color:var(--cream);margin-bottom:6px">نتيجة مراجعة الامتثال</div>
                        <div style="font-size:12px;color:var(--dim);line-height:1.7">{{ $review->summary }}</div>
                        <div style="display:flex;gap:10px;margin-top:8px;font-size:11px">
                            @if (($stats['critical'] ?? 0) > 0)
                                <span style="color:#e05555">⚠ {{ $stats['critical'] }} حرجة</span>
                            @endif
                            @if (($stats['high'] ?? 0) > 0)
                                <span style="color:#ff8a4b">⚠ {{ $stats['high'] }} عالية</span>
                            @endif
                            @if (($stats['medium'] ?? 0) > 0)
                                <span style="color:#c8a94b">○ {{ $stats['medium'] }} متوسطة</span>
                            @endif
                            @if (($stats['improvement'] ?? 0) > 0)
                                <span style="color:#3dbf8a">✓ {{ $stats['improvement'] }} تحسينية</span>
                            @endif
                        </div>
                    </div>
                </div>
                @if (! empty($review->issues))
                    <div class="mz-card-body" style="border-top:1px solid var(--borderl);padding:14px 18px">
                        <div style="font-size:11px;font-weight:800;color:var(--mute);margin-bottom:8px">الملاحظات</div>
                        @foreach ($review->issues as $issue)
                            @php
                                $sevColor = ['critical'=>'#e05555','high'=>'#ff8a4b','medium'=>'#c8a94b','improvement'=>'#3dbf8a'][$issue['severity']] ?? '#888';
                                $sevLabel = ['critical'=>'حرجة','high'=>'عالية','medium'=>'متوسطة','improvement'=>'تحسينية'][$issue['severity']] ?? $issue['severity'];
                            @endphp
                            <div style="border-right:3px solid {{ $sevColor }};background:var(--card2);padding:10px 12px;border-radius:8px;margin-bottom:6px">
                                <div style="display:flex;justify-content:space-between;margin-bottom:4px;flex-wrap:wrap;gap:6px">
                                    <span style="font-size:12px;font-weight:700;color:var(--cream)">{{ $issue['title'] }}</span>
                                    <div style="display:flex;gap:6px">
                                        @if (! empty($issue['violation_type']))
                                            @php $vColor = str_contains($issue['violation_type'], 'مؤكدة') ? '#e05555' : '#c8a94b'; @endphp
                                            <span style="font-size:9px;padding:2px 6px;border-radius:5px;background:rgba(0,0,0,.2);color:{{ $vColor }};font-weight:700">{{ $issue['violation_type'] }}</span>
                                        @endif
                                        <span style="font-size:10px;color:{{ $sevColor }};font-weight:800">{{ $sevLabel }}</span>
                                    </div>
                                </div>
                                <div style="font-size:11px;color:var(--dim);line-height:1.6">{{ $issue['issue'] }}</div>
                                @if (! empty($issue['legal_reference']))
                                    <div style="font-size:11px;color:var(--gold);margin-top:4px">📜 {{ $issue['legal_reference'] }}@if (! empty($issue['reference_source'])) — {{ $issue['reference_source'] }}@endif</div>
                                @endif
                                @if (! empty($issue['recommendation']))
                                    <div style="font-size:11px;color:#3dbf8a;margin-top:4px">💡 {{ $issue['recommendation'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        {{-- Similarity Panel --}}
        <div x-data="similarityPanel()" x-init="loadResults()" style="margin-bottom:16px">
            <template x-if="topAlert">
                <div class="mz-card" :style="alertStyle()" style="padding:14px 18px;margin-bottom:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <div>
                            <span x-text="alertIcon()" style="font-size:18px;margin-left:8px"></span>
                            <span style="font-weight:700" x-text="topAlert.message"></span>
                            <span style="font-size:12px;color:var(--mute);margin-right:8px" x-text="matches.length + ' كراسة مشابهة'"></span>
                        </div>
                        <button @click="showPanel = !showPanel" class="mz-btn mz-btn-ghost mz-btn-sm" x-text="showPanel ? 'إخفاء' : 'عرض التفاصيل'"></button>
                    </div>
                </div>
            </template>

            <template x-if="showPanel && matches.length > 0">
                <div class="mz-card" style="padding:0;overflow:hidden;margin-bottom:12px">
                    <div style="padding:12px 16px;background:var(--card2);border-bottom:1px solid var(--borderl);font-size:13px;font-weight:700;color:var(--gold)">
                        كراسات مشابهة داخل الجهة
                    </div>
                    <template x-for="m in matches" :key="m.tender_id">
                        <div style="padding:12px 16px;border-bottom:1px solid var(--borderl)">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
                                <div style="flex:1">
                                    <div style="font-size:13px;font-weight:700;color:var(--cream)" x-text="m.title"></div>
                                    <div style="font-size:11px;color:var(--mute);margin-top:3px">
                                        <span x-text="m.type_label"></span> ·
                                        <span x-text="m.status_label"></span>
                                        <template x-if="m.compliance_score">
                                            <span> · امتثال: <span x-text="m.compliance_score + '%'" style="color:var(--gold)"></span></span>
                                        </template>
                                    </div>
                                    <template x-if="m.lessons_learned && m.lessons_learned.length">
                                        <div style="font-size:11px;color:var(--dim);margin-top:4px">
                                            <span style="color:var(--gold)">دروس مستفادة:</span>
                                            <template x-for="l in m.lessons_learned.slice(0,2)"><span x-text="' · ' + l"></span></template>
                                        </div>
                                    </template>
                                </div>
                                <div style="text-align:center;min-width:80px">
                                    <div style="font-size:20px;font-weight:900" :style="'color:' + scoreColor(m.final_similarity_score)" x-text="Math.round(m.final_similarity_score) + '%'"></div>
                                    <div style="font-size:10px;color:var(--mute)" x-text="m.similarity_label || (m.similarity_level === 'partial_match' ? 'تطابق جزئي' : '')"></div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:4px">
                                    <a :href="'/tenders/' + m.tender_id" target="_blank" class="mz-btn mz-btn-ghost mz-btn-sm" style="font-size:11px">فتح</a>
                                <button @click="ignoreTender(m)" class="mz-btn mz-btn-ghost mz-btn-sm" style="font-size:11px;color:var(--mute)">تجاهل</button>
                                </div>
                            </div>
                            {{-- Matched segments: which parts of the scope match --}}
                            <template x-if="m.matched_segments && m.matched_segments.length > 0">
                                <div style="margin-top:8px;padding-top:8px;border-top:1px solid var(--borderl)">
                                    <div style="font-size:11px;color:var(--gold);font-weight:700;margin-bottom:6px">أجزاء النطاق المتطابقة:</div>
                                    <template x-for="seg in m.matched_segments.slice(0,5)" :key="seg.segment">
                                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;font-size:11px">
                                            <div style="min-width:40px;text-align:center;font-weight:900;font-size:12px" :style="'color:' + scoreColor(seg.score)" x-text="Math.round(seg.score) + '%'"></div>
                                            <div style="flex:1;color:var(--dim);background:var(--card2);padding:4px 8px;border-radius:6px;direction:rtl" x-text="seg.segment"></div>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </template>
        </div>

        <div class="mz-doc-layout">
            {{-- Sidebar: section index --}}
            <aside class="mz-doc-index">
                <div class="mz-card" style="position:sticky;top:16px">
                    <div class="mz-card-head">
                        <div class="mz-card-title" style="font-size:13px">📑 الأقسام</div>
                    </div>
                    <div class="mz-card-body" style="padding:6px;max-height:75vh;overflow-y:auto">
                        @foreach ($tender->sections as $i => $section)
                            <a href="#section-{{ $section->id }}" class="mz-index-item">
                                <span class="mz-index-num">{{ $i + 1 }}</span>
                                <span class="mz-index-label">{{ $section->title }}</span>
                                @if ($section->is_edited)
                                    <span style="font-size:10px;color:var(--gold)" title="معدّل">✎</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </aside>

            <div class="mz-doc-main">
                @foreach ($tender->sections as $section)
                    <div id="section-{{ $section->id }}" class="mz-card" x-data="{ editing: false, content: @js($section->content ?? '') }">
                        <div class="mz-card-head" style="display:flex;justify-content:space-between;align-items:center">
                            <div class="mz-card-title">{{ $section->title }}</div>
                            <div style="display:flex;gap:6px">
                                <button type="button" @click="editing = !editing" class="mz-btn mz-btn-ghost mz-btn-sm">
                                    <span x-text="editing ? '✕ إلغاء' : '✏ تعديل'"></span>
                                </button>
                                <button type="button" x-show="editing"
                                        @click="saveSection({{ $section->id }}, content)"
                                        class="mz-btn mz-btn-gold mz-btn-sm">💾 حفظ</button>
                            </div>
                        </div>
                        <div class="mz-card-body">
                            <div x-show="!editing" style="font-size:13px;color:var(--cream);line-height:1.9;white-space:pre-wrap">{{ $section->content }}</div>
                            <textarea x-show="editing" x-model="content" rows="12" class="mz-inp mz-textarea" style="font-family:'Tajawal',sans-serif;font-size:13px;line-height:1.8"></textarea>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        async function saveSection(sectionId, content) {
            try {
                const resp = await fetch('/tenders/{{ $tender->id }}/sections/' + sectionId, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ content }),
                });
                if (resp.ok) {
                    location.reload();
                } else {
                    alert('فشل الحفظ');
                }
            } catch (e) {
                alert('تعذّر الاتصال');
            }
        }
        window.saveSection = saveSection;

        function similarityPanel() {
            return {
                topAlert: null,
                matches: [],
                showPanel: false,
                async loadResults() {
                    try {
                        const resp = await fetch('/api/v1/tenders/{{ $tender->id }}/similarity/results', {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!resp.ok) return;
                        const data = await resp.json();
                        this.topAlert = data.top_alert;
                        this.matches = data.matches || [];
                        if (this.topAlert && (this.topAlert.severity === 'critical' || this.topAlert.severity === 'high')) {
                            this.showPanel = true;
                        }
                    } catch (e) {}
                },
                alertStyle() {
                    if (!this.topAlert) return '';
                    const colors = {
                        critical: 'background:rgba(220,60,60,.12);border:1px solid rgba(220,60,60,.4)',
                        high: 'background:rgba(230,150,0,.12);border:1px solid rgba(230,150,0,.4)',
                        medium: 'background:rgba(200,169,75,.08);border:1px solid rgba(200,169,75,.3)',
                        low: 'background:var(--card2);border:1px solid var(--borderl)',
                    };
                    return colors[this.topAlert.severity] || colors.low;
                },
                alertIcon() {
                    if (!this.topAlert) return '';
                    return { critical: '🔴', high: '🟠', medium: '🟡', low: '🔵' }[this.topAlert.severity] || '🔵';
                },
                scoreColor(score) {
                    if (score >= 95) return '#e55';
                    if (score >= 80) return '#e90';
                    if (score >= 65) return 'var(--gold)';
                    return 'var(--mute)';
                },
                async ignoreTender(match) {
                    const reason = prompt('أدخل سبب تجاهل هذا التنبيه:');
                    if (!reason || reason.length < 5) { alert('السبب مطلوب (5 أحرف على الأقل)'); return; }
                    try {
                        const resp = await fetch('/api/v1/tenders/{{ $tender->id }}/similarity/ignore', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                            body: JSON.stringify({ matched_tender_id: match.tender_id, ignore_reason: reason }),
                        });
                        if (resp.ok) {
                            this.matches = this.matches.filter(m => m.tender_id !== match.tender_id);
                            if (this.matches.length === 0) this.topAlert = null;
                        }
                    } catch (e) {}
                },
            };
        }
    </script>
</x-app-layout>
