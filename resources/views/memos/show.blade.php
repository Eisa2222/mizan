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
                <div class="mz-page-sub">📄 مسودة مذكرة · {{ $document->uploader?->name ?? '—' }} · {{ $document->created_at->diffForHumans() }}</div>
            </div>
            <a href="{{ route('memos.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة للمذكرات</a>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;max-width:960px;margin:0 auto">
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                <div class="mz-card" x-data="{ tab: 'analysis' }">
                    <div class="mz-tabs">
                        <button type="button" :class="tab==='analysis' ? 'mz-tab active' : 'mz-tab'" @click="tab='analysis'">📄 نتائج التحليل</button>
                        <button type="button" :class="tab==='content' ? 'mz-tab active' : 'mz-tab'" @click="tab='content'">📝 نص المذكرة</button>
                        @if($isPdfFile)
                            <button type="button" :class="tab==='pdf' ? 'mz-tab active' : 'mz-tab'" @click="tab='pdf'">📄 أصل الوثيقة</button>
                        @endif
                        <button type="button" :class="tab==='ai' ? 'mz-tab active' : 'mz-tab'" @click="tab='ai'">🤖 المساعد</button>
                    </div>

                    {{-- Tab: Analysis --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='analysis'" x-cloak>
                        @if(!$aiConfigured)
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⚖ غير متاح</div><div class="mz-notice-body">ميزة AI غير متاحة.</div></div>
                        @elseif($analysisStatus === 'failed')
                            <div class="mz-notice mz-notice-error"><div class="mz-notice-title">⚠ فشل التحليل</div><div class="mz-notice-body">{{ $document->metadata['analysis_error'] ?? 'خطأ.' }}</div></div>
                        @elseif(!$hasAnalysis)
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">⏳ جاري التحليل</div><div class="mz-notice-body">حدّث الصفحة بعد دقيقة.</div></div>
                        @else
                            @if(!empty($a['summary']))<div style="margin-bottom:18px"><div class="mz-section-label">الملخص</div><p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p></div>@endif

                            @if(!empty($a['overall_assessment'])||!empty($a['persuasiveness_score']))
                                <div style="margin-bottom:18px;padding:12px 16px;background:rgba(200,169,75,.06);border-right:4px solid var(--gold);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                                    <span style="font-size:15px;font-weight:700;color:var(--gold)">التقييم: {{ $a['overall_assessment']??'—' }}</span>
                                    @if(!empty($a['persuasiveness_score']))<span style="font-size:14px;color:var(--cream)">قوة الإقناع: <strong>{{ $a['persuasiveness_score'] }}</strong>/10</span>@endif
                                </div>
                            @endif

                            @if(!empty($a['legal_arguments']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">الحجج القانونية</div>
                                    @foreach($a['legal_arguments'] as $arg)
                                        @php $str=$arg['strength']??'متوسطة'; $c=match($str){'قوية'=>'#3dbf8a','ضعيفة'=>'#e05555',default=>'#c8a94b'}; @endphp
                                        <div style="background:var(--card2);border-right:3px solid {{ $c }};border-radius:8px;padding:10px 12px;margin-bottom:6px">
                                            <div style="display:flex;justify-content:space-between"><span style="font-size:12px;color:var(--cream);font-weight:600">{{ $arg['argument']??'' }}</span><span style="font-size:10px;color:{{ $c }};font-weight:700">{{ $str }}</span></div>
                                            @if(!empty($arg['note']))<div style="font-size:11px;color:var(--mute);margin-top:4px">{{ $arg['note'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($a['weak_points']))
                                <div style="margin-bottom:18px"><div class="mz-section-label" style="color:#e05555">نقاط الضعف</div>
                                    @foreach($a['weak_points'] as $wp)
                                        <div class="mz-risk-card mz-risk-med"><div style="font-size:12px;color:var(--cream);margin-bottom:4px">{{ $wp['point']??'' }}</div><div style="font-size:11px;color:var(--gold)">💡 {{ $wp['suggestion']??'' }}</div></div>
                                    @endforeach
                                </div>
                            @endif

                            @if(!empty($a['missing_references']))<div style="margin-bottom:18px"><div class="mz-section-label">مراجع مقترحة</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['missing_references'] as $mr)<li>📚 {{ $mr }}</li>@endforeach</ul></div>@endif
                            @if(!empty($a['key_recommendations']))<div style="margin-bottom:18px"><div class="mz-section-label" style="color:#3dbf8a">التوصيات</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['key_recommendations'] as $kr)<li>✓ {{ $kr }}</li>@endforeach</ul></div>@endif
                        @endif
                    </div>

                    {{-- Tab: Content --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='content'" x-cloak>
                        @if($document->content)
                            <div style="font-family:'Amiri',serif;font-size:15px;color:var(--cream);line-height:2.1;white-space:pre-wrap">{{ $document->content }}</div>
                        @else
                            <p style="text-align:center;color:var(--mute);padding:32px">لا يوجد نص.</p>
                        @endif
                    </div>

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
                            <div class="mz-notice mz-notice-warn"><div class="mz-notice-title">🤖 غير متاح</div><div class="mz-notice-body">ميزة AI غير متاحة.</div></div>
                        @else
                            <div id="ai-chat-panel">
                                <div id="ai-messages" class="mz-ai-messages"><div style="text-align:center;color:var(--mute);padding:24px;font-size:13px">اسأل عن المذكرة.</div></div>
                                <div class="mz-ai-input">
                                    <textarea id="ai-input" rows="2" placeholder="اسأل عن حجة أو مرجع..." class="mz-inp mz-textarea"></textarea>
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
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد نقاشات بعد.</p>
                        @endforelse
                    </div>
                </div>
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
</x-app-layout>
