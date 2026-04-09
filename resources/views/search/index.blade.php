<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">البحث القانوني الذكي</div>
                <div class="mz-page-sub">بحث في النصوص الكاملة مع التطبيع العربي وتمييز الكلمات</div>
            </div>
        </div>

        {{-- Hero search box --}}
        <div class="mz-search-hero">
            <form id="search-form" onsubmit="event.preventDefault(); runSearch();">
                <div class="mz-search-box">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/>
                        <path d="m21 21-4.35-4.35"/>
                    </svg>
                    <input id="q" name="q" placeholder="ابحث... مثال: شروط إنهاء عقد العمل، مكافأة نهاية الخدمة" autocomplete="off">
                    <button type="submit" class="mz-search-go">🔍 بحث</button>
                </div>

                <div class="mz-search-tags" id="type-tags">
                    <button type="button" class="mz-tag active" data-type="">الكل</button>
                    @foreach (\App\Models\LegalDocument::TYPES as $id => $label)
                        <button type="button" class="mz-tag" data-type="{{ $id }}">{{ $label }}</button>
                    @endforeach
                </div>

                <div style="display:flex;gap:14px;margin-top:14px;flex-wrap:wrap;align-items:flex-end">
                    <div style="flex:1;min-width:160px">
                        <label class="mz-flabel">من تاريخ</label>
                        <input type="date" id="from" class="mz-inp">
                    </div>
                    <div style="flex:1;min-width:160px">
                        <label class="mz-flabel">إلى تاريخ</label>
                        <input type="date" id="to" class="mz-inp">
                    </div>
                    <div>
                        <label class="mz-toggle">
                            <input type="checkbox" id="ai-toggle">
                            <span>🤖 بحث ذكي</span>
                        </label>
                    </div>
                </div>
            </form>
        </div>

        {{-- Results area --}}
        <div id="results-stats" class="mz-search-stats" style="display:none">
            <span id="stats-text"></span>
            <span id="engine-pill" class="mz-search-engine-pill"></span>
        </div>

        <div id="results"></div>
    </div>

    <script>
        const $q = document.getElementById('q');
        const $from = document.getElementById('from');
        const $to = document.getElementById('to');
        const $aiToggle = document.getElementById('ai-toggle');
        const $results = document.getElementById('results');
        const $stats = document.getElementById('results-stats');
        const $statsText = document.getElementById('stats-text');
        const $enginePill = document.getElementById('engine-pill');
        const $tags = document.querySelectorAll('#type-tags .mz-tag');

        // Re-run when toggling AI mode if there's an active query
        $aiToggle.addEventListener('change', () => {
            if ($q.value.trim().length >= 2) runSearch();
        });

        let activeType = '';
        $tags.forEach(t => t.addEventListener('click', () => {
            $tags.forEach(x => x.classList.remove('active'));
            t.classList.add('active');
            activeType = t.dataset.type;
            if ($q.value.trim()) runSearch();
        }));

        // Auto-search when user types (debounced)
        let timer = null;
        $q.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => {
                if ($q.value.trim().length >= 2) runSearch();
            }, 400);
        });

        async function runSearch() {
            const q = $q.value.trim();
            if (q.length < 2) return;

            // Loading state
            $stats.style.display = 'none';
            $results.innerHTML = `
                <div class="mz-search-loading">
                    <div class="mz-search-loading-icon">🔍</div>
                    <div style="margin-top:12px;font-size:13px">جارٍ البحث...</div>
                </div>`;

            const params = new URLSearchParams({ q });
            if (activeType) params.set('type', activeType);
            if ($from.value) params.set('from', $from.value);
            if ($to.value)   params.set('to', $to.value);
            if ($aiToggle.checked) params.set('ai', '1');

            try {
                const res = await fetch('/api/v1/search?' + params, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                });

                if (!res.ok) throw new Error('HTTP ' + res.status);
                const data = await res.json();
                renderResults(data);
            } catch (err) {
                $results.innerHTML = `
                    <div class="mz-search-empty">
                        <div style="font-size:48px">⚠️</div>
                        <div style="margin-top:12px">فشل البحث: ${err.message}</div>
                    </div>`;
            }
        }

        function renderResults(data) {
            // Stats
            $stats.style.display = 'flex';
            const tookText = data.took ? ` · ${data.took} ms` : '';
            $statsText.textContent = `${data.total.toLocaleString('ar-SA')} نتيجة لـ "${data.query}"${tookText}`;
            $enginePill.className = 'mz-search-engine-pill ' + data.engine;
            const engineLabels = {
                elasticsearch: '⚡ Elasticsearch',
                database:      '💾 Database',
                semantic:      '🤖 بحث ذكي',
            };
            $enginePill.textContent = engineLabels[data.engine] || data.engine;
            if (data.engine_note) {
                $enginePill.title = data.engine_note;
            }

            // Empty state
            if (!data.hits || data.hits.length === 0) {
                $results.innerHTML = `
                    <div class="mz-search-empty">
                        <div style="font-size:48px">📭</div>
                        <div style="margin-top:12px">لا توجد نتائج مطابقة</div>
                        <div style="font-size:11px;margin-top:6px">جرّب كلمات مختلفة أو وسّع الفلاتر</div>
                    </div>`;
                return;
            }

            // Results list — semantic engine adds ai_score and ai_reason fields
            const html = data.hits.map(hit => {
                const aiScore = hit.ai_score != null ? `<span class="mz-sr-score">🤖 ${hit.ai_score}</span>` : '';
                const reason = hit.ai_reason ? `<div class="mz-sr-reason">💡 ${escapeHtml(hit.ai_reason)}</div>` : '';
                return `
                    <a href="/documents/${hit.document_id}" class="mz-search-result">
                        <div class="mz-sr-head">
                            <div>
                                <div class="mz-sr-title">${escapeHtml(hit.title)}</div>
                                ${hit.label ? `<span class="mz-sr-label">${escapeHtml(hit.label)}</span>` : ''}
                            </div>
                            <span class="mz-badge mz-badge-gold">${typeLabel(hit.type)}</span>
                        </div>
                        <div class="mz-sr-snippet">${hit.snippet || ''}</div>
                        ${reason}
                        <div class="mz-sr-meta">
                            <span>📄 المستند #${hit.document_id}</span>
                            <span>· جزء ${hit.chunk_index + 1}</span>
                            ${aiScore || `<span class="mz-sr-score">${(hit.score || 0).toFixed(2)}</span>`}
                        </div>
                    </a>
                `;
            }).join('');

            $results.innerHTML = `<div style="display:flex;flex-direction:column;gap:12px">${html}</div>`;
        }

        const TYPE_LABELS = @json(\App\Models\LegalDocument::TYPES);
        function typeLabel(t) { return TYPE_LABELS[t] || '—'; }

        function escapeHtml(s) {
            const div = document.createElement('div');
            div.textContent = s ?? '';
            return div.innerHTML;
        }

        // Auto-run if query was passed via URL (?q=...)
        const initialQ = new URLSearchParams(location.search).get('q');
        if (initialQ) { $q.value = initialQ; runSearch(); }
        $q.focus();
    </script>

    <style>
        .mz-toggle {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 8px 14px;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 8px;
            font-size: 13px;
            color: var(--cream);
            font-weight: 600;
            transition: all .15s;
        }
        .mz-toggle:hover { border-color: var(--gold); }
        .mz-toggle input { cursor: pointer; }
        .mz-toggle input:checked + span { color: var(--gold); }
        .mz-search-engine-pill.semantic {
            background: rgba(200,169,75,.12);
            color: var(--gold);
            border-color: var(--gold);
        }
        .mz-sr-reason {
            margin-top: 8px;
            padding: 8px 12px;
            background: rgba(200,169,75,.06);
            border-right: 3px solid var(--gold);
            border-radius: 6px;
            font-size: 12px;
            color: var(--cream);
            line-height: 1.6;
        }
    </style>
</x-app-layout>
