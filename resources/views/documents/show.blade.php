<x-app-layout>
    @php
        // $isWatching, $annotations, $discussions are injected by
        // DocumentController::show() via DocumentShowContextQuery.
        // Already eager-loaded by DocumentController::show()
        $chunks = $document->chunks;
        $articleUpdates = $document->articleUpdates;
        $versions = $document->versions;
        $relationsFrom = $document->relationsFrom;
        $relationsTo = $document->relationsTo;

        // Build a per-article-label map of updates so we can show counters next to each section
        $updatesByLabel = $articleUpdates->groupBy('article_label');

        // Pre-serialize a flat structure for the JS-side modal so we don't need
        // closures inside the @json directive (which Blade's parser dislikes).
        $updatesForJs = $updatesByLabel->mapWithKeys(function ($group, $label) {
            return [$label => $group->map(function ($u) {
                return [
                    'date' => $u->update_date->format('Y-m-d'),
                    'body' => $u->body,
                    'decree_number' => $u->decree_number,
                    'decree_url' => $u->decree_url,
                    'auto' => (bool) $u->auto_generated,
                    'creator' => $u->creator?->name,
                ];
            })->values()];
        })->toArray();

        // OCR / extraction state
        $extractionStatus = $document->metadata['extraction_status'] ?? null;
        $extractionError = $document->metadata['extraction_error'] ?? null;
        $fileExt = $document->file_path ? strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION)) : null;
        $isImageFile = in_array($fileExt, ['jpg','jpeg','png','tiff','tif','webp','gif','bmp'], true);
        $isPdfFile = $fileExt === 'pdf';

        // Index sidebar: deduplicated entries (one per label, pointing to the
        // FIRST chunk with that label). Also filter out false-positive labels
        // like "المادة بناءً" which are amendment notes, not real article titles.
        $falseLabelPatterns = ['بناء', '؛'];
        $indexEntries = $chunks
            ->filter(fn($c) => !empty($c->label))
            ->filter(function ($c) use ($falseLabelPatterns) {
                foreach ($falseLabelPatterns as $pat) {
                    if (mb_strpos($c->label, $pat) !== false) return false;
                }
                return true;
            })
            ->unique('label')  // one entry per unique label, keeps the first occurrence
            ->values();

        // Distinct article labels (for the "add update" modal's <select>)
        $articleLabels = $indexEntries
            ->pluck('label')
            ->unique()
            ->values()
            ->toArray();

        // Permission flags rendered into the page so the modals/buttons
        // appear only for users who can actually use them.
        $canAddUpdate = auth()->user()?->can('createForDocument', [\App\Models\ArticleUpdate::class, $document]) ?? false;
        $canUploadVersion = auth()->user()?->can('update', $document) ?? false;
        // Anyone who can view this doc can also link a related one — they
        // just need view rights on both endpoints, enforced server-side.
        $canAddRelation = auth()->user()?->can('view', $document) ?? false;

        // AI feature flags — works for both Ollama (local) and Anthropic (cloud)
        $aiConfigured = app(\App\Services\ClaudeService::class)->isConfigured();
        $isContract = $document->kind === \App\Models\LegalDocument::KIND_CONTRACT;
        $isContractReview = $document->kind === \App\Models\LegalDocument::KIND_CONTRACT_REVIEW;
        $isCase = $document->kind === \App\Models\LegalDocument::KIND_CASE;
        $isMemo = $document->kind === \App\Models\LegalDocument::KIND_MEMO;
        $hasAnalysisTab = $isContract || $isContractReview || $isCase || $isMemo;
        $hasAnalysis = is_array($document->analysis) && ! empty($document->analysis);
        $analysisStatus = $document->metadata['analysis_status'] ?? null;
        $suggestedQuestions = $document->metadata['suggested_questions'] ?? [];
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $document->title }}</div>
                @if ($document->title_en)
                    <div class="mz-page-sub" dir="ltr" style="text-align:right">{{ $document->title_en }}</div>
                @endif
            </div>
            <div style="display:flex;gap:8px">
                <a href="{{ route('documents.read', $document) }}" class="mz-btn mz-btn-gold mz-btn-sm" title="عرض مخصّص للقراءة بنمط المواد (خط Cairo + تخطيط مشابه لبوابة هيئة الخبراء)">📖 عرض القراءة</a>
                <form method="POST" action="{{ route('watchlist.toggle', $document) }}">
                    @csrf
                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm">
                        @if ($isWatching) ✓ تتابعه @else 👁 متابعة @endif
                    </button>
                </form>
                <a href="{{ route('documents.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm"><span class="mz-back-arrow">←</span> العودة</a>
            </div>
        </div>

        {{-- ═══════ 3-column layout: [article index] [main+tabs] [annotations] ═══════ --}}
        <div class="mz-doc-layout">

            {{-- ───── Left column: article index sidebar ───── --}}
            <aside class="mz-doc-index">
                <div class="mz-card" style="position:sticky;top:16px">
                    <div class="mz-card-head" style="display:flex;justify-content:space-between;align-items:center">
                        <div class="mz-card-title" style="font-size:13px">📑 فهرس المواد</div>
                        <span class="mz-badge mz-badge-grey" style="font-size:10px">{{ $indexEntries->count() }}</span>
                    </div>
                    {{-- In-document search --}}
                    <div style="padding:6px 8px 0">
                        <input type="text" id="doc-search" class="mz-inp" placeholder="🔍 بحث داخل المستند..." style="font-size:12px;padding:6px 10px">
                        <div id="doc-search-stats" style="display:none;font-size:10px;color:var(--mute);padding:4px 4px 0;text-align:center"></div>
                    </div>
                    <div class="mz-card-body" style="padding:6px;max-height:65vh;overflow-y:auto" id="index-list">
                        @forelse ($indexEntries as $idx => $entry)
                            @php $upCount = ($updatesByLabel[$entry->label] ?? collect())->count(); @endphp
                            <a href="#chunk-{{ $entry->chunk_index }}"
                               class="mz-index-item"
                               data-target="chunk-{{ $entry->chunk_index }}">
                                <span class="mz-index-num">{{ $idx + 1 }}</span>
                                <span class="mz-index-label">{{ $entry->label }}</span>
                                @if ($upCount > 0)
                                    <span class="mz-index-badge" title="{{ $upCount }} تحديث">{{ $upCount }}</span>
                                @endif
                            </a>
                        @empty
                            <p style="text-align:center;padding:18px;color:var(--mute);font-size:12px">
                                @if ($document->content)
                                    لم يتم تقسيم المحتوى لمواد بعد.
                                @else
                                    لا يوجد محتوى لعرضه.
                                @endif
                            </p>
                        @endforelse
                    </div>
                </div>
            </aside>

            {{-- ───── Center column: tabs + content ───── --}}
            <div class="mz-doc-main">

                {{-- Document header card --}}
                <div class="mz-card">
                    <div class="mz-card-body">
                        <div style="display:flex;gap:10px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                            <span class="mz-badge mz-badge-gold">{{ $document->type_label }}</span>
                            @if ($document->kind !== 'document')
                                <span class="mz-badge mz-badge-grey">🏷 {{ $document->kind_label }}</span>
                            @endif
                            @if ($document->issued_at)<span class="mz-badge mz-badge-grey">📅 {{ $document->issued_at->format('Y-m-d') }}</span>@endif
                            @if ($document->reference_number)<span class="mz-badge mz-badge-grey">📋 {{ $document->reference_number }}</span>@endif
                            @if ($extractionStatus === 'extracted_via_ocr')
                                <span class="mz-badge mz-badge-grey" title="تم الاستخراج عبر OCR">🔍 OCR</span>
                            @endif
                        </div>

                        @if ($document->summary)
                            <div>
                                <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px">الملخص</div>
                                <p class="mz-selectable" style="font-size:14px;color:var(--cream);line-height:1.85;margin:0">{{ $document->summary }}</p>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- ═══════ Tabs ═══════ --}}
                <div class="mz-card" x-data="{ tab: window.location.hash.startsWith('#tab-') ? window.location.hash.slice(5) : 'content' }">
                    <div class="mz-tabs">
                        <button type="button"
                                :class="tab==='content' ? 'mz-tab active' : 'mz-tab'"
                                @click="tab='content'">
                            📄 المحتوى
                        </button>
                        @if ($document->file_path && $isPdfFile)
                            <button type="button"
                                    :class="tab==='pdf' ? 'mz-tab active' : 'mz-tab'"
                                    @click="tab='pdf'">
                                📄 أصل الوثيقة
                            </button>
                        @endif
                        <button type="button"
                                :class="tab==='source' ? 'mz-tab active' : 'mz-tab'"
                                @click="tab='source'">
                            🗂 معلومات الملف
                        </button>
                        <button type="button"
                                :class="tab==='related' ? 'mz-tab active' : 'mz-tab'"
                                @click="tab='related'">
                            🔗 ملفات ذات صلة
                            @if ($relationsFrom->count() + $relationsTo->count() > 0)
                                <span class="mz-tab-badge">{{ $relationsFrom->count() + $relationsTo->count() }}</span>
                            @endif
                        </button>
                        <button type="button"
                                :class="tab==='timeline' ? 'mz-tab active' : 'mz-tab'"
                                @click="tab='timeline'">
                            🕒 الخط الزمني
                            @if ($articleUpdates->count() + $versions->count() > 0)
                                <span class="mz-tab-badge">{{ $articleUpdates->count() + $versions->count() }}</span>
                            @endif
                        </button>
                        <button type="button"
                                :class="tab==='ai' ? 'mz-tab active' : 'mz-tab'"
                                @click="tab='ai'">
                            🤖 المساعد
                        </button>
                        @if ($isContract)
                            <button type="button" :class="tab==='analysis' ? 'mz-tab active' : 'mz-tab'" @click="tab='analysis'">📋 تحليل العقد</button>
                        @endif
                        @if ($isContractReview)
                            <button type="button" :class="tab==='review' ? 'mz-tab active' : 'mz-tab'" @click="tab='review'">📝 مراجعة العقد</button>
                        @endif
                        @if ($isCase)
                            <button type="button" :class="tab==='case' ? 'mz-tab active' : 'mz-tab'" @click="tab='case'">⚖ تحليل القضية</button>
                        @endif
                        @if ($isMemo)
                            <button type="button" :class="tab==='memo' ? 'mz-tab active' : 'mz-tab'" @click="tab='memo'">📄 تحليل المذكرة</button>
                        @endif
                    </div>

                    {{-- ─── Tab 1: Content (article sections) ─── --}}
                    <div class="mz-card-body" x-show="tab==='content'" x-cloak>
                        <p style="font-size:11px;color:var(--mute);margin:0 0 14px">
                            💡 حدّد أي نص لإجراء تعاوني (تظليل / نقاش / مهمة)
                        </p>

                        {{-- Only show extraction banners when there is actually no content yet.
                             If content exists (e.g. after a successful re-extraction) we never
                             want a stale "failed" marker to hide a perfectly good document. --}}
                        @if (empty($document->content))
                            @if ($extractionStatus === 'pending')
                                <div class="mz-notice mz-notice-warn">
                                    <div class="mz-notice-title">⏳ جاري استخراج المحتوى عبر OCR</div>
                                    <div class="mz-notice-body">يعمل النظام على قراءة النص من الملف. حدّث الصفحة بعد قليل.</div>
                                </div>
                            @elseif ($extractionStatus === 'failed')
                                <div class="mz-notice mz-notice-error">
                                    <div class="mz-notice-title">⚠ تعذّر استخراج المحتوى</div>
                                    <div class="mz-notice-body">{{ $extractionError ?? 'خطأ غير معروف.' }}</div>
                                </div>
                            @endif
                        @endif

                        @if ($chunks->isNotEmpty())
                            {{-- Chunked rendering — each section has its own anchor + actions --}}
                            <div id="doc-content" class="mz-selectable">
                                @foreach ($chunks as $chunk)
                                    @php $upForThis = $updatesByLabel[$chunk->label] ?? collect(); @endphp
                                    <section id="chunk-{{ $chunk->chunk_index }}"
                                             class="mz-article"
                                             data-label="{{ $chunk->label }}"
                                             data-chunk-index="{{ $chunk->chunk_index }}">
                                        @if ($chunk->label)
                                            <header class="mz-article-head">
                                                <span class="mz-article-label">{{ $chunk->label }}</span>
                                                <div class="mz-article-actions">
                                                    @if ($upForThis->count() > 0)
                                                        <button type="button"
                                                                class="mz-art-btn mz-art-btn-warn"
                                                                onclick="openUpdatesModal('{{ addslashes($chunk->label) }}')"
                                                                title="عرض التحديثات">
                                                            🔁 تحديثات ({{ $upForThis->count() }})
                                                        </button>
                                                    @endif
                                                    @if ($aiConfigured)
                                                        <button type="button"
                                                                class="mz-art-btn mz-art-btn-ai"
                                                                onclick="explainArticle({{ $chunk->chunk_index }}, '{{ addslashes($chunk->label) }}')"
                                                                title="اشرح هذه المادة بالذكاء الاصطناعي">🤖 اشرح</button>
                                                    @endif
                                                    <button type="button"
                                                            class="mz-art-btn"
                                                            onclick="copyArticle({{ $chunk->chunk_index }})"
                                                            title="نسخ المادة">📋</button>
                                                    <button type="button"
                                                            class="mz-art-btn"
                                                            onclick="shareArticle({{ $chunk->chunk_index }})"
                                                            title="نسخ رابط المادة">🔗</button>
                                                </div>
                                            </header>
                                        @endif
                                        <div class="mz-article-body">{{ $chunk->content }}</div>
                                        <div id="ai-explain-{{ $chunk->chunk_index }}" class="mz-ai-explain" style="display:none"></div>
                                    </section>
                                @endforeach
                            </div>
                        @elseif ($document->content)
                            {{-- Fallback: raw content (no chunks indexed yet) --}}
                            <div id="doc-content" class="mz-selectable" style="font-family:'Amiri',serif;font-size:15px;color:var(--cream);line-height:2.1;white-space:pre-wrap">{{ $document->content }}</div>
                        @else
                            <p style="text-align:center;color:var(--mute);padding:24px">لا يوجد محتوى نصي لهذا المستند.</p>
                        @endif
                    </div>

                    {{-- ─── Tab: PDF Viewer (full document) ─── --}}
                    @if ($document->file_path && $isPdfFile)
                        <div class="mz-card-body" x-show="tab==='pdf'" x-cloak>
                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                                <div style="font-size:13px;color:var(--cream);font-weight:700">{{ $document->file_name }}</div>
                                <div style="display:flex;gap:8px">
                                    <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="mz-btn mz-btn-ghost mz-btn-sm">
                                        🔗 فتح في نافذة جديدة
                                    </a>
                                    <a href="{{ asset('storage/' . $document->file_path) }}" download class="mz-btn mz-btn-ghost mz-btn-sm">
                                        📥 تحميل
                                    </a>
                                </div>
                            </div>
                            <iframe src="{{ asset('storage/' . $document->file_path) }}"
                                    style="width:100%;height:80vh;border:1px solid var(--borderl);border-radius:8px;background:#fff"></iframe>
                        </div>
                    @endif

                    {{-- ─── Tab 2: Source / file info ─── --}}
                    <div class="mz-card-body" x-show="tab==='source'" x-cloak>
                        <dl class="mz-meta">
                            <dt>النوع</dt><dd>{{ $document->type_label }}</dd>
                            <dt>التصنيف</dt><dd>{{ $document->kind_label }}</dd>
                            @if ($document->source_entity)
                                <dt>الجهة المُصدِرة</dt><dd>{{ $document->source_entity }}</dd>
                            @endif
                            @if ($document->reference_number)
                                <dt>رقم المرجع</dt><dd>{{ $document->reference_number }}</dd>
                            @endif
                            @if ($document->issued_at)
                                <dt>تاريخ الإصدار</dt><dd>{{ $document->issued_at->format('Y-m-d') }}</dd>
                            @endif
                            <dt>رفعه</dt><dd>{{ $document->uploader?->name ?? '—' }}</dd>
                            <dt>تاريخ الرفع</dt><dd>{{ $document->created_at->format('Y-m-d H:i') }}</dd>
                            @if ($document->file_name)
                                <dt>الملف</dt><dd>{{ $document->file_name }} ({{ number_format($document->file_size / 1024, 1) }} KB)</dd>
                            @endif
                        </dl>

                        @if ($isImageFile)
                            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borderl)">
                                <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;margin-bottom:8px">معاينة الصورة</div>
                                <img src="{{ asset('storage/' . $document->file_path) }}"
                                     alt="{{ $document->file_name }}"
                                     style="max-width:100%;border-radius:8px;border:1px solid var(--borderl);background:#fff">
                            </div>
                        @elseif ($isPdfFile)
                            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borderl)">
                                <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;margin-bottom:8px">معاينة الملف</div>
                                <iframe src="{{ asset('storage/' . $document->file_path) }}"
                                        style="width:100%;height:600px;border:1px solid var(--borderl);border-radius:8px;background:#fff"></iframe>
                            </div>
                        @endif

                        @if ($document->file_path)
                            <div style="margin-top:16px;display:flex;gap:8px;flex-wrap:wrap">
                                <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="mz-btn mz-btn-ghost">
                                    📎 تحميل الملف الأصلي
                                </a>
                                @if ($canUploadVersion)
                                    <button type="button" class="mz-btn mz-btn-ghost"
                                            onclick="openModal('modal-upload-version')">
                                        📁 رفع نسخة جديدة
                                    </button>
                                @endif
                            </div>
                        @endif

                        @if ($canUploadVersion)
                            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borderl)">
                                <button type="button" class="mz-btn mz-btn-ghost"
                                        onclick="openModal('modal-edit-content')">
                                    ✏ تحرير المحتوى المستخرج
                                </button>
                                <p style="font-size:11px;color:var(--mute);margin-top:6px;line-height:1.6">
                                    💡 إذا احتوى النص المستخرج آلياً على أخطاء (مثل الـ ligatures المكسورة في PDFs العربية القديمة)، يمكنك تصحيحها يدوياً.
                                </p>
                            </div>
                        @endif

                        @can('delete', $document)
                            <form method="POST" action="{{ route('documents.destroy', $document) }}"
                                  style="margin-top:24px;padding-top:16px;border-top:1px solid var(--borderl)"
                                  onsubmit="return confirm('هل أنت متأكد من حذف هذا المستند؟')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" style="background:transparent;border:none;color:var(--red);font-size:13px;cursor:pointer;font-family:inherit">🗑 حذف المستند</button>
                            </form>
                        @endcan
                    </div>

                    {{-- ─── Tab 3: Related documents ─── --}}
                    <div class="mz-card-body" x-show="tab==='related'" x-cloak>
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px">
                            <div style="font-size:13px;color:var(--mute)">الوثائق المرتبطة بهذا المستند</div>
                            @if ($canAddRelation)
                                <button type="button" class="mz-btn mz-btn-gold mz-btn-sm"
                                        onclick="openModal('modal-add-relation')">
                                    + ربط مستند
                                </button>
                            @endif
                        </div>

                        @if ($relationsFrom->isEmpty() && $relationsTo->isEmpty())
                            <p style="text-align:center;color:var(--mute);padding:32px 16px">
                                لا توجد ملفات ذات صلة بعد. اضغط "ربط مستند" لإضافة علاقة.
                            </p>
                        @else
                            @if ($relationsFrom->isNotEmpty())
                                <div style="margin-bottom:16px">
                                    <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;margin-bottom:8px">يرتبط بـ</div>
                                    @foreach ($relationsFrom as $rel)
                                        @php
                                            $canDelete = auth()->user()
                                                && ($rel->created_by === auth()->id()
                                                    || auth()->user()->hasAtLeastRole(\App\Enums\UserRole::OrgAdmin));
                                        @endphp
                                        <div class="mz-rel-item-wrap">
                                            <a href="{{ route('documents.show', $rel->toDocument) }}" class="mz-rel-item">
                                                <span class="mz-rel-type">{{ $rel->relation_label }}</span>
                                                <span class="mz-rel-title">{{ $rel->toDocument?->title ?? '—' }}</span>
                                                @if ($rel->note)<div class="mz-rel-note">{{ $rel->note }}</div>@endif
                                            </a>
                                            @if ($canDelete)
                                                <form method="POST" action="{{ route('relations.destroy', $rel) }}"
                                                      class="mz-rel-del"
                                                      onsubmit="return confirm('حذف هذا الربط؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="حذف الربط">🗑</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            @if ($relationsTo->isNotEmpty())
                                <div>
                                    <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;margin-bottom:8px">يُشار إليه من</div>
                                    @foreach ($relationsTo as $rel)
                                        <a href="{{ route('documents.show', $rel->fromDocument) }}" class="mz-rel-item">
                                            <span class="mz-rel-type">{{ $rel->relation_label }}</span>
                                            <span class="mz-rel-title">{{ $rel->fromDocument?->title ?? '—' }}</span>
                                            @if ($rel->note)<div class="mz-rel-note">{{ $rel->note }}</div>@endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        @endif
                    </div>

                    {{-- ─── Tab 4: Timeline ─── --}}
                    <div class="mz-card-body" x-show="tab==='timeline'" x-cloak>
                        @php
                            // Merge versions, article_updates, and creation event into a single timeline
                            $events = collect();
                            $events->push([
                                'date' => $document->created_at,
                                'kind' => 'created',
                                'icon' => '✨',
                                'title' => 'إنشاء الوثيقة',
                                'body' => 'رفعها ' . ($document->uploader?->name ?? '—'),
                            ]);
                            foreach ($versions as $v) {
                                $events->push([
                                    'date' => $v->created_at,
                                    'kind' => 'version',
                                    'icon' => '📁',
                                    'title' => 'نسخة جديدة #' . $v->version_number,
                                    'body' => $v->file_name . ' — رفعها ' . ($v->uploader?->name ?? '—'),
                                ]);
                            }
                            foreach ($articleUpdates as $u) {
                                $events->push([
                                    'id' => $u->id,
                                    'date' => $u->update_date,
                                    'kind' => 'update',
                                    'icon' => '🔁',
                                    'title' => 'تحديث ' . $u->article_label,
                                    'body' => $u->body,
                                    'auto' => $u->auto_generated,
                                    'decree' => $u->decree_number,
                                    'decree_url' => $u->decree_url,
                                    'creator' => $u->creator?->name,
                                    'can_delete' => auth()->user()?->can('delete', $u) ?? false,
                                ]);
                            }
                            $events = $events->sortByDesc(fn($e) => $e['date']->toDateString())->values();
                        @endphp

                        @if ($canAddUpdate)
                            <div style="display:flex;justify-content:flex-end;margin-bottom:14px">
                                <button type="button" class="mz-btn mz-btn-gold mz-btn-sm"
                                        onclick="openModal('modal-add-update')">
                                    + إضافة تحديث للمادة
                                </button>
                            </div>
                        @endif

                        @if ($events->count() <= 1 && $articleUpdates->isEmpty() && $versions->isEmpty())
                            <p style="text-align:center;color:var(--mute);padding:32px 16px">
                                لا توجد أحداث في الخط الزمني بعد. التحديثات والنسخ ستظهر هنا.
                            </p>
                        @endif

                        <ol class="mz-timeline">
                            @foreach ($events as $ev)
                                <li class="mz-timeline-item mz-timeline-{{ $ev['kind'] }}">
                                    <div class="mz-timeline-icon">{{ $ev['icon'] }}</div>
                                    <div class="mz-timeline-content">
                                        <div class="mz-timeline-head">
                                            <span class="mz-timeline-title">{{ $ev['title'] }}</span>
                                            @if (($ev['auto'] ?? false))
                                                <span class="mz-badge mz-badge-grey" title="مكتشف تلقائياً عبر مقارنة النسخ">🤖 تلقائي</span>
                                            @elseif ($ev['kind'] === 'update')
                                                <span class="mz-badge mz-badge-grey">✍ يدوي</span>
                                            @endif
                                            <span class="mz-timeline-date">{{ $ev['date']->format('Y-m-d') }}</span>
                                            @if (($ev['kind'] === 'update') && ($ev['can_delete'] ?? false))
                                                <form method="POST" action="{{ route('article-updates.destroy', $ev['id']) }}"
                                                      style="display:inline;margin-right:6px"
                                                      onsubmit="return confirm('حذف هذا التحديث؟')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:11px"
                                                            title="حذف">🗑</button>
                                                </form>
                                            @endif
                                        </div>
                                        @if ($ev['kind'] === 'update')
                                            <div class="mz-timeline-body">{{ Str::limit($ev['body'], 240) }}</div>
                                            @if (!empty($ev['decree']) || !empty($ev['decree_url']))
                                                <div style="font-size:11px;color:var(--mute);margin-top:6px">
                                                    @if ($ev['decree'])📋 {{ $ev['decree'] }}@endif
                                                    @if ($ev['decree_url'])
                                                        · <a href="{{ $ev['decree_url'] }}" target="_blank" rel="noopener" style="color:var(--gold)">رابط القرار</a>
                                                    @endif
                                                </div>
                                            @endif
                                            @if (!empty($ev['creator']))
                                                <div style="font-size:11px;color:var(--mute);margin-top:4px">— {{ $ev['creator'] }}</div>
                                            @endif
                                        @else
                                            <div class="mz-timeline-body">{{ $ev['body'] }}</div>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    </div>

                    {{-- ─── Tab 5: AI Assistant ─── --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='ai'" x-cloak>
                        @if (! $aiConfigured)
                            <div class="mz-notice mz-notice-warn">
                                <div class="mz-notice-title">🤖 المساعد الذكي غير متاح</div>
                                <div class="mz-notice-body">
                                    لتفعيل المساعد الذكي، يجب على مدير النظام ضبط متغير
                                    <code style="background:var(--card2);padding:2px 6px;border-radius:4px">ANTHROPIC_API_KEY</code>
                                    في ملف <code>.env</code> ثم إعادة تشغيل الخادم.
                                </div>
                            </div>
                        @else
                            <div id="ai-chat-panel">
                                @if (! empty($suggestedQuestions))
                                    <div style="margin-bottom:14px">
                                        <div style="font-size:11px;font-weight:800;color:var(--mute);text-transform:uppercase;margin-bottom:8px">أسئلة مقترحة</div>
                                        <div style="display:flex;flex-wrap:wrap;gap:6px">
                                            @foreach ($suggestedQuestions as $q)
                                                <button type="button" class="mz-chip" onclick="askAi(this.dataset.q)" data-q="{{ $q }}">
                                                    {{ $q }}
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                                <div id="ai-messages" class="mz-ai-messages">
                                    <div style="text-align:center;color:var(--mute);padding:24px;font-size:13px">
                                        ابدأ محادثة جديدة بطرح سؤال عن هذه الوثيقة.
                                    </div>
                                </div>
                                <div class="mz-ai-input">
                                    <textarea id="ai-input" rows="2" placeholder="اطرح سؤالاً عن هذه الوثيقة..." class="mz-inp mz-textarea"></textarea>
                                    <button type="button" id="ai-send-btn" class="mz-btn mz-btn-gold" onclick="sendAiMessage()">إرسال</button>
                                </div>
                            </div>
                        @endif
                    </div>

                    @php
                        // Shared analysis status helper — reused across all analysis tabs
                        $analysisNotice = null;
                        if ($analysisStatus === 'unavailable' || ! $aiConfigured) {
                            $analysisNotice = ['warn', '⚖ التحليل غير متاح', 'ميزة AI غير متاحة. تحقق من إعدادات AI_PROVIDER في ملف .env.'];
                        } elseif ($analysisStatus === 'failed') {
                            $analysisNotice = ['error', '⚠ فشل التحليل', $document->metadata['analysis_error'] ?? 'خطأ غير معروف.'];
                        } elseif (! $hasAnalysis) {
                            $analysisNotice = ['warn', '⏳ التحليل قيد المعالجة', 'يجري النظام تحليل الوثيقة في الخلفية. حدّث الصفحة بعد قليل.'];
                        }
                    @endphp

                    @if ($isContract)
                    {{-- ─── Tab: تحليل العقد ─── --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='analysis'" x-cloak>
                        @if ($analysisNotice)
                            <div class="mz-notice mz-notice-{{ $analysisNotice[0] }}">
                                <div class="mz-notice-title">{{ $analysisNotice[1] }}</div>
                                <div class="mz-notice-body">{{ $analysisNotice[2] }}</div>
                            </div>
                        @else
                            @php $a = $document->analysis; @endphp

                            @if (! empty($a['summary']))
                                <div style="margin-bottom:18px">
                                    <div class="mz-section-label">الملخص</div>
                                    <p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p>
                                </div>
                            @endif

                                @if (! empty($a['parties']))
                                    <div style="margin-bottom:18px">
                                        <div class="mz-section-label">الأطراف</div>
                                        <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                            @foreach ($a['parties'] as $p)<li>{{ $p }}</li>@endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (! empty($a['risks']))
                                    <div style="margin-bottom:18px">
                                        <div class="mz-section-label">المخاطر المكتشفة ({{ count($a['risks']) }})</div>
                                        @foreach ($a['risks'] as $r)
                                            @php
                                                $sev = $r['severity'] ?? 'medium';
                                                $sevClass = match($sev) {
                                                    'high' => 'mz-risk-high',
                                                    'low'  => 'mz-risk-low',
                                                    default => 'mz-risk-med',
                                                };
                                                $sevLabel = match($sev) {
                                                    'high' => 'عالية', 'low' => 'منخفضة', default => 'متوسطة',
                                                };
                                            @endphp
                                            <div class="mz-risk-card {{ $sevClass }}">
                                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">
                                                    <span style="font-size:12px;font-weight:700;color:var(--cream)">{{ $r['clause'] ?? '' }}</span>
                                                    <span class="mz-risk-badge">{{ $sevLabel }}</span>
                                                </div>
                                                <div style="font-size:12px;color:var(--dim);line-height:1.7;margin-bottom:6px">{{ $r['explanation'] ?? '' }}</div>
                                                @if (! empty($r['suggested_change']))
                                                    <div style="font-size:11px;color:var(--gold);background:rgba(200,169,75,.08);padding:6px 10px;border-radius:6px;line-height:1.6">
                                                        💡 اقتراح: {{ $r['suggested_change'] }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                @if (! empty($a['obligations']))
                                    <div style="margin-bottom:18px">
                                        <div class="mz-section-label">الالتزامات</div>
                                        <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                            @foreach ($a['obligations'] as $o)
                                                <li><strong>{{ $o['party'] ?? '—' }}:</strong> {{ $o['obligation'] ?? '' }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (! empty($a['deadlines']))
                                    <div style="margin-bottom:18px">
                                        <div class="mz-section-label">المواعيد</div>
                                        <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">
                                            @foreach ($a['deadlines'] as $d)
                                                <li>{{ $d['description'] ?? '—' }} <span style="color:var(--mute)">({{ $d['date_or_period'] ?? '—' }})</span></li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                @if (! empty($a['missing_clauses']))
                                    <div style="margin-bottom:18px">
                                        <div class="mz-section-label">بنود مقترح إضافتها</div>
                                        <ul style="padding-right:18px;color:var(--dim);font-size:13px;line-height:1.9">
                                            @foreach ($a['missing_clauses'] as $m)<li>{{ $m }}</li>@endforeach
                                        </ul>
                                    </div>
                                @endif
                        @endif
                    </div>
                    @endif

                    @if ($isContractReview)
                    {{-- ─── Tab: مراجعة العقد ─── --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='review'" x-cloak>
                        @if ($analysisNotice)
                            <div class="mz-notice mz-notice-{{ $analysisNotice[0] }}"><div class="mz-notice-title">{{ $analysisNotice[1] }}</div><div class="mz-notice-body">{{ $analysisNotice[2] }}</div></div>
                        @else
                            @php $a = $document->analysis; @endphp
                            @if (! empty($a['summary']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">الملخص</div><p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p></div>
                            @endif
                            @if (! empty($a['contract_type']))
                                <div style="margin-bottom:14px"><div class="mz-section-label">نوع العقد</div><p style="font-size:14px;color:var(--cream)">{{ $a['contract_type'] }}</p></div>
                            @endif
                            @if (! empty($a['overall_rating']))
                                <div style="margin-bottom:18px;padding:12px 16px;background:rgba(200,169,75,.06);border-right:4px solid var(--gold);border-radius:8px">
                                    <span style="font-size:13px;font-weight:700;color:var(--gold)">التقييم العام: {{ $a['overall_rating'] }}</span>
                                    @if (! empty($a['overall_notes']))<div style="font-size:12px;color:var(--cream);margin-top:6px;line-height:1.7">{{ $a['overall_notes'] }}</div>@endif
                                </div>
                            @endif
                            @if (! empty($a['risks']))
                                <div style="margin-bottom:18px">
                                    <div class="mz-section-label">المخاطر ({{ count($a['risks']) }})</div>
                                    @foreach ($a['risks'] as $r)
                                        @php $sev=$r['severity']??'medium'; $sc=match($sev){'high','critical'=>'mz-risk-high','low'=>'mz-risk-low',default=>'mz-risk-med'}; $sl=match($sev){'high'=>'عالية','critical'=>'حرجة','low'=>'منخفضة',default=>'متوسطة'}; @endphp
                                        <div class="mz-risk-card {{ $sc }}">
                                            <div style="display:flex;justify-content:space-between;margin-bottom:6px"><span style="font-size:12px;font-weight:700;color:var(--cream)">{{ $r['clause']??$r['description']??'' }}</span><span class="mz-risk-badge">{{ $sl }}</span></div>
                                            @if(!empty($r['description'])&&!empty($r['clause']))<div style="font-size:12px;color:var(--dim);line-height:1.7;margin-bottom:4px">{{ $r['description'] }}</div>@endif
                                            @if(!empty($r['mitigation']))<div style="font-size:11px;color:var(--gold)">💡 {{ $r['mitigation'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($a['compliance_issues']))
                                <div style="margin-bottom:18px">
                                    <div class="mz-section-label" style="color:#e05555">مخالفات أنظمة ({{ count($a['compliance_issues']) }})</div>
                                    @foreach ($a['compliance_issues'] as $ci)
                                        <div class="mz-risk-card mz-risk-high">
                                            <div style="font-size:12px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $ci['law']??'' }} {{ !empty($ci['article'])?'— المادة '.$ci['article']:'' }}</div>
                                            <div style="font-size:12px;color:var(--dim);line-height:1.7">{{ $ci['issue']??'' }}</div>
                                            @if(!empty($ci['recommendation']))<div style="font-size:11px;color:var(--gold);margin-top:4px">💡 {{ $ci['recommendation'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($a['recommended_amendments']))
                                <div style="margin-bottom:18px">
                                    <div class="mz-section-label">تعديلات مقترحة ({{ count($a['recommended_amendments']) }})</div>
                                    @foreach ($a['recommended_amendments'] as $am)
                                        <div style="background:var(--card2);border:1px solid var(--borderl);border-radius:8px;padding:10px 14px;margin-bottom:8px">
                                            <div style="font-size:11px;color:var(--red);text-decoration:line-through;margin-bottom:4px">{{ $am['current']??'' }}</div>
                                            <div style="font-size:12px;color:#3dbf8a;margin-bottom:4px">{{ $am['suggested']??'' }}</div>
                                            <div style="font-size:11px;color:var(--mute)">💡 {{ $am['reason']??'' }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if (! empty($a['missing_protections']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">بنود حماية مفقودة</div>
                                    <ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['missing_protections'] as $mp)<li>⚠ {{ $mp }}</li>@endforeach</ul>
                                </div>
                            @endif
                        @endif
                    </div>
                    @endif

                    @if ($isCase)
                    {{-- ─── Tab: تحليل القضية ─── --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='case'" x-cloak>
                        @if ($analysisNotice)
                            <div class="mz-notice mz-notice-{{ $analysisNotice[0] }}"><div class="mz-notice-title">{{ $analysisNotice[1] }}</div><div class="mz-notice-body">{{ $analysisNotice[2] }}</div></div>
                        @else
                            @php $a = $document->analysis; @endphp
                            @if(!empty($a['summary']))<div style="margin-bottom:18px"><div class="mz-section-label">الملخص</div><p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p></div>@endif
                            @if(!empty($a['court']))<div style="margin-bottom:14px"><div class="mz-section-label">الجهة القضائية</div><p style="font-size:14px;color:var(--cream)">{{ $a['court'] }}</p></div>@endif
                            @if(!empty($a['ruling']))<div style="margin-bottom:14px"><div class="mz-section-label">الحكم</div><p style="font-size:14px;color:var(--cream);line-height:1.8">{{ $a['ruling'] }}</p></div>@endif
                            @if(!empty($a['legal_basis']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">الأساس القانوني</div>
                                    @foreach($a['legal_basis'] as $lb)<div style="background:var(--card2);border:1px solid var(--borderl);border-radius:8px;padding:10px 12px;margin-bottom:6px"><div style="font-size:12px;font-weight:700;color:var(--gold);margin-bottom:4px">{{ $lb['reference']??'—' }}</div><div style="font-size:12px;color:var(--dim);line-height:1.7">{{ $lb['explanation']??'' }}</div></div>@endforeach
                                </div>
                            @endif
                            @if(!empty($a['applicable_laws']))<div style="margin-bottom:18px"><div class="mz-section-label">القوانين المنطبقة</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['applicable_laws'] as $l)<li>{{ $l }}</li>@endforeach</ul></div>@endif
                            @if(!empty($a['outcome_prediction']))<div style="margin-bottom:18px"><div class="mz-section-label">توقّع النتيجة</div><p style="font-size:13px;color:var(--cream);line-height:1.8;background:rgba(200,169,75,.06);border-right:3px solid var(--gold);padding:10px 14px;border-radius:6px">{{ $a['outcome_prediction'] }}</p></div>@endif
                            @if(!empty($a['success_factors']))<div style="margin-bottom:14px"><div class="mz-section-label" style="color:#3dbf8a">عوامل نجاح القضية</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['success_factors'] as $f)<li>✓ {{ $f }}</li>@endforeach</ul></div>@endif
                            @if(!empty($a['risk_factors']))<div style="margin-bottom:14px"><div class="mz-section-label" style="color:#e05555">عوامل المخاطرة</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['risk_factors'] as $f)<li>⚠ {{ $f }}</li>@endforeach</ul></div>@endif
                        @endif
                    </div>
                    @endif

                    @if ($isMemo)
                    {{-- ─── Tab: تحليل المذكرة ─── --}}
                    <div class="mz-card-body mz-selectable" x-show="tab==='memo'" x-cloak>
                        @if ($analysisNotice)
                            <div class="mz-notice mz-notice-{{ $analysisNotice[0] }}"><div class="mz-notice-title">{{ $analysisNotice[1] }}</div><div class="mz-notice-body">{{ $analysisNotice[2] }}</div></div>
                        @else
                            @php $a = $document->analysis; @endphp
                            @if(!empty($a['summary']))<div style="margin-bottom:18px"><div class="mz-section-label">الملخص</div><p style="font-size:14px;color:var(--cream);line-height:1.85">{{ $a['summary'] }}</p></div>@endif
                            @if(!empty($a['memo_type']))<div style="margin-bottom:14px"><div class="mz-section-label">نوع المذكرة</div><p style="font-size:14px;color:var(--cream)">{{ $a['memo_type'] }}</p></div>@endif
                            @if(!empty($a['overall_assessment'])||!empty($a['persuasiveness_score']))
                                <div style="margin-bottom:18px;padding:12px 16px;background:rgba(200,169,75,.06);border-right:4px solid var(--gold);border-radius:8px;display:flex;justify-content:space-between;align-items:center">
                                    <span style="font-size:13px;font-weight:700;color:var(--gold)">التقييم: {{ $a['overall_assessment']??'—' }}</span>
                                    @if(!empty($a['persuasiveness_score']))<span style="font-size:13px;color:var(--cream)">قوة الإقناع: <strong>{{ $a['persuasiveness_score'] }}</strong>/10</span>@endif
                                </div>
                            @endif
                            @if(!empty($a['legal_arguments']))
                                <div style="margin-bottom:18px"><div class="mz-section-label">الحجج القانونية</div>
                                    @foreach($a['legal_arguments'] as $arg)
                                        @php $str=$arg['strength']??'متوسطة'; $strColor=match($str){'قوية'=>'#3dbf8a','ضعيفة'=>'#e05555',default=>'#c8a94b'}; @endphp
                                        <div style="background:var(--card2);border-right:3px solid {{ $strColor }};border-radius:8px;padding:10px 12px;margin-bottom:6px">
                                            <div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="font-size:12px;color:var(--cream);font-weight:600">{{ $arg['argument']??'' }}</span><span style="font-size:10px;color:{{ $strColor }};font-weight:700">{{ $str }}</span></div>
                                            @if(!empty($arg['note']))<div style="font-size:11px;color:var(--mute)">{{ $arg['note'] }}</div>@endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            @if(!empty($a['weak_points']))
                                <div style="margin-bottom:18px"><div class="mz-section-label" style="color:#e05555">نقاط الضعف</div>
                                    @foreach($a['weak_points'] as $wp)<div class="mz-risk-card mz-risk-med"><div style="font-size:12px;color:var(--cream);margin-bottom:4px">{{ $wp['point']??'' }}</div><div style="font-size:11px;color:var(--gold)">💡 {{ $wp['suggestion']??'' }}</div></div>@endforeach
                                </div>
                            @endif
                            @if(!empty($a['missing_references']))<div style="margin-bottom:18px"><div class="mz-section-label">مراجع قانونية مقترحة</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['missing_references'] as $mr)<li>📚 {{ $mr }}</li>@endforeach</ul></div>@endif
                            @if(!empty($a['key_recommendations']))<div style="margin-bottom:18px"><div class="mz-section-label" style="color:#3dbf8a">أهم التوصيات</div><ul style="padding-right:18px;color:var(--cream);font-size:13px;line-height:1.9">@foreach($a['key_recommendations'] as $kr)<li>✓ {{ $kr }}</li>@endforeach</ul></div>@endif
                        @endif
                    </div>
                    @endif
                </div>

                {{-- Discussions card (below tabs) --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">💬 النقاشات ({{ $discussions->count() }})</div>
                    </div>
                    <div class="mz-card-body" style="padding:8px">
                        @forelse ($discussions as $disc)
                            <a href="{{ route('discussions.show', $disc) }}"
                               style="display:block;padding:12px 14px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                                <div style="font-size:13px;font-weight:700;color:var(--cream);margin-bottom:4px">{{ $disc->title }}</div>
                                <div style="font-size:11px;color:var(--mute)">
                                    {{ $disc->user?->name }} · {{ $disc->created_at->diffForHumans() }}
                                    · 💬 {{ $disc->replies->count() }} ردود
                                </div>
                            </a>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد نقاشات بعد</p>
                        @endforelse
                    </div>
                </div>
            </div>

            {{-- ───── Right column: annotations sidebar ───── --}}
            <aside class="mz-doc-side">
                <div class="mz-card" style="position:sticky;top:16px">
                    <div class="mz-card-head">
                        <div class="mz-card-title" style="font-size:13px">📝 الملاحظات ({{ $annotations->count() }})</div>
                    </div>
                    <div class="mz-card-body" style="max-height:70vh;overflow-y:auto">
                        @forelse ($annotations as $ann)
                            <div style="background:var(--card2);border-right:3px solid {{ \App\Models\Annotation::COLORS[$ann->color] ?? '#c8a94b' }};border-radius:8px;padding:10px 12px;margin-bottom:8px">
                                <div style="font-size:11px;color:var(--cream);background:rgba(255,255,255,.04);padding:6px 8px;border-radius:5px;margin-bottom:6px;line-height:1.5">"{{ Str::limit($ann->selected_text, 100) }}"</div>
                                @if ($ann->comment)
                                    <div style="font-size:12px;color:var(--dim);line-height:1.6">{{ $ann->comment }}</div>
                                @endif
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:6px;font-size:10px;color:var(--mute)">
                                    <span>
                                        {{ $ann->user?->name }} · {{ $ann->created_at->diffForHumans() }}
                                        @if ($ann->visibility === 'private') · 🔒
                                        @elseif ($ann->visibility === 'public') · 🌍
                                        @endif
                                    </span>
                                    @if ($ann->user_id === auth()->id())
                                        <form method="POST" action="{{ route('annotations.destroy', $ann) }}" style="display:inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:10px">حذف</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p style="text-align:center;color:var(--mute);font-size:12px;padding:16px">لا توجد ملاحظات</p>
                        @endforelse
                    </div>
                </div>
            </aside>
        </div>
    </div>

    {{-- ═══════ Floating selection toolbar ═══════ --}}
    <div id="sel-toolbar" class="mz-sel-toolbar">
        <div id="sel-quote" class="mz-sel-quote"></div>
        <div class="mz-sel-actions">
            <button type="button" class="mz-sel-color" data-color="gold">
                <span class="dot" style="background:#c8a94b"></span> ذهبي
            </button>
            <button type="button" class="mz-sel-color" data-color="blue">
                <span class="dot" style="background:#3d82ff"></span> أزرق
            </button>
            <button type="button" class="mz-sel-color" data-color="green">
                <span class="dot" style="background:#3dbf8a"></span> أخضر
            </button>
            <button type="button" class="mz-sel-color" data-color="red">
                <span class="dot" style="background:#e05555"></span> أحمر
            </button>

            <span class="mz-sel-divider"></span>

            <select id="sel-visibility" class="mz-sel-vis" title="نطاق الرؤية">
                <option value="public">🌍 عامة</option>
                <option value="org" selected>🏢 المؤسسة</option>
                <option value="private">🔒 خاصة</option>
            </select>

            <span class="mz-sel-divider"></span>

            <button type="button" id="sel-note" class="mz-sel-action gold">📌 إضافة ملاحظة</button>
            <button type="button" id="sel-task" class="mz-sel-action green">📝 إنشاء مهمة</button>
            <button type="button" id="sel-discuss" class="mz-sel-action blue">💬 فتح نقاش</button>
            <button type="button" id="sel-copy" class="mz-sel-action dark">📋 نسخ مع المصدر</button>

            <button type="button" id="sel-close" class="mz-sel-close">✕</button>
        </div>
    </div>

    {{-- ═══════ Discussion modal ═══════ --}}
    <div id="modal-discussion" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-discussion')">
        <div class="mz-modal">
            <div class="mz-modal-head">
                <div class="mz-modal-title">💬 فتح نقاش حول النص المُحدَّد</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-discussion')">✕</button>
            </div>
            <form method="POST" action="{{ route('discussions.store', $document) }}">
                @csrf
                <div class="mz-modal-body">
                    <div class="mz-form-group">
                        <label class="mz-flabel">عنوان النقاش *</label>
                        <input type="text" name="title" required class="mz-inp" placeholder="مثال: تفسير المادة 14">
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">المحتوى *</label>
                        <textarea name="body" id="disc-body" required rows="6" class="mz-inp mz-textarea"></textarea>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">نطاق الرؤية</label>
                        <select name="visibility" class="mz-inp">
                            <option value="public">🌍 عامة — تظهر للجميع</option>
                            <option value="org" selected>🏢 مقيّدة — المؤسسة فقط</option>
                            <option value="private">🔒 خاصة — لي فقط</option>
                        </select>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-discussion')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">بدء النقاش</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ Annotation/Note modal (with comment) ═══════ --}}
    <div id="modal-note" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-note')">
        <div class="mz-modal" style="max-width:560px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">📌 إضافة ملاحظة على النص المُحدَّد</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-note')">✕</button>
            </div>
            <form method="POST" action="{{ route('annotations.store', $document) }}">
                @csrf
                <input type="hidden" name="selected_text" id="note-selected-text">
                <div class="mz-modal-body">
                    <div class="mz-form-group">
                        <label class="mz-flabel">النص المُحدَّد</label>
                        <div id="note-quote" style="font-size:12px;color:var(--dim);background:var(--card2);padding:10px 12px;border-radius:8px;line-height:1.6;max-height:100px;overflow-y:auto;border-right:3px solid var(--gold)"></div>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">التعليق *</label>
                        <textarea name="comment" id="note-comment" required rows="4" class="mz-inp mz-textarea" placeholder="اكتب ملاحظتك هنا..."></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">اللون</label>
                            <select name="color" class="mz-inp">
                                <option value="gold">🟡 ذهبي</option>
                                <option value="blue">🔵 أزرق</option>
                                <option value="green">🟢 أخضر</option>
                                <option value="red">🔴 أحمر</option>
                            </select>
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">نطاق الرؤية</label>
                            <select name="visibility" class="mz-inp">
                                <option value="public">🌍 عامة — للجميع</option>
                                <option value="org" selected>🏢 مقيّدة — المؤسسة</option>
                                <option value="private">🔒 خاصة — لي فقط</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-note')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">📌 حفظ الملاحظة</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ Task modal ═══════ --}}
    <div id="modal-task" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-task')">
        <div class="mz-modal">
            <div class="mz-modal-head">
                <div class="mz-modal-title">📝 إنشاء مهمة من النص المُحدَّد</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-task')">✕</button>
            </div>
            <form method="POST" action="{{ route('tasks.store') }}">
                @csrf
                <input type="hidden" name="document_id" value="{{ $document->id }}">
                <div class="mz-modal-body">
                    <div class="mz-form-group">
                        <label class="mz-flabel">عنوان المهمة *</label>
                        <input type="text" name="title" required class="mz-inp">
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">الوصف</label>
                        <textarea name="description" id="task-desc" rows="5" class="mz-inp mz-textarea"></textarea>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">الأولوية</label>
                            <select name="priority" class="mz-inp">
                                @foreach (\App\Models\Task::PRIORITIES as $id => $label)
                                    <option value="{{ $id }}" @selected($id == 2)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">تاريخ الاستحقاق</label>
                            <input type="date" name="due_date" class="mz-inp">
                        </div>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-task')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">إنشاء المهمة</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ Article updates modal (per-article view) ═══════ --}}
    <div id="modal-updates" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-updates')">
        <div class="mz-modal" style="max-width:680px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">🔁 تحديثات <span id="updates-label"></span></div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-updates')">✕</button>
            </div>
            <div class="mz-modal-body" id="updates-body" style="max-height:60vh;overflow-y:auto">
                <p style="text-align:center;color:var(--mute);padding:24px">لا توجد تحديثات.</p>
            </div>
            <div class="mz-modal-footer">
                <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-updates')">إغلاق</button>
            </div>
        </div>
    </div>

    @if ($canAddUpdate)
    {{-- ═══════ Add article update modal (manual entry) ═══════ --}}
    <div id="modal-add-update" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-add-update')">
        <div class="mz-modal" style="max-width:640px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">+ إضافة تحديث للمادة</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-add-update')">✕</button>
            </div>
            <form method="POST" action="{{ route('article-updates.store', $document) }}">
                @csrf
                <div class="mz-modal-body">
                    <div class="mz-form-group">
                        <label class="mz-flabel">المادة *</label>
                        @if (count($articleLabels) > 0)
                            <select name="article_label" required class="mz-inp">
                                <option value="">— اختر مادة —</option>
                                @foreach ($articleLabels as $label)
                                    <option value="{{ $label }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        @else
                            <input type="text" name="article_label" required class="mz-inp" placeholder="مثال: المادة 14">
                        @endif
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">تاريخ التحديث *</label>
                            <input type="date" name="update_date" required value="{{ now()->toDateString() }}" class="mz-inp">
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">رقم القرار</label>
                            <input type="text" name="decree_number" class="mz-inp" placeholder="مثال: قرار مجلس الوزراء رقم 723">
                        </div>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">رابط القرار</label>
                        <input type="url" name="decree_url" class="mz-inp" dir="ltr" placeholder="https://...">
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">نص التعديل *</label>
                        <textarea name="body" required rows="6" class="mz-inp mz-textarea" placeholder="النص الجديد للمادة بعد التعديل..."></textarea>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-add-update')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">إضافة التحديث</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($canUploadVersion)
    {{-- ═══════ Upload new version modal ═══════ --}}
    <div id="modal-upload-version" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-upload-version')">
        <div class="mz-modal" style="max-width:560px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">📁 رفع نسخة جديدة</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-upload-version')">✕</button>
            </div>
            <form method="POST" action="{{ route('versions.store', $document) }}" enctype="multipart/form-data">
                @csrf
                <div class="mz-modal-body">
                    <p style="font-size:12px;color:var(--mute);margin-bottom:14px;line-height:1.7">
                        ارفع نسخة جديدة من نفس الوثيقة وسيقوم النظام تلقائياً بمقارنة المواد بين النسختين
                        وإنشاء تحديثات للمواد المعدّلة.
                    </p>
                    <div class="mz-form-group">
                        <label class="mz-flabel">الملف *</label>
                        <input type="file" name="file" required accept=".pdf,.doc,.docx,.txt" class="mz-inp">
                        <p style="font-size:11px;color:var(--mute);margin-top:6px">
                            يجب أن يكون نصياً (PDF/DOCX/TXT) — الصور غير مدعومة للنسخ.
                        </p>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-upload-version')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">رفع النسخة</button>
                </div>
            </form>
        </div>
    </div>

    {{-- ═══════ Edit content modal (manual correction of extraction errors) ═══════ --}}
    <div id="modal-edit-content" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-edit-content')">
        <div class="mz-modal" style="max-width:900px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">✏ تحرير المحتوى المستخرج</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-edit-content')">✕</button>
            </div>
            <form method="POST" action="{{ route('documents.updateContent', $document) }}">
                @csrf
                @method('PATCH')
                <div class="mz-modal-body">
                    <p style="font-size:12px;color:var(--mute);margin-bottom:14px;line-height:1.7">
                        يمكنك تصحيح الأخطاء يدوياً هنا. عند الحفظ سيتم إعادة فهرسة المحتوى تلقائياً.
                    </p>
                    <div class="mz-form-group">
                        <label class="mz-flabel">الملخص</label>
                        <textarea name="summary" rows="3" class="mz-inp mz-textarea">{{ $document->summary }}</textarea>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">المحتوى الكامل</label>
                        <textarea name="content" rows="20" class="mz-inp mz-textarea" style="font-family:'Amiri',serif;font-size:14px;line-height:1.9">{{ $document->content }}</textarea>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-edit-content')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">حفظ وإعادة الفهرسة</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if ($canAddRelation)
    {{-- ═══════ Add relation modal (with title autocomplete) ═══════ --}}
    <div id="modal-add-relation" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-add-relation')">
        <div class="mz-modal" style="max-width:640px">
            <div class="mz-modal-head">
                <div class="mz-modal-title">+ ربط مستند</div>
                <button type="button" class="mz-modal-close" onclick="closeModal('modal-add-relation')">✕</button>
            </div>
            <form method="POST" action="{{ route('relations.store', $document) }}">
                @csrf
                <div class="mz-modal-body">
                    <div class="mz-form-group">
                        <label class="mz-flabel">نوع العلاقة *</label>
                        <select name="relation_type" required class="mz-inp">
                            @foreach (\App\Models\LegalDocument::RELATION_TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="mz-form-group" style="position:relative">
                        <label class="mz-flabel">المستند المرتبط *</label>
                        <input type="text"
                               id="relation-search"
                               class="mz-inp"
                               placeholder="ابحث عن مستند بالعنوان أو رقم المرجع..."
                               autocomplete="off">
                        <input type="hidden" name="to_document_id" id="relation-target-id" required>
                        <div id="relation-suggestions" class="mz-autocomplete-list" style="display:none"></div>
                        <div id="relation-selected" style="display:none;margin-top:8px;padding:10px 12px;background:var(--card2);border:1px solid var(--gold);border-radius:8px">
                            <div style="display:flex;justify-content:space-between;align-items:center;gap:8px">
                                <div>
                                    <div style="font-size:13px;color:var(--cream);font-weight:700" id="relation-selected-title"></div>
                                    <div style="font-size:11px;color:var(--mute)" id="relation-selected-meta"></div>
                                </div>
                                <button type="button" onclick="clearRelationSelection()"
                                        style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:14px">✕</button>
                            </div>
                        </div>
                    </div>
                    <div class="mz-form-group">
                        <label class="mz-flabel">ملاحظة</label>
                        <textarea name="note" rows="3" class="mz-inp mz-textarea" placeholder="(اختياري) وصف موجز للعلاقة"></textarea>
                    </div>
                </div>
                <div class="mz-modal-footer">
                    <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-add-relation')">إلغاء</button>
                    <button type="submit" class="mz-btn mz-btn-gold">حفظ الربط</button>
                </div>
            </form>
        </div>
    </div>
    @endif

    {{-- Hidden form to submit annotations from JS --}}
    <form id="annot-form" method="POST" action="{{ route('annotations.store', $document) }}" style="display:none">
        @csrf
        <input type="hidden" name="selected_text" id="annot-text">
        <input type="hidden" name="color" id="annot-color">
        <input type="hidden" name="visibility" id="annot-visibility" value="org">
    </form>

    <script>
        const docTitle = @json($document->title);
        const docId = @json($document->id);
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const existingAnnotations = @json($annotations->map(fn($a) => ['text' => $a->selected_text, 'color' => $a->color])->values());
        // Article updates grouped by label, exposed for the per-article modal
        const updatesByLabel = @json($updatesForJs);
        let lastSelection = '';

        // Highlight existing annotations inside selectable areas on page load
        function highlightExistingAnnotations() {
            if (!existingAnnotations.length) return;
            document.querySelectorAll('.mz-selectable').forEach(area => {
                let html = area.innerHTML;
                existingAnnotations.forEach(ann => {
                    if (!ann.text || ann.text.length < 2) return;
                    const escaped = ann.text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    try {
                        html = html.replace(escaped, '<mark data-color="' + ann.color + '">' + ann.text + '</mark>');
                    } catch (e) {}
                });
                area.innerHTML = html;
            });
        }
        highlightExistingAnnotations();

        // ═══ Selection toolbar ═══
        const toolbar = document.getElementById('sel-toolbar');
        const quoteEl = document.getElementById('sel-quote');

        function hideToolbar() { toolbar.classList.remove('open'); lastSelection = ''; }
        function showToolbar(rect, text) {
            quoteEl.textContent = '«' + text + '»';
            toolbar.classList.add('open');
            const tw = toolbar.offsetWidth;
            const th = toolbar.offsetHeight;
            // Use viewport-relative coords (rect is already viewport-relative
            // from getBoundingClientRect). The toolbar uses position:fixed.
            let top = rect.top - th - 10;
            let left = rect.left + (rect.width / 2) - (tw / 2);
            // Keep within viewport
            if (left < 12) left = 12;
            if (left + tw > window.innerWidth - 12) left = window.innerWidth - tw - 12;
            if (top < 12) top = rect.bottom + 10; // flip below if too close to top
            toolbar.style.top = top + 'px';
            toolbar.style.left = left + 'px';
        }

        document.addEventListener('mouseup', e => {
            if (toolbar.contains(e.target)) return;
            const sel = window.getSelection();
            const text = sel ? sel.toString().trim() : '';
            if (!text) { hideToolbar(); return; }
            const node = sel.anchorNode;
            const inside = node && (node.parentElement?.closest('.mz-selectable'));
            if (!inside) { hideToolbar(); return; }
            const range = sel.getRangeAt(0);
            const rect = range.getBoundingClientRect();
            lastSelection = text;
            showToolbar(rect, text);
        });

        document.getElementById('sel-close').addEventListener('click', hideToolbar);

        document.querySelectorAll('.mz-sel-color').forEach(btn => {
            btn.addEventListener('click', () => {
                if (!lastSelection) return;
                document.getElementById('annot-text').value = lastSelection;
                document.getElementById('annot-color').value = btn.dataset.color;
                document.getElementById('annot-visibility').value = document.getElementById('sel-visibility').value;
                document.getElementById('annot-form').submit();
            });
        });

        document.getElementById('sel-copy').addEventListener('click', async () => {
            if (!lastSelection) return;
            const text = '«' + lastSelection + '»\n— المصدر: ' + docTitle;
            try {
                await navigator.clipboard.writeText(text);
                showToast('تم النسخ مع المصدر');
            } catch { showToast('فشل النسخ'); }
            hideToolbar();
        });

        document.getElementById('sel-note').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('note-selected-text').value = lastSelection;
            document.getElementById('note-quote').textContent = '«' + lastSelection + '»';
            document.getElementById('note-comment').value = '';
            openModal('modal-note');
        });

        document.getElementById('sel-discuss').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('disc-body').value = '«' + lastSelection + '»\n\n';
            openModal('modal-discussion');
        });

        document.getElementById('sel-task').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('task-desc').value = 'مرتبط بالنص:\n«' + lastSelection + '»';
            openModal('modal-task');
        });

        // ═══ Modals ═══
        function openModal(id) { document.getElementById(id).classList.add('open'); hideToolbar(); }
        function closeModal(id) { document.getElementById(id).classList.remove('open'); }
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.mz-modal-overlay.open').forEach(m => m.classList.remove('open'));
                hideToolbar();
            }
        });

        function showToast(msg) {
            const t = document.createElement('div');
            t.className = 'mz-toast';
            t.textContent = '✓ ' + msg;
            document.body.appendChild(t);
            setTimeout(() => t.remove(), 2500);
        }

        // ═══ Per-article actions ═══
        function copyArticle(chunkIndex) {
            const section = document.getElementById('chunk-' + chunkIndex);
            if (!section) return;
            const label = section.dataset.label || '';
            const body = section.querySelector('.mz-article-body')?.innerText || '';
            const text = (label ? label + '\n' : '') + body + '\n\n— المصدر: ' + docTitle;
            navigator.clipboard.writeText(text)
                .then(() => showToast('تم نسخ المادة'))
                .catch(() => showToast('فشل النسخ'));
        }

        function shareArticle(chunkIndex) {
            const url = window.location.origin + window.location.pathname + '#chunk-' + chunkIndex;
            navigator.clipboard.writeText(url)
                .then(() => showToast('تم نسخ الرابط'))
                .catch(() => showToast('فشل النسخ'));
        }

        function openUpdatesModal(label) {
            document.getElementById('updates-label').textContent = label;
            const body = document.getElementById('updates-body');
            const items = updatesByLabel[label] || [];
            if (items.length === 0) {
                body.innerHTML = '<p style="text-align:center;color:var(--mute);padding:24px">لا توجد تحديثات.</p>';
            } else {
                body.innerHTML = items.map(u => `
                    <div style="padding:12px 14px;border-bottom:1px solid var(--borderl)">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:6px;flex-wrap:wrap">
                            <span style="font-size:12px;color:var(--gold);font-weight:700">📅 ${u.date}</span>
                            ${u.decree_number ? `<span style="font-size:11px;color:var(--mute)">📋 ${u.decree_number}</span>` : ''}
                            ${u.auto ? '<span class="mz-badge mz-badge-grey">🤖 تلقائي</span>' : '<span class="mz-badge mz-badge-grey">✍ يدوي</span>'}
                        </div>
                        <div style="font-size:13px;color:var(--cream);line-height:1.7;white-space:pre-wrap">${escapeHtml(u.body)}</div>
                        ${u.decree_url ? `<a href="${u.decree_url}" target="_blank" rel="noopener" style="display:inline-block;margin-top:6px;font-size:11px;color:var(--gold)">رابط القرار ↗</a>` : ''}
                        ${u.creator ? `<div style="font-size:11px;color:var(--mute);margin-top:4px">— ${escapeHtml(u.creator)}</div>` : ''}
                    </div>
                `).join('');
            }
            openModal('modal-updates');
        }

        function escapeHtml(str) {
            return String(str).replace(/[&<>"']/g, c => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            }[c]));
        }

        // ═══ Relation autocomplete (for the "ربط مستند" modal) ═══
        const relSearchInput = document.getElementById('relation-search');
        const relSuggestions = document.getElementById('relation-suggestions');
        const relTargetId = document.getElementById('relation-target-id');
        const relSelected = document.getElementById('relation-selected');
        const relSelectedTitle = document.getElementById('relation-selected-title');
        const relSelectedMeta = document.getElementById('relation-selected-meta');
        let relSearchTimer = null;

        function clearRelationSelection() {
            if (relTargetId) relTargetId.value = '';
            if (relSelected) relSelected.style.display = 'none';
            if (relSearchInput) {
                relSearchInput.value = '';
                relSearchInput.style.display = '';
                relSearchInput.focus();
            }
        }
        window.clearRelationSelection = clearRelationSelection;

        if (relSearchInput) {
            relSearchInput.addEventListener('input', () => {
                const q = relSearchInput.value.trim();
                clearTimeout(relSearchTimer);
                if (q.length < 1) {
                    relSuggestions.style.display = 'none';
                    relSuggestions.innerHTML = '';
                    return;
                }
                // Debounce 200ms to avoid hammering the endpoint while typing
                relSearchTimer = setTimeout(async () => {
                    try {
                        const url = `/api/v1/documents/autocomplete?q=${encodeURIComponent(q)}&exclude=${docId}`;
                        const resp = await fetch(url, {
                            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        if (!resp.ok) return;
                        const data = await resp.json();
                        if (!data.results || data.results.length === 0) {
                            relSuggestions.innerHTML = '<div class="mz-autocomplete-empty">لا توجد نتائج</div>';
                            relSuggestions.style.display = 'block';
                            return;
                        }
                        relSuggestions.innerHTML = data.results.map(r => `
                            <div class="mz-autocomplete-item" data-id="${r.id}" data-title="${escapeHtml(r.title)}" data-meta="${escapeHtml((r.type_label || '') + (r.reference_number ? ' · ' + r.reference_number : ''))}">
                                <div class="mz-autocomplete-title">${escapeHtml(r.title)}</div>
                                <div class="mz-autocomplete-meta">${escapeHtml(r.type_label || '')}${r.reference_number ? ' · ' + escapeHtml(r.reference_number) : ''}</div>
                            </div>
                        `).join('');
                        relSuggestions.style.display = 'block';
                        // Wire up clicks
                        relSuggestions.querySelectorAll('.mz-autocomplete-item').forEach(item => {
                            item.addEventListener('click', () => {
                                relTargetId.value = item.dataset.id;
                                relSelectedTitle.textContent = item.dataset.title;
                                relSelectedMeta.textContent = item.dataset.meta;
                                relSelected.style.display = 'block';
                                relSearchInput.style.display = 'none';
                                relSuggestions.style.display = 'none';
                                relSuggestions.innerHTML = '';
                            });
                        });
                    } catch (e) {
                        // Silent — autocomplete failure shouldn't break the modal
                    }
                }, 200);
            });

            // Hide suggestions when clicking outside
            document.addEventListener('click', (e) => {
                if (!relSearchInput.contains(e.target) && !relSuggestions.contains(e.target)) {
                    relSuggestions.style.display = 'none';
                }
            });
        }

        // ═══ AI Assistant chat panel ═══
        const aiConfigured = @json($aiConfigured);
        let aiConversationId = null;

        async function ensureAiConversation() {
            if (aiConversationId) return aiConversationId;
            const resp = await fetch('/ai/conversations', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ document_id: docId, title: 'محادثة عن: ' + docTitle }),
            });
            if (!resp.ok) {
                throw new Error('Failed to start conversation');
            }
            const data = await resp.json();
            aiConversationId = data.conversation.id;
            return aiConversationId;
        }

        function appendAiMessage(role, content, isError = false) {
            const messages = document.getElementById('ai-messages');
            // Clear the empty-state message on first append
            if (messages.querySelector('div[style*="ابدأ محادثة جديدة"]')) {
                messages.innerHTML = '';
            }
            const wrap = document.createElement('div');
            wrap.className = 'mz-ai-msg mz-ai-msg-' + role + (isError ? ' mz-ai-msg-error' : '');
            wrap.innerHTML = `
                <div class="mz-ai-msg-role">${role === 'user' ? 'أنت' : 'المساعد'}</div>
                <div class="mz-ai-msg-body"></div>
            `;
            wrap.querySelector('.mz-ai-msg-body').textContent = content;
            messages.appendChild(wrap);
            messages.scrollTop = messages.scrollHeight;
            return wrap;
        }

        async function sendAiMessage() {
            if (!aiConfigured) return;
            const input = document.getElementById('ai-input');
            const text = input.value.trim();
            if (!text) return;
            const sendBtn = document.getElementById('ai-send-btn');

            input.value = '';
            sendBtn.disabled = true;
            sendBtn.textContent = '⏳ جاري...';
            appendAiMessage('user', text);

            // Insert a placeholder assistant bubble that we'll update on response
            const placeholder = appendAiMessage('assistant', '...');

            try {
                const convId = await ensureAiConversation();
                const resp = await fetch(`/ai/conversations/${convId}/messages`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ content: text }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    placeholder.querySelector('.mz-ai-msg-body').textContent = data.error || 'حدث خطأ.';
                    placeholder.classList.add('mz-ai-msg-error');
                } else {
                    placeholder.querySelector('.mz-ai-msg-body').textContent = data.assistant_message.content;
                }
            } catch (e) {
                placeholder.querySelector('.mz-ai-msg-body').textContent = 'تعذّر الاتصال بالخادم.';
                placeholder.classList.add('mz-ai-msg-error');
            } finally {
                sendBtn.disabled = false;
                sendBtn.textContent = 'إرسال';
                input.focus();
            }
        }
        window.sendAiMessage = sendAiMessage;

        function askAi(question) {
            const input = document.getElementById('ai-input');
            if (input) {
                input.value = question;
                sendAiMessage();
            }
        }
        window.askAi = askAi;

        // Send on Enter (Shift+Enter for newline)
        const aiInput = document.getElementById('ai-input');
        if (aiInput) {
            aiInput.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendAiMessage();
                }
            });
        }

        // ═══ In-document search with highlight ═══
        const docSearchInput = document.getElementById('doc-search');
        const docSearchStats = document.getElementById('doc-search-stats');
        let searchTimer = null;
        let searchHighlights = [];

        function clearSearchHighlights() {
            searchHighlights.forEach(el => {
                const parent = el.parentNode;
                if (parent) {
                    parent.replaceChild(document.createTextNode(el.textContent), el);
                    parent.normalize();
                }
            });
            searchHighlights = [];
            if (docSearchStats) { docSearchStats.style.display = 'none'; }
        }

        function highlightSearch(query) {
            clearSearchHighlights();
            if (!query || query.length < 2) return;
            const articles = document.querySelectorAll('.mz-article-body');
            let total = 0;
            articles.forEach(el => {
                const walker = document.createTreeWalker(el, NodeFilter.SHOW_TEXT, null, false);
                const textNodes = [];
                while (walker.nextNode()) textNodes.push(walker.currentNode);
                textNodes.forEach(node => {
                    const text = node.nodeValue;
                    const qLower = query.toLowerCase();
                    let idx = text.toLowerCase().indexOf(qLower);
                    if (idx === -1) return;
                    const frag = document.createDocumentFragment();
                    let last = 0;
                    while (idx !== -1) {
                        frag.appendChild(document.createTextNode(text.slice(last, idx)));
                        const mark = document.createElement('mark');
                        mark.className = 'mz-search-hl';
                        mark.textContent = text.slice(idx, idx + query.length);
                        frag.appendChild(mark);
                        searchHighlights.push(mark);
                        total++;
                        last = idx + query.length;
                        idx = text.toLowerCase().indexOf(qLower, last);
                    }
                    frag.appendChild(document.createTextNode(text.slice(last)));
                    node.parentNode.replaceChild(frag, node);
                });
            });
            if (docSearchStats) {
                docSearchStats.style.display = 'block';
                docSearchStats.textContent = total > 0 ? total + ' نتيجة' : 'لا توجد نتائج';
            }
            if (searchHighlights.length > 0) {
                searchHighlights[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }

        if (docSearchInput) {
            docSearchInput.addEventListener('input', () => {
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => highlightSearch(docSearchInput.value.trim()), 300);
            });
        }

        // ═══ AI Explain article (inline) ═══
        async function explainArticle(chunkIndex, label) {
            const target = document.getElementById('ai-explain-' + chunkIndex);
            if (!target) return;
            const section = document.getElementById('chunk-' + chunkIndex);
            const body = section?.querySelector('.mz-article-body')?.innerText || '';
            if (!body) return;
            if (target.style.display !== 'none' && target.innerHTML.trim() !== '') {
                target.style.display = 'none';
                return;
            }
            target.style.display = 'block';
            target.innerHTML = '<div style="text-align:center;color:var(--mute);padding:12px">⏳ جاري شرح المادة...</div>';
            try {
                const convId = await ensureAiConversation();
                const prompt = 'اشرح لي "' + label + '" التالية باللغة العربية فقط. اشرحها بشكل مبسط وواضح مع ذكر الأثر القانوني العملي. لا تستخدم اللغة الإنجليزية أبداً في إجابتك:\n\n' + body.substring(0, 3000);
                const resp = await fetch('/ai/conversations/' + convId + '/messages', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                    body: JSON.stringify({ content: prompt }),
                });
                const data = await resp.json();
                if (!resp.ok) {
                    target.innerHTML = '<div class="mz-ai-explain-content mz-ai-explain-error">⚠ ' + escapeHtml(data.error || 'حدث خطأ') + '</div>';
                } else {
                    target.innerHTML = '<div class="mz-ai-explain-content"><div class="mz-ai-explain-head">🤖 شرح المادة</div><div class="mz-ai-explain-body">' + escapeHtml(data.assistant_message.content) + '</div><button type="button" class="mz-art-btn" onclick="this.closest(\'.mz-ai-explain\').style.display=\'none\'" style="margin-top:8px">إغلاق</button></div>';
                }
            } catch (e) {
                target.innerHTML = '<div class="mz-ai-explain-content mz-ai-explain-error">⚠ تعذّر الاتصال</div>';
            }
        }
        window.explainArticle = explainArticle;

        // ═══ Relation autocomplete (for the "ربط مستند" modal) ═══
        const indexLinks = document.querySelectorAll('.mz-index-item');
        const articleSections = document.querySelectorAll('.mz-article');
        if (indexLinks.length && articleSections.length && 'IntersectionObserver' in window) {
            const linksByTarget = new Map();
            indexLinks.forEach(a => linksByTarget.set(a.dataset.target, a));
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    const link = linksByTarget.get(entry.target.id);
                    if (!link) return;
                    if (entry.isIntersecting) {
                        indexLinks.forEach(l => l.classList.remove('active'));
                        link.classList.add('active');
                    }
                });
            }, { rootMargin: '-20% 0px -70% 0px', threshold: 0 });
            articleSections.forEach(s => observer.observe(s));
        }
    </script>

    <style>
        /* Document page layout */
        .mz-doc-layout {
            display: grid;
            grid-template-columns: 240px 1fr 280px;
            gap: 16px;
        }
        @media (max-width: 1200px) {
            .mz-doc-layout { grid-template-columns: 220px 1fr; }
            .mz-doc-side { display: none; }
        }
        @media (max-width: 900px) {
            .mz-doc-layout { grid-template-columns: 1fr; }
            .mz-doc-index { display: none; }
        }
        .mz-doc-main { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

        /* Article index sidebar */
        .mz-index-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 7px 10px;
            border-radius: 6px;
            text-decoration: none;
            color: var(--dim);
            font-size: 12px;
            transition: all .15s;
        }
        .mz-index-item:hover { background: var(--card2); color: var(--cream); }
        .mz-index-item.active {
            background: rgba(200,169,75,.12);
            color: var(--gold);
            font-weight: 700;
        }
        .mz-index-item.active .mz-index-num { background: var(--gold); color: var(--card); }
        .mz-index-num {
            width: 22px; height: 22px; border-radius: 50%;
            background: var(--card2); color: var(--mute);
            display: flex; align-items: center; justify-content: center;
            font-size: 10px; font-weight: 800; flex-shrink: 0;
            border: 1px solid var(--borderl);
        }
        .mz-index-label { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .mz-index-badge {
            background: rgba(200,169,75,.18);
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 8px;
        }

        /* Visibility selector in selection toolbar */
        .mz-sel-vis {
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 6px;
            color: var(--cream);
            font-family: inherit;
            font-size: 11px;
            padding: 4px 6px;
            cursor: pointer;
        }
        .mz-sel-vis:focus { border-color: var(--gold); outline: none; }

        /* In-document search highlight */
        .mz-search-hl {
            background: rgba(200,169,75,.35);
            color: var(--cream);
            padding: 1px 2px;
            border-radius: 3px;
        }

        /* AI explain inline block */
        .mz-ai-explain-content {
            margin-top: 12px;
            padding: 14px 16px;
            background: rgba(200,169,75,.06);
            border: 1px solid rgba(200,169,75,.25);
            border-radius: 10px;
        }
        .mz-ai-explain-error {
            background: rgba(224,85,85,.06);
            border-color: rgba(224,85,85,.3);
        }
        .mz-ai-explain-head {
            font-size: 12px;
            font-weight: 800;
            color: var(--gold);
            margin-bottom: 8px;
        }
        .mz-ai-explain-body {
            font-size: 13px;
            color: var(--cream);
            line-height: 1.85;
            white-space: pre-wrap;
        }

        /* AI explain button special style */
        .mz-art-btn-ai {
            color: var(--gold);
            border-color: rgba(200,169,75,.4);
            background: rgba(200,169,75,.08);
        }
        .mz-art-btn-ai:hover { background: rgba(200,169,75,.18); }

        /* Tabs */
        .mz-tabs {
            display: flex;
            gap: 4px;
            padding: 8px 8px 0;
            border-bottom: 1px solid var(--borderl);
            flex-wrap: wrap;
        }
        .mz-tab {
            background: transparent;
            border: none;
            color: var(--dim);
            font-family: inherit;
            font-size: 13px;
            font-weight: 600;
            padding: 10px 14px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all .15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .mz-tab:hover { color: var(--cream); }
        .mz-tab.active {
            color: var(--gold);
            border-bottom-color: var(--gold);
        }
        .mz-tab-badge {
            background: rgba(200,169,75,.18);
            color: var(--gold);
            font-size: 10px;
            padding: 1px 6px;
            border-radius: 8px;
            min-width: 16px;
            text-align: center;
        }
        [x-cloak] { display: none !important; }

        /* Article sections */
        .mz-article {
            margin-bottom: 18px;
            padding: 14px 16px;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 10px;
            scroll-margin-top: 80px;
        }
        .mz-article:target { border-color: var(--gold); box-shadow: 0 0 0 2px rgba(200,169,75,.18); }
        .mz-article-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px dashed var(--borderl);
            flex-wrap: wrap;
            gap: 8px;
        }
        .mz-article-label {
            font-size: 13px;
            font-weight: 800;
            color: var(--gold);
        }
        .mz-article-actions { display: flex; gap: 6px; }
        .mz-art-btn {
            background: transparent;
            border: 1px solid var(--borderl);
            border-radius: 6px;
            padding: 4px 8px;
            font-size: 11px;
            color: var(--dim);
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }
        .mz-art-btn:hover { background: var(--card); color: var(--cream); border-color: var(--gold); }
        .mz-art-btn-warn {
            color: #e0c878;
            border-color: rgba(224,200,120,.4);
            background: rgba(224,200,120,.08);
        }
        .mz-art-btn-warn:hover { background: rgba(224,200,120,.18); }
        .mz-article-body {
            font-family: 'Amiri', serif;
            font-size: 15px;
            color: var(--cream);
            line-height: 2.0;
            white-space: pre-wrap;
        }

        /* Source tab metadata */
        .mz-meta {
            display: grid;
            grid-template-columns: 140px 1fr;
            gap: 10px 16px;
            margin: 0;
            font-size: 13px;
        }
        .mz-meta dt {
            color: var(--mute);
            font-weight: 700;
        }
        .mz-meta dd {
            color: var(--cream);
            margin: 0;
        }

        /* Notice banners (OCR pending/failed) */
        .mz-notice {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            border: 1px solid;
        }
        .mz-notice-warn {
            background: rgba(200,169,75,.08);
            border-color: rgba(200,169,75,.35);
        }
        .mz-notice-warn .mz-notice-title { color: #e0c878; }
        .mz-notice-error {
            background: rgba(224,85,85,.08);
            border-color: rgba(224,85,85,.4);
        }
        .mz-notice-error .mz-notice-title { color: #ff8a8a; }
        .mz-notice-title { font-size: 13px; font-weight: 700; margin-bottom: 4px; }
        .mz-notice-body { font-size: 12px; color: var(--dim); line-height: 1.7; }

        /* Related items list */
        .mz-rel-item-wrap {
            display: flex;
            gap: 6px;
            align-items: stretch;
            margin-bottom: 6px;
        }
        .mz-rel-item-wrap .mz-rel-item { flex: 1; margin-bottom: 0; }
        .mz-rel-del {
            display: flex;
            align-items: center;
        }
        .mz-rel-del button {
            background: transparent;
            border: 1px solid var(--borderl);
            border-radius: 8px;
            color: var(--red);
            cursor: pointer;
            padding: 0 12px;
            font-size: 14px;
            transition: all .15s;
        }
        .mz-rel-del button:hover { border-color: var(--red); background: rgba(224,85,85,.08); }
        .mz-rel-item {
            display: block;
            padding: 10px 12px;
            margin-bottom: 6px;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
            transition: all .15s;
        }
        .mz-rel-item:hover { border-color: var(--gold); background: var(--card); }
        .mz-rel-type {
            display: inline-block;
            background: rgba(200,169,75,.15);
            color: var(--gold);
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 6px;
            margin-left: 8px;
        }
        .mz-rel-title { font-size: 13px; font-weight: 600; color: var(--cream); }
        .mz-rel-note { font-size: 11px; color: var(--mute); margin-top: 4px; }

        /* AI chat panel */
        .mz-ai-messages {
            min-height: 300px;
            max-height: 500px;
            overflow-y: auto;
            padding: 12px;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 8px;
            margin-bottom: 10px;
        }
        .mz-ai-msg {
            margin-bottom: 12px;
            padding: 10px 14px;
            border-radius: 10px;
            line-height: 1.7;
        }
        .mz-ai-msg-user {
            background: rgba(200,169,75,.08);
            border: 1px solid rgba(200,169,75,.3);
            margin-left: 40px;
        }
        .mz-ai-msg-assistant {
            background: var(--card);
            border: 1px solid var(--borderl);
            margin-right: 40px;
        }
        .mz-ai-msg-error {
            background: rgba(224,85,85,.08);
            border-color: rgba(224,85,85,.4);
        }
        .mz-ai-msg-role {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            color: var(--mute);
            margin-bottom: 4px;
        }
        .mz-ai-msg-body {
            font-size: 14px;
            color: var(--cream);
            white-space: pre-wrap;
        }
        .mz-ai-input {
            display: flex;
            gap: 8px;
            align-items: stretch;
        }
        .mz-ai-input textarea {
            flex: 1;
            resize: vertical;
        }
        .mz-ai-input button {
            min-width: 90px;
        }
        .mz-chip {
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 14px;
            padding: 6px 12px;
            font-size: 12px;
            color: var(--cream);
            cursor: pointer;
            font-family: inherit;
            transition: all .15s;
        }
        .mz-chip:hover { border-color: var(--gold); background: var(--card); color: var(--gold); }

        /* Section labels in analysis tab */
        .mz-section-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--mute);
            text-transform: uppercase;
            letter-spacing: .06em;
            margin-bottom: 8px;
        }

        /* Risk cards in contract analysis */
        .mz-risk-card {
            padding: 12px 14px;
            margin-bottom: 8px;
            border-radius: 8px;
            border-right: 4px solid;
            background: var(--card2);
        }
        .mz-risk-low { border-right-color: #3dbf8a; background: rgba(61,191,138,.05); }
        .mz-risk-med { border-right-color: #c8a94b; background: rgba(200,169,75,.05); }
        .mz-risk-high { border-right-color: #e05555; background: rgba(224,85,85,.06); }
        .mz-risk-badge {
            font-size: 10px;
            font-weight: 800;
            padding: 2px 8px;
            border-radius: 8px;
            background: rgba(255,255,255,.06);
            color: var(--cream);
        }
        .mz-risk-low .mz-risk-badge { color: #3dbf8a; }
        .mz-risk-med .mz-risk-badge { color: #c8a94b; }
        .mz-risk-high .mz-risk-badge { color: #ff8a8a; }

        /* Autocomplete dropdown for the relation modal */
        .mz-autocomplete-list {
            position: absolute;
            top: 100%;
            right: 0;
            left: 0;
            margin-top: 4px;
            background: var(--card);
            border: 1px solid var(--borderl);
            border-radius: 8px;
            max-height: 240px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 0 4px 18px rgba(0,0,0,.4);
        }
        .mz-autocomplete-item {
            padding: 10px 12px;
            border-bottom: 1px solid var(--borderl);
            cursor: pointer;
            transition: background .12s;
        }
        .mz-autocomplete-item:last-child { border-bottom: none; }
        .mz-autocomplete-item:hover { background: var(--card2); }
        .mz-autocomplete-title { font-size: 13px; color: var(--cream); font-weight: 600; }
        .mz-autocomplete-meta { font-size: 11px; color: var(--mute); margin-top: 2px; }
        .mz-autocomplete-empty {
            padding: 16px;
            text-align: center;
            color: var(--mute);
            font-size: 12px;
        }

        /* Timeline */
        .mz-timeline {
            list-style: none;
            padding: 0;
            margin: 0;
            position: relative;
        }
        .mz-timeline::before {
            content: '';
            position: absolute;
            top: 12px; bottom: 12px;
            right: 18px;
            width: 2px;
            background: var(--borderl);
        }
        .mz-timeline-item {
            position: relative;
            display: flex;
            gap: 12px;
            padding: 0 0 18px 0;
            margin-right: 0;
        }
        .mz-timeline-icon {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: var(--card2);
            border: 2px solid var(--borderl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            flex-shrink: 0;
            z-index: 1;
        }
        .mz-timeline-update .mz-timeline-icon { border-color: rgba(200,169,75,.5); }
        .mz-timeline-version .mz-timeline-icon { border-color: rgba(61,130,255,.5); }
        .mz-timeline-content {
            flex: 1;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 8px;
            padding: 10px 14px;
            min-width: 0;
        }
        .mz-timeline-head {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 6px;
            flex-wrap: wrap;
        }
        .mz-timeline-title {
            font-size: 13px;
            font-weight: 700;
            color: var(--cream);
        }
        .mz-timeline-date {
            font-size: 11px;
            color: var(--mute);
            margin-right: auto;
        }
        .mz-timeline-body {
            font-size: 12px;
            color: var(--dim);
            line-height: 1.7;
        }
    </style>
</x-app-layout>
