<x-app-layout>
    @section('title', 'قراءة المستند — ' . \Illuminate\Support\Str::limit($document->title, 40))

    {{-- Scoped BoE-inspired reader theme. Lives inside mz-main so it overrides
         the dark admin palette only for this view without touching topbar/sidebar. --}}
    <style>
        .mz-main:has(.mz-reader) {
            background: linear-gradient(180deg, #f7fffc 0%, #f2fbf6 100%);
            color: #1a2e2b;
            overflow-y: auto;
        }

        .mz-reader {
            max-width: 1180px;
            margin: 0 auto;
            padding: 24px 28px 80px;
            font-family: 'Cairo', 'Tajawal', sans-serif;
            font-size: 16px;
            line-height: 1.9;
            color: #1a2e2b;
        }

        .mz-reader a { color: #079247; }
        .mz-reader a:hover { color: #d6a329; }

        .rdr-header {
            background: #fff;
            border: 1px solid rgba(7, 146, 71, 0.15);
            border-radius: 14px;
            padding: 22px 28px;
            margin-bottom: 24px;
            box-shadow: 0 2px 10px rgba(7, 146, 71, 0.06);
        }

        .rdr-breadcrumb {
            display: flex; align-items: center; gap: 8px;
            font-size: 13px; color: #4c6661; margin-bottom: 12px;
        }
        .rdr-breadcrumb a { color: #079247; text-decoration: none; }
        .rdr-breadcrumb a:hover { text-decoration: underline; }

        .rdr-title {
            font-family: 'Cairo', sans-serif;
            font-weight: 700;
            font-size: 26px;
            color: #004d24;
            margin: 0 0 12px;
            line-height: 1.4;
        }

        .rdr-meta {
            display: flex; flex-wrap: wrap; gap: 8px 24px;
            font-size: 13px; color: #4c6661;
        }
        .rdr-meta span { display: inline-flex; align-items: center; gap: 6px; }
        .rdr-meta strong { color: #1a2e2b; font-weight: 600; }

        .rdr-toolbar {
            display: flex; flex-wrap: wrap; gap: 8px;
            margin-top: 16px; padding-top: 16px;
            border-top: 1px dashed rgba(7, 146, 71, 0.15);
        }
        .rdr-tool {
            display: inline-flex; align-items: center; gap: 6px;
            background: #ecfaf0; color: #079247;
            border: 1px solid rgba(7, 146, 71, 0.2);
            border-radius: 8px;
            padding: 6px 14px;
            font-size: 13px; font-weight: 500;
            cursor: pointer; text-decoration: none;
            transition: all .15s;
            font-family: inherit;
        }
        .rdr-tool:hover { background: #079247; color: #fff; }

        .rdr-grid {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 22px;
            align-items: start;
        }
        @media (max-width: 900px) {
            .rdr-grid { grid-template-columns: 1fr; }
            .rdr-toc { position: static !important; max-height: none !important; }
        }

        .rdr-toc {
            background: #fff;
            border: 1px solid rgba(7, 146, 71, 0.15);
            border-radius: 14px;
            padding: 18px 8px 18px 18px;
            position: sticky; top: 16px;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        .rdr-toc h3 {
            margin: 0 0 12px 10px;
            font-size: 14px; font-weight: 700;
            color: #004d24;
            display: flex; align-items: center; gap: 8px;
        }
        .rdr-toc h3::before {
            content: ''; display: block;
            width: 3px; height: 16px; background: #079247; border-radius: 2px;
        }

        .rdr-toc ol {
            list-style: none; padding: 0; margin: 0;
            counter-reset: none;
        }
        .rdr-toc li { margin: 0; }
        .rdr-toc a {
            display: flex; align-items: baseline; gap: 10px;
            padding: 8px 10px;
            border-radius: 8px;
            font-size: 13px;
            color: #1a2e2b;
            text-decoration: none;
            line-height: 1.5;
            transition: all .15s;
        }
        .rdr-toc a:hover { background: #ecfaf0; color: #079247; }
        .rdr-toc a.active { background: #079247; color: #fff; font-weight: 600; }
        .rdr-toc a.active .rdr-toc-num { background: rgba(255,255,255,.25); color: #fff; }

        .rdr-toc-num {
            flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 32px; height: 22px;
            background: #ecfaf0; color: #079247;
            border-radius: 6px;
            font-size: 12px; font-weight: 700;
            font-variant-numeric: tabular-nums;
        }

        .rdr-body { min-width: 0; }

        .rdr-preamble {
            background: #fff;
            border-right: 4px solid #079247;
            border-radius: 10px;
            padding: 20px 26px;
            margin-bottom: 18px;
            white-space: pre-wrap;
            color: #2a3d38;
            font-size: 16px;
        }

        .rdr-article {
            background: #fff;
            border: 1px solid rgba(7, 146, 71, 0.1);
            border-radius: 12px;
            padding: 24px 28px;
            margin-bottom: 16px;
            box-shadow: 0 1px 3px rgba(7, 146, 71, 0.04);
            scroll-margin-top: 20px;
        }

        .rdr-article-head {
            display: flex; align-items: center; gap: 14px;
            padding-bottom: 14px;
            margin-bottom: 16px;
            border-bottom: 1px solid rgba(7, 146, 71, 0.12);
        }
        .rdr-article-num {
            flex-shrink: 0;
            display: inline-flex; align-items: center; justify-content: center;
            min-width: 64px; padding: 8px 14px;
            background: linear-gradient(135deg, #079247, #00da64);
            color: #fff;
            border-radius: 8px;
            font-size: 14px; font-weight: 700;
            letter-spacing: .3px;
        }
        .rdr-article-title {
            font-size: 18px;
            font-weight: 600;
            color: #004d24;
            margin: 0;
            flex: 1;
        }

        .rdr-article-body {
            white-space: pre-wrap;
            font-size: 17px;
            line-height: 2;
            color: #1a2e2b;
            text-align: justify;
            text-justify: inter-word;
        }

        .rdr-empty {
            background: #fff;
            border-radius: 14px;
            padding: 48px 28px;
            text-align: center;
            color: #4c6661;
        }
        .rdr-empty .rdr-empty-icon { font-size: 48px; margin-bottom: 12px; }

        /* Font-size controls (set via JS inline style on .rdr-body) */
        .rdr-body[data-zoom="sm"] .rdr-article-body { font-size: 15px; line-height: 1.85; }
        .rdr-body[data-zoom="md"] .rdr-article-body { font-size: 17px; line-height: 2; }
        .rdr-body[data-zoom="lg"] .rdr-article-body { font-size: 19px; line-height: 2.1; }
        .rdr-body[data-zoom="xl"] .rdr-article-body { font-size: 22px; line-height: 2.2; }

        /* Print: hide nav chrome, flatten colours */
        @media print {
            .mz-topbar, .mz-sidebar, .mz-mobile-toggle, .rdr-toolbar, .rdr-toc { display: none !important; }
            .mz-main { margin: 0 !important; }
            .rdr-grid { grid-template-columns: 1fr; }
            .rdr-article, .rdr-preamble, .rdr-header {
                box-shadow: none; border: 1px solid #ccc; break-inside: avoid;
            }
            .rdr-article-num { background: #079247 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            body { background: #fff !important; }
        }
    </style>

    <div class="mz-reader"
         x-data="{
             zoom: localStorage.getItem('mz-reader-zoom') || 'md',
             active: null,
             setZoom(z) { this.zoom = z; localStorage.setItem('mz-reader-zoom', z); },
             init() {
                 // Sync active TOC entry with scroll position
                 const observer = new IntersectionObserver(entries => {
                     entries.forEach(e => {
                         if (e.isIntersecting) this.active = e.target.id;
                     });
                 }, { rootMargin: '-20% 0px -60% 0px' });
                 this.$nextTick(() => {
                     document.querySelectorAll('.rdr-article').forEach(el => observer.observe(el));
                 });
             }
         }">

        {{-- Header card --}}
        <div class="rdr-header">
            <div class="rdr-breadcrumb">
                <a href="{{ route('documents.index') }}">المستندات</a>
                <span>›</span>
                <a href="{{ route('documents.show', $document) }}">{{ \Illuminate\Support\Str::limit($document->title, 40) }}</a>
                <span>›</span>
                <span>قراءة</span>
            </div>

            <h1 class="rdr-title">{{ $document->title }}</h1>

            <div class="rdr-meta">
                @if ($document->type)
                    <span>📑 <strong>{{ \App\Models\LegalDocument::typeLabel($document->type) }}</strong></span>
                @endif
                @if ($document->kind)
                    <span>🏷 <strong>{{ \App\Models\LegalDocument::kindLabel($document->kind) }}</strong></span>
                @endif
                @if ($document->reference_number)
                    <span>🔢 <strong>{{ $document->reference_number }}</strong></span>
                @endif
                @if ($document->issued_at)
                    <span>📅 <strong>{{ $document->issued_at->format('Y-m-d') }}</strong></span>
                @endif
                @if ($document->source_entity)
                    <span>🏛 <strong>{{ $document->source_entity }}</strong></span>
                @endif
                @if (count($articles) > 0)
                    <span>📜 <strong>{{ count($articles) }} مادة</strong></span>
                @endif
            </div>

            <div class="rdr-toolbar">
                <a href="{{ route('documents.show', $document) }}" class="rdr-tool">← العودة للعرض الكامل</a>
                @if ($document->file_path)
                    <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="rdr-tool">📎 تنزيل الملف الأصلي</a>
                @endif
                <button type="button" class="rdr-tool" onclick="window.print()">🖨️ طباعة</button>
                <span style="margin-inline-start:auto;display:inline-flex;gap:6px;align-items:center;font-size:12px;color:#4c6661">
                    حجم الخط:
                    <button type="button" class="rdr-tool" style="padding:4px 10px" @click="setZoom('sm')" :style="zoom==='sm' ? 'background:#079247;color:#fff' : ''">صغير</button>
                    <button type="button" class="rdr-tool" style="padding:4px 10px" @click="setZoom('md')" :style="zoom==='md' ? 'background:#079247;color:#fff' : ''">متوسط</button>
                    <button type="button" class="rdr-tool" style="padding:4px 10px" @click="setZoom('lg')" :style="zoom==='lg' ? 'background:#079247;color:#fff' : ''">كبير</button>
                    <button type="button" class="rdr-tool" style="padding:4px 10px" @click="setZoom('xl')" :style="zoom==='xl' ? 'background:#079247;color:#fff' : ''">أكبر</button>
                </span>
            </div>
        </div>

        <div class="rdr-grid">
            {{-- Side TOC (only if there are parsed articles) --}}
            @if (count($articles) > 0)
                <nav class="rdr-toc" aria-label="فهرس المواد">
                    <h3>فهرس المواد</h3>
                    <ol>
                        @foreach ($articles as $i => $article)
                            <li>
                                <a href="#article-{{ $i }}"
                                   :class="active === 'article-{{ $i }}' ? 'active' : ''">
                                    <span class="rdr-toc-num">{{ $article['number'] }}</span>
                                    <span>{{ $article['title'] ?? \Illuminate\Support\Str::limit(strip_tags($article['body']), 40) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ol>
                </nav>
            @endif

            <div class="rdr-body" :data-zoom="zoom">
                @if (trim($preamble) !== '')
                    <div class="rdr-preamble">{{ $preamble }}</div>
                @endif

                @forelse ($articles as $i => $article)
                    <article id="article-{{ $i }}" class="rdr-article">
                        <div class="rdr-article-head">
                            <span class="rdr-article-num">المادة {{ $article['number'] }}</span>
                            @if ($article['title'])
                                <h2 class="rdr-article-title">{{ $article['title'] }}</h2>
                            @endif
                        </div>
                        <div class="rdr-article-body">{{ $article['body'] }}</div>
                    </article>
                @empty
                    @if (trim($preamble) === '')
                        <div class="rdr-empty">
                            <div class="rdr-empty-icon">📄</div>
                            <div>لا يوجد نص مستخرج لعرضه بعد.</div>
                            @if ($document->file_path)
                                <div style="margin-top:12px">
                                    <a href="{{ asset('storage/' . $document->file_path) }}" target="_blank" class="rdr-tool">📎 افتح الملف الأصلي</a>
                                </div>
                            @endif
                        </div>
                    @endif
                @endforelse
            </div>
        </div>
    </div>
</x-app-layout>
