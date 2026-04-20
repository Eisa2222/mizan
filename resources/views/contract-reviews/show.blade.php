<x-app-layout>
    @php
        // $annotations, $discussions, $aiConfigured come from
        // ContractReviewController::show() via ContractReviewShowContextQuery.
        $hasAnalysis = is_array($document->analysis) && !empty($document->analysis);
        $analysisStatus = $document->metadata['analysis_status'] ?? null;
        $analysis = $document->analysis ?? [];
        $isPdfFile = $document->file_path ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) === 'pdf' : false;

        // Severity statistics + unified findings list
        $risks = $analysis['risks'] ?? [];
        $compliance = $analysis['compliance_issues'] ?? [];
        $amendments = $analysis['recommended_amendments'] ?? [];
        $protections = $analysis['missing_protections'] ?? [];

        $severityBuckets = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        foreach ($risks as $risk) {
            $sev = strtolower($risk['severity'] ?? 'medium');
            $sev = in_array($sev, ['critical', 'high', 'medium', 'low'], true) ? $sev : 'medium';
            $severityBuckets[$sev]++;
        }
        $severityBuckets['critical'] += count($compliance);
        $totalFindings = array_sum($severityBuckets) + count($amendments) + count($protections);

        $severityMeta = [
            'critical' => ['label' => 'حرجة', 'icon' => '🔴', 'color' => '#e55'],
            'high'     => ['label' => 'عالية', 'icon' => '🟠', 'color' => '#e78842'],
            'medium'   => ['label' => 'متوسطة', 'icon' => '🟡', 'color' => '#c8a94b'],
            'low'      => ['label' => 'تحسينية', 'icon' => '🟢', 'color' => '#3dbf8a'],
        ];
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $document->title }}</div>
                <div class="mz-page-sub">📝 مراجعة عقد · {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at->diffForHumans() }}</div>
            </div>
            <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm"><span class="mz-back-arrow">←</span> العودة للعقود</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;max-width:960px;margin:0 auto">
            {{-- Main column (full width — annotations are inline on the text) --}}
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                {{-- Analysis result --}}
                <div class="mz-card" x-data="{ tab: 'review', filter: 'all' }">
                    <div class="mz-tabs">
                        <button type="button" :class="tab==='review' ? 'mz-tab active' : 'mz-tab'" @click="tab='review'">📝 نتائج المراجعة @if($totalFindings > 0)<span class="mz-tab-count">{{ $totalFindings }}</span>@endif</button>
                        <button type="button" :class="tab==='content' ? 'mz-tab active' : 'mz-tab'" @click="tab='content'">📄 نص العقد</button>
                        @if ($isPdfFile)
                            <button type="button" :class="tab==='pdf' ? 'mz-tab active' : 'mz-tab'" @click="tab='pdf'">📄 أصل الوثيقة</button>
                        @endif
                        <button type="button" :class="tab==='ai' ? 'mz-tab active' : 'mz-tab'" @click="tab='ai'">🤖 المساعد</button>
                    </div>

                    {{-- Tab: Review results --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='review'" x-cloak>
                        @if (!$aiConfigured)
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⚖ التحليل غير متاح</div><div class="mz-notice-body">ميزة AI غير متاحة.</div></div>
                        @elseif ($analysisStatus === 'failed')
                            <div class="mz-notice mz-notice-error"><div class="mz-notice-title">⚠ فشل التحليل</div><div class="mz-notice-body">{{ $document->metadata['analysis_error'] ?? 'خطأ غير معروف.' }}</div></div>
                        @elseif (!$hasAnalysis)
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⏳ جاري المراجعة</div><div class="mz-notice-body">يجري النظام مراجعة العقد. حدّث الصفحة بعد دقيقة.</div></div>
                        @else
                            {{-- Executive summary: rating + severity stats --}}
                            @if(!empty($analysis['summary']) || !empty($analysis['overall_rating']))
                                <div class="mz-review-hero">
                                    @if(!empty($analysis['overall_rating']))
                                        <div class="mz-review-hero-rating">
                                            <span class="mz-review-hero-label">التقييم العام</span>
                                            <span class="mz-review-hero-value">{{ $analysis['overall_rating'] }}</span>
                                        </div>
                                    @endif
                                    @if(!empty($analysis['summary']))
                                        <p class="mz-review-hero-summary">{{ $analysis['summary'] }}</p>
                                    @endif
                                    @if(!empty($analysis['overall_notes']))
                                        <p class="mz-review-hero-notes">{{ $analysis['overall_notes'] }}</p>
                                    @endif
                                </div>
                            @endif

                            {{-- Severity stats bar --}}
                            @if($totalFindings > 0)
                                <div class="mz-sev-stats">
                                    @foreach(['critical', 'high', 'medium', 'low'] as $sevKey)
                                        @php $meta = $severityMeta[$sevKey]; $count = $severityBuckets[$sevKey]; @endphp
                                        <button type="button"
                                                @click="filter = filter === '{{ $sevKey }}' ? 'all' : '{{ $sevKey }}'"
                                                :class="filter === '{{ $sevKey }}' ? 'mz-sev-stat active' : 'mz-sev-stat'"
                                                style="--sev-color:{{ $meta['color'] }}"
                                                @if($count === 0)disabled @endif>
                                            <span class="mz-sev-stat-icon">{{ $meta['icon'] }}</span>
                                            <span class="mz-sev-stat-count">{{ $count }}</span>
                                            <span class="mz-sev-stat-label">{{ $meta['label'] }}</span>
                                        </button>
                                    @endforeach
                                    <button type="button"
                                            @click="filter = 'all'"
                                            :class="filter === 'all' ? 'mz-sev-stat active' : 'mz-sev-stat'"
                                            style="--sev-color:var(--gold)">
                                        <span class="mz-sev-stat-count">{{ $totalFindings }}</span>
                                        <span class="mz-sev-stat-label">الكل</span>
                                    </button>
                                </div>
                            @endif

                            {{-- Findings: risks + compliance (critical class) unified with anchor numbering --}}
                            @if(!empty($risks) || !empty($compliance))
                                <div class="mz-findings-section">
                                    <div class="mz-section-label mz-findings-section-label">الملاحظات والمخاطر</div>

                                    @php $findingIndex = 0; @endphp
                                    @foreach($risks as $risk)
                                        @php
                                            $findingIndex++;
                                            $sev = strtolower($risk['severity'] ?? 'medium');
                                            $sev = in_array($sev, ['critical', 'high', 'medium', 'low'], true) ? $sev : 'medium';
                                            $meta = $severityMeta[$sev];
                                            $sourceQuote = $risk['source_quote'] ?? $risk['clause'] ?? null;
                                        @endphp
                                        <article class="mz-finding-card" id="finding-{{ $findingIndex }}"
                                                 style="--sev-color:{{ $meta['color'] }}"
                                                 x-show="filter === 'all' || filter === '{{ $sev }}'">
                                            <header class="mz-finding-head">
                                                <span class="mz-finding-num">#{{ $findingIndex }}</span>
                                                <span class="mz-finding-sev">
                                                    <span class="mz-finding-sev-dot"></span>
                                                    {{ $meta['label'] }}
                                                </span>
                                                <h3 class="mz-finding-title">{{ $risk['description'] ?? '' }}</h3>
                                            </header>
                                            @if($sourceQuote)
                                                <div class="mz-source-quote">
                                                    <span class="mz-source-quote-label">نص العقد</span>
                                                    {{ $sourceQuote }}
                                                </div>
                                            @endif
                                            @if(!empty($risk['mitigation'] ?? $risk['suggested_change'] ?? ''))
                                                <div class="mz-finding-action">
                                                    <span class="mz-finding-action-label">💡 الحل المقترح</span>
                                                    {{ $risk['mitigation'] ?? $risk['suggested_change'] }}
                                                </div>
                                            @endif
                                            @if(!empty($risk['judicial_precedent']))
                                                <div class="mz-finding-meta">⚖ {{ $risk['judicial_precedent'] }}</div>
                                            @endif
                                        </article>
                                    @endforeach

                                    @foreach($compliance as $issue)
                                        @php
                                            $findingIndex++;
                                            $meta = $severityMeta['critical'];
                                        @endphp
                                        <article class="mz-finding-card" id="finding-{{ $findingIndex }}"
                                                 style="--sev-color:{{ $meta['color'] }}"
                                                 x-show="filter === 'all' || filter === 'critical'">
                                            <header class="mz-finding-head">
                                                <span class="mz-finding-num">#{{ $findingIndex }}</span>
                                                <span class="mz-finding-sev">
                                                    <span class="mz-finding-sev-dot"></span>
                                                    مخالفة نظامية
                                                </span>
                                                <h3 class="mz-finding-title">
                                                    {{ $issue['law'] ?? '' }}
                                                    @if(!empty($issue['article'])) — المادة {{ $issue['article'] }}@endif
                                                </h3>
                                            </header>
                                            @if(!empty($issue['issue']))
                                                <p class="mz-finding-body">{{ $issue['issue'] }}</p>
                                            @endif
                                            @if(!empty($issue['source_quote']))
                                                <div class="mz-source-quote">
                                                    <span class="mz-source-quote-label">نص العقد</span>
                                                    {{ $issue['source_quote'] }}
                                                </div>
                                            @endif
                                            @if(!empty($issue['recommendation']))
                                                <div class="mz-finding-action">
                                                    <span class="mz-finding-action-label">💡 التوصية</span>
                                                    {{ $issue['recommendation'] }}
                                                </div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($amendments))
                                <div class="mz-findings-section">
                                    <div class="mz-section-label mz-findings-section-label">✏ تعديلات مقترحة ({{ count($amendments) }})</div>
                                    @foreach($amendments as $amendment)
                                        <article class="mz-amendment-card">
                                            @if(!empty($amendment['current']))
                                                <div class="mz-source-quote mz-source-quote-strike">
                                                    <span class="mz-source-quote-label">النص الحالي</span>
                                                    {{ $amendment['current'] }}
                                                </div>
                                            @endif
                                            @if(!empty($amendment['suggested']))
                                                <div class="mz-suggested-quote">
                                                    <span class="mz-suggested-quote-label">النص المقترح</span>
                                                    {{ $amendment['suggested'] }}
                                                </div>
                                            @endif
                                            @if(!empty($amendment['reason']))
                                                <div class="mz-finding-meta">💡 {{ $amendment['reason'] }}</div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($protections))
                                <div class="mz-findings-section">
                                    <div class="mz-section-label mz-findings-section-label">🛡 بنود حماية مفقودة ({{ count($protections) }})</div>
                                    @foreach($protections as $protection)
                                        <article class="mz-protection-card">
                                            <div class="mz-protection-issue">⚠ {{ is_array($protection) ? ($protection['issue'] ?? '') : $protection }}</div>
                                            @if(is_array($protection) && !empty($protection['related_clause']))
                                                <div class="mz-source-quote">
                                                    <span class="mz-source-quote-label">البند المرتبط من العقد</span>
                                                    {{ $protection['related_clause'] }}
                                                </div>
                                            @endif
                                        </article>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($analysis['parties']))
                                <div class="mz-findings-section">
                                    <div class="mz-section-label mz-findings-section-label">👥 الأطراف</div>
                                    <ul class="mz-parties-list">
                                        @foreach($analysis['parties'] as $party)
                                            <li>
                                                @if(is_array($party))
                                                    <strong>{{ $party['name'] ?? '' }}</strong>
                                                    @if(!empty($party['role']))<span style="color:var(--mute)"> — {{ $party['role'] }}</span>@endif
                                                @else
                                                    {{ $party }}
                                                @endif
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- Tab: Contract text --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='content'" x-cloak>
                        @if($document->content)
                            <div style="font-family:'Amiri',serif;font-size:15px;color:var(--cream);line-height:2.1;white-space:pre-wrap">{{ $document->content }}</div>
                        @else
                            <p style="text-align:center;color:var(--mute);padding:32px">لا يوجد نص مستخرج من العقد.</p>
                        @endif
                    </div>

                    {{-- Tab: PDF --}}
                    @if($isPdfFile)
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
                        @if(!$aiConfigured)
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">🤖 المساعد غير متاح</div><div class="mz-notice-body">ميزة AI غير متاحة.</div></div>
                        @else
                            <div id="ai-chat-panel">
                                <div id="ai-messages" class="mz-ai-messages"><div style="text-align:center;color:var(--mute);padding:24px;font-size:13px">اسأل أي سؤال عن هذا العقد.</div></div>
                                <div class="mz-ai-input">
                                    <textarea id="ai-input" rows="2" placeholder="اسأل عن بند معين أو مخاطرة..." class="mz-inp mz-textarea"></textarea>
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
                        @forelse($discussions as $disc)
                            <div style="padding:12px 14px;border-bottom:1px solid var(--borderl)">
                                <div style="font-size:13px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $disc->title }}</div>
                                <div style="font-size:11px;color:var(--mute)">{{ $disc->user?->name }} · {{ $disc->created_at->diffForHumans() }}</div>
                            </div>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد نقاشات بعد. حدّد نصاً لبدء نقاش.</p>
                        @endforelse
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Selection toolbar + modals (reuse from annotations system) --}}
    @include('partials.selection-toolbar', ['document' => $document, 'annotations' => $annotations])

    <script>
        const docTitle = @json($document->title);
        const docId = @json($document->id);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let lastSelection = '';
        let aiConversationId = null;

        // AI chat (same logic as documents show)
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
</x-app-layout>
