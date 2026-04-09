<x-app-layout>
    @php
        $annotations = \App\Models\Annotation::with('user')
            ->where('document_id', $document->id)
            ->visibleTo(auth()->user())
            ->latest()->get();
        $discussions = \App\Models\Discussion::with(['user', 'replies'])
            ->where('document_id', $document->id)
            ->visibleTo(auth()->user())
            ->latest()->get();
        $aiConfigured = app(\App\Services\ClaudeService::class)->isConfigured();
        $hasAnalysis = is_array($document->analysis) && !empty($document->analysis);
        $analysisStatus = $document->metadata['analysis_status'] ?? null;
        $a = $document->analysis ?? [];
        $isPdfFile = $document->file_path ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) === 'pdf' : false;
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $document->title }}</div>
                <div class="mz-page-sub">📝 مراجعة عقد · {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at->diffForHumans() }}</div>
            </div>
            <a href="{{ route('contract-reviews.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة للعقود</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;max-width:960px;margin:0 auto">
            {{-- Main column (full width — annotations are inline on the text) --}}
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                {{-- Analysis result --}}
                <div class="mz-card" x-data="{ tab: 'review' }">
                    <div class="mz-tabs">
                        <button type="button" :class="tab==='review' ? 'mz-tab active' : 'mz-tab'" @click="tab='review'">📝 نتائج المراجعة</button>
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
                            @if(!empty($a['summary']))<div style="margin-bottom:18px"><div class="mz-section-label">الملخص</div><p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p></div>@endif

                            @if(!empty($a['overall_rating']))
                                <div style="margin-bottom:18px;padding:12px 16px;background:rgba(200,169,75,.06);border-right:4px solid var(--gold);border-radius:8px">
                                    <span style="font-size:15px;font-weight:700;color:var(--gold)">التقييم العام: {{ $a['overall_rating'] }}</span>
                                    @if(!empty($a['overall_notes']))<div style="font-size:13px;color:var(--cream);margin-top:6px;line-height:1.7">{{ $a['overall_notes'] }}</div>@endif
                                </div>
                            @endif

                            @if(!empty($a['risks']))
                                <div style="margin-bottom:18px"><div class="mz-section-label" style="color:#e05555">المخاطر ({{ count($a['risks']) }})</div>
                                    @foreach($a['risks'] as $r)
                                        @php $sev=$r['severity']??'medium'; $sc=match($sev){'high','critical'=>'mz-risk-high','low'=>'mz-risk-low',default=>'mz-risk-med'}; $sl=match($sev){'high'=>'عالية','critical'=>'حرجة','low'=>'منخفضة',default=>'متوسطة'}; @endphp
                                        <div class="mz-risk-card {{ $sc }}">
                                            <div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="font-size:12px;font-weight:700;color:var(--cream)">{{ $r['clause']??$r['description']??'' }}</span><span class="mz-risk-badge">{{ $sl }}</span></div>
                                            @if(!empty($r['description'])&&!empty($r['clause']))<div style="font-size:12px;color:var(--dim);line-height:1.7;margin-bottom:4px">{{ $r['description'] }}</div>@endif
                                            @if(!empty($r['mitigation']??$r['suggested_change']??''))<div style="font-size:11px;color:var(--gold)">💡 {{ $r['mitigation']??$r['suggested_change'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($a['compliance_issues']))
                                <div style="margin-bottom:18px"><div class="mz-section-label" style="color:#e05555">مخالفات أنظمة ({{ count($a['compliance_issues']) }})</div>
                                    @foreach($a['compliance_issues'] as $ci)
                                        <div class="mz-risk-card mz-risk-high">
                                            <div style="font-size:12px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $ci['law']??'' }} {{ !empty($ci['article'])?'— المادة '.$ci['article']:'' }}</div>
                                            <div style="font-size:12px;color:var(--dim);line-height:1.7">{{ $ci['issue']??'' }}</div>
                                            @if(!empty($ci['recommendation']))<div style="font-size:11px;color:var(--gold);margin-top:4px">💡 {{ $ci['recommendation'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($a['recommended_amendments']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">تعديلات مقترحة ({{ count($a['recommended_amendments']) }})</div>
                                    @foreach($a['recommended_amendments'] as $am)
                                        <div style="background:var(--card2);border:1px solid var(--borderl);border-radius:8px;padding:10px 14px;margin-bottom:8px">
                                            <div style="font-size:11px;color:var(--red);text-decoration:line-through;margin-bottom:4px">{{ $am['current']??'' }}</div>
                                            <div style="font-size:12px;color:#3dbf8a;margin-bottom:4px">{{ $am['suggested']??'' }}</div>
                                            <div style="font-size:11px;color:var(--mute)">💡 {{ $am['reason']??'' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($a['missing_protections']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">بنود حماية مفقودة</div>
                                    <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['missing_protections'] as $mp)<li>⚠ {{ $mp }}</li>@endforeach</ul>
                                </div>
                            @endif

                            @if(!empty($a['parties']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">الأطراف</div>
                                    <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                        @foreach($a['parties'] as $p)
                                            <li>@if(is_array($p)){{ $p['name']??'' }} — {{ $p['role']??'' }}@else{{ $p }}@endif</li>
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
