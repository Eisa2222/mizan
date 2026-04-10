<x-app-layout>
    @php
        $aiConfigured = app(\App\Services\ClaudeService::class)->isConfigured();
        $hasAnalysis = is_array($document->analysis) && !empty($document->analysis);
        $analysisStatus = $document->metadata['analysis_status'] ?? null;
        $a = $document->analysis ?? [];
        $score = $a['compliance_score'] ?? null;
        $findings = $a['findings'] ?? [];
        $stats = $a['statistics'] ?? [];
        $isPdfFile = $document->file_path ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) === 'pdf' : false;

        // Group findings by category
        $byCategory = collect($findings)->groupBy('category');
        $bySeverity = collect($findings)->groupBy('severity');

        $sevColors = ['حرجة' => '#e05555', 'عالية' => '#ff8a4b', 'متوسطة' => '#c8a94b', 'تحسينية' => '#3dbf8a'];
        $catIcons = ['نظامي' => '📜', 'شكلي' => '📋', 'موضوعي' => '⚖', 'عدالة' => '🏛'];
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $document->title }}</div>
                <div class="mz-page-sub">
                    📋 مراجعة كراسة · {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at->diffForHumans() }}
                    @if ($document->metadata['tender_type'] ?? null) · {{ $document->metadata['tender_type'] }} @endif
                    @if ($document->metadata['sector'] ?? null) · {{ $document->metadata['sector'] }} @endif
                </div>
            </div>
            <a href="{{ route('tender-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة للكراسات</a>
        </div>

        @if (!$aiConfigured)
            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⚖ المراجعة غير متاحة</div><div class="mz-notice-body">ميزة AI غير متاحة.</div></div>
        @elseif ($analysisStatus === 'failed')
            <div class="mz-notice mz-notice-error"><div class="mz-notice-title">⚠ فشل المراجعة</div><div class="mz-notice-body">{{ $document->metadata['analysis_error'] ?? 'خطأ.' }}</div></div>
        @elseif (!$hasAnalysis)
            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⏳ جاري المراجعة</div><div class="mz-notice-body">يقوم النظام بفحص الكراسة. حدّث الصفحة بعد دقيقة.</div></div>
        @else
            {{-- ═══ Compliance Dashboard ═══ --}}
            <div style="display:grid;grid-template-columns:200px 1fr;gap:16px;margin-bottom:20px">
                {{-- Score circle --}}
                <div class="mz-card" style="display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px">
                    @php $scoreColor = $score >= 80 ? '#3dbf8a' : ($score >= 60 ? '#c8a94b' : '#e05555'); @endphp
                    <div style="width:100px;height:100px;border-radius:50%;border:6px solid {{ $scoreColor }};display:flex;align-items:center;justify-content:center;margin-bottom:12px">
                        <span style="font-size:28px;font-weight:900;color:{{ $scoreColor }}">{{ $score }}%</span>
                    </div>
                    <div style="font-size:14px;font-weight:700;color:var(--cream);margin-bottom:6px">نسبة الامتثال</div>
                    @if ($a['ready_for_tender'] ?? false)
                        <div style="font-size:12px;color:#3dbf8a;font-weight:700">✓ جاهزة للطرح</div>
                    @else
                        <div style="font-size:12px;color:#e05555;font-weight:700">⚠ تحتاج مراجعة</div>
                    @endif
                    @if ($a['needs_legal_review'] ?? false)
                        <div style="font-size:11px;color:#ff8a4b;margin-top:4px">⚖ تحتاج مراجعة قانونية</div>
                    @endif
                </div>

                {{-- Stats cards --}}
                <div class="mz-card" style="padding:20px">
                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:16px">
                        @foreach (['حرجة' => $stats['critical'] ?? 0, 'عالية' => $stats['high'] ?? 0, 'متوسطة' => $stats['medium'] ?? 0, 'تحسينية' => $stats['improvement'] ?? 0] as $label => $count)
                            <div style="text-align:center;padding:12px;background:var(--card2);border-radius:8px;border-bottom:3px solid {{ $sevColors[$label] }}">
                                <div style="font-size:24px;font-weight:900;color:{{ $sevColors[$label] }}">{{ $count }}</div>
                                <div style="font-size:11px;color:var(--mute);margin-top:4px">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>

                    @if (!empty($a['executive_summary']))
                        <div style="font-size:13px;color:var(--cream);line-height:1.8;padding:12px 14px;background:rgba(200,169,75,.06);border-right:3px solid var(--gold);border-radius:6px">
                            <strong>الملخص التنفيذي:</strong> {{ $a['executive_summary'] }}
                        </div>
                    @endif
                </div>
            </div>

            {{-- ═══ Critical items banner ═══ --}}
            @if (!empty($a['critical_items']))
                <div class="mz-card" style="border:1px solid rgba(224,85,85,.4);margin-bottom:16px">
                    <div class="mz-card-body" style="padding:14px 18px">
                        <div style="font-size:13px;font-weight:800;color:#ff8a8a;margin-bottom:8px">🚫 بنود حرجة يجب معالجتها قبل الطرح</div>
                        <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9;margin:0">
                            @foreach ($a['critical_items'] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    </div>
                </div>
            @endif
        @endif

        {{-- ═══ Tabs ═══ --}}
        <div class="mz-card" x-data="{ tab: '{{ $hasAnalysis ? 'findings' : 'content' }}', filterSev: 'all', filterCat: 'all' }">
            <div class="mz-tabs">
                @if ($hasAnalysis)
                    <button type="button" :class="tab==='findings' ? 'mz-tab active' : 'mz-tab'" @click="tab='findings'">📊 الملاحظات ({{ count($findings) }})</button>
                @endif
                <button type="button" :class="tab==='content' ? 'mz-tab active' : 'mz-tab'" @click="tab='content'">📄 نص الكراسة</button>
                @if ($isPdfFile)
                    <button type="button" :class="tab==='pdf' ? 'mz-tab active' : 'mz-tab'" @click="tab='pdf'">📄 أصل الوثيقة</button>
                @endif
                <button type="button" :class="tab==='ai' ? 'mz-tab active' : 'mz-tab'" @click="tab='ai'">🤖 المساعد</button>
            </div>

            {{-- Tab: Findings --}}
            @if ($hasAnalysis)
                <div class="mz-card-body mz-selectable" x-show="tab==='findings'" x-cloak>
                    {{-- Filters --}}
                    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
                        <button class="mz-chip" :class="filterSev==='all'&&'active'" @click="filterSev='all'">الكل</button>
                        @foreach (['حرجة','عالية','متوسطة','تحسينية'] as $sev)
                            <button class="mz-chip" :class="filterSev==='{{ $sev }}'&&'active'" @click="filterSev='{{ $sev }}'" style="border-color:{{ $sevColors[$sev] }}">{{ $sev }} ({{ ($bySeverity[$sev] ?? collect())->count() }})</button>
                        @endforeach
                        <span style="border-left:1px solid var(--borderl);margin:0 4px"></span>
                        @foreach (['نظامي','شكلي','موضوعي','عدالة'] as $cat)
                            <button class="mz-chip" :class="filterCat==='{{ $cat }}'&&'active'" @click="filterCat=filterCat==='{{ $cat }}'?'all':'{{ $cat }}'">{{ $catIcons[$cat] ?? '' }} {{ $cat }} ({{ ($byCategory[$cat] ?? collect())->count() }})</button>
                        @endforeach
                    </div>

                    {{-- Findings list --}}
                    @foreach ($findings as $i => $f)
                        @php
                            $sev = $f['severity'] ?? 'متوسطة';
                            $cat = $f['category'] ?? 'موضوعي';
                            $sevColor = $sevColors[$sev] ?? '#c8a94b';
                            $catIcon = $catIcons[$cat] ?? '📋';
                        @endphp
                        <div class="mz-finding"
                             x-show="(filterSev==='all'||filterSev==='{{ $sev }}')&&(filterCat==='all'||filterCat==='{{ $cat }}')"
                             style="border-right:4px solid {{ $sevColor }};background:var(--card2);border-radius:10px;padding:14px 18px;margin-bottom:10px">
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                                <div style="display:flex;align-items:center;gap:8px">
                                    <span style="font-size:14px">{{ $catIcon }}</span>
                                    <span style="font-size:13px;font-weight:700;color:var(--cream)">{{ $f['title'] ?? 'ملاحظة #' . ($i+1) }}</span>
                                </div>
                                <div style="display:flex;gap:6px">
                                    <span style="font-size:10px;font-weight:700;padding:3px 8px;border-radius:8px;background:rgba(0,0,0,.2);color:{{ $sevColor }}">{{ $sev }}</span>
                                    <span style="font-size:10px;padding:3px 8px;border-radius:8px;background:rgba(0,0,0,.2);color:var(--mute)">{{ $cat }}</span>
                                </div>
                            </div>

                            @if (!empty($f['current_text']))
                                <div style="font-size:12px;color:var(--dim);background:rgba(255,255,255,.03);padding:8px 10px;border-radius:6px;margin-bottom:8px;line-height:1.6;border-right:2px solid var(--borderl)">
                                    "{{ $f['current_text'] }}"
                                </div>
                            @endif

                            <div style="font-size:12px;color:var(--cream);line-height:1.7;margin-bottom:8px">{{ $f['issue'] ?? '' }}</div>

                            <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:6px">
                                @if (!empty($f['legal_reference']))
                                    <div style="font-size:11px;color:var(--gold)">📜 {{ $f['legal_reference'] }}</div>
                                @endif
                                @if (!empty($f['violation_type']))
                                    @php $vColor = str_contains($f['violation_type'], 'مؤكدة') ? '#e05555' : '#c8a94b'; @endphp
                                    <div style="font-size:10px;padding:2px 8px;border-radius:6px;background:rgba(0,0,0,.2);color:{{ $vColor }};font-weight:700">
                                        {{ $f['violation_type'] }}
                                    </div>
                                @endif
                            </div>

                            @if (!empty($f['recommendation']))
                                <div style="font-size:12px;color:#3dbf8a;background:rgba(61,191,138,.06);padding:8px 10px;border-radius:6px;line-height:1.6">
                                    💡 {{ $f['recommendation'] }}
                                </div>
                            @endif
                        </div>
                    @endforeach

                    {{-- Missing sections + restricted conditions --}}
                    @if (!empty($a['missing_sections']))
                        <div style="margin-top:18px;padding-top:16px;border-top:1px solid var(--borderl)">
                            <div class="mz-section-label" style="color:#e05555">أقسام ناقصة</div>
                            <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                @foreach ($a['missing_sections'] as $s)<li>⚠ {{ $s }}</li>@endforeach
                            </ul>
                        </div>
                    @endif

                    @if (!empty($a['restricted_conditions']))
                        <div style="margin-top:14px">
                            <div class="mz-section-label" style="color:#ff8a4b">شروط مقيّدة للمنافسة</div>
                            <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                @foreach ($a['restricted_conditions'] as $c)<li>🚫 {{ $c }}</li>@endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endif

            {{-- Tab: Content --}}
            <div class="mz-card-body mz-selectable" x-show="tab==='content'" x-cloak>
                @if ($document->content)
                    <div style="font-family:'Amiri',serif;font-size:15px;color:var(--cream);line-height:2.1;white-space:pre-wrap">{{ $document->content }}</div>
                @else
                    <p style="text-align:center;color:var(--mute);padding:32px">لا يوجد نص مستخرج.</p>
                @endif
            </div>

            {{-- Tab: PDF --}}
            @if ($isPdfFile)
                <div class="mz-card-body" x-show="tab==='pdf'" x-cloak>
                    <div style="display:flex;justify-content:flex-end;gap:8px;margin-bottom:12px">
                        <a href="{{ asset('storage/'.$document->file_path) }}" target="_blank" class="mz-btn mz-btn-ghost mz-btn-sm">🔗 نافذة جديدة</a>
                        <a href="{{ asset('storage/'.$document->file_path) }}" download class="mz-btn mz-btn-ghost mz-btn-sm">📥 تحميل</a>
                    </div>
                    <iframe src="{{ asset('storage/'.$document->file_path) }}" style="width:100%;height:80vh;border:1px solid var(--borderl);border-radius:8px;background:#fff"></iframe>
                </div>
            @endif

            {{-- Tab: AI --}}
            <div class="mz-card-body mz-selectable" x-show="tab==='ai'" x-cloak>
                @if (!$aiConfigured)
                    <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">🤖 غير متاح</div></div>
                @else
                    <div id="ai-chat-panel">
                        <div id="ai-messages" class="mz-ai-messages"><div style="text-align:center;color:var(--mute);padding:24px;font-size:13px">اسأل عن أي بند في الكراسة أو عن نظام المنافسات والمشتريات.</div></div>
                        <div class="mz-ai-input">
                            <textarea id="ai-input" rows="2" placeholder="مثال: هل معايير التقييم متوافقة مع النظام؟" class="mz-inp mz-textarea"></textarea>
                            <button type="button" id="ai-send-btn" class="mz-btn mz-btn-gold" onclick="sendAiMessage()">إرسال</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Discussions --}}
        <div class="mz-card">
            <div class="mz-card-head"><div class="mz-card-title">💬 النقاشات ({{ $discussions->count() }})</div></div>
            <div class="mz-card-body" style="padding:8px">
                @forelse ($discussions as $disc)
                    <div style="padding:12px 14px;border-bottom:1px solid var(--borderl)">
                        <div style="font-size:13px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $disc->title }}</div>
                        <div style="font-size:11px;color:var(--mute)">{{ $disc->user?->name }} · {{ $disc->created_at->diffForHumans() }}</div>
                    </div>
                @empty
                    <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد نقاشات. حدّد نصاً لبدء نقاش.</p>
                @endforelse
            </div>
        </div>
    </div>

    @include('partials.selection-toolbar', ['document' => $document, 'annotations' => $annotations])

    <script>
        const docTitle = @json($document->title);
        const docId = @json($document->id);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let lastSelection = '';
        let aiConversationId = null;

        async function ensureAiConversation() {
            if (aiConversationId) return aiConversationId;
            const resp = await fetch('/ai/conversations', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ document_id: docId }) });
            const data = await resp.json();
            aiConversationId = data.conversation?.id;
            return aiConversationId;
        }

        async function sendAiMessage() {
            const input = document.getElementById('ai-input');
            const text = input.value.trim();
            if (!text) return;
            const btn = document.getElementById('ai-send-btn');
            const messages = document.getElementById('ai-messages');
            input.value = ''; btn.disabled = true; btn.textContent = '⏳';
            if (messages.querySelector('div[style*="اسأل"]')) messages.innerHTML = '';
            messages.innerHTML += '<div class="mz-ai-msg mz-ai-msg-user"><div class="mz-ai-msg-role">أنت</div><div class="mz-ai-msg-body">' + text.replace(/</g,'&lt;') + '</div></div>';
            const ph = document.createElement('div'); ph.className = 'mz-ai-msg mz-ai-msg-assistant'; ph.innerHTML = '<div class="mz-ai-msg-role">المساعد</div><div class="mz-ai-msg-body">...</div>'; messages.appendChild(ph); messages.scrollTop = messages.scrollHeight;
            try {
                const convId = await ensureAiConversation();
                const resp = await fetch('/ai/conversations/' + convId + '/messages', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', body: JSON.stringify({ content: text }) });
                const data = await resp.json();
                ph.querySelector('.mz-ai-msg-body').textContent = resp.ok ? data.assistant_message.content : (data.error || 'خطأ');
                if (!resp.ok) ph.classList.add('mz-ai-msg-error');
            } catch(e) { ph.querySelector('.mz-ai-msg-body').textContent = 'تعذّر الاتصال'; ph.classList.add('mz-ai-msg-error'); }
            btn.disabled = false; btn.textContent = 'إرسال'; input.focus(); messages.scrollTop = messages.scrollHeight;
        }
        const aiInput = document.getElementById('ai-input');
        if (aiInput) aiInput.addEventListener('keydown', e => { if (e.key==='Enter'&&!e.shiftKey) { e.preventDefault(); sendAiMessage(); } });
    </script>

    <style>
        .mz-chip.active { background: var(--gold); color: var(--card); border-color: var(--gold); }
    </style>
</x-app-layout>
