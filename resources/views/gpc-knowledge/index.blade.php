<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📚 قاعدة المعرفة - نظام المنافسات والمشتريات</div>
                <div class="mz-page-sub">
                    النصوص الرسمية المعتمدة لمرجعية مراجعات الكراسات والذكاء الاصطناعي
                </div>
            </div>
            <div style="text-align:left">
                <div style="font-size:13px;color:var(--cream);font-weight:700">{{ $articles->total() }} مادة مفهرسة</div>
                <div style="font-size:11px;color:var(--mute)">المرسوم الملكي م/128 + اللائحة 1242</div>
            </div>
        </div>

        {{-- Source filter --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
            <a href="{{ route('gpc-knowledge.index') }}"
               class="mz-chip {{ ! request('source') ? 'active' : '' }}">
                الكل ({{ array_sum($totalsBySource) }})
            </a>
            @foreach ($sources as $key => $label)
                <a href="{{ route('gpc-knowledge.index', ['source' => $key]) }}"
                   class="mz-chip {{ request('source') === $key ? 'active' : '' }}">
                    {{ $label }} ({{ $totalsBySource[$key] ?? 0 }})
                </a>
            @endforeach
        </div>

        {{-- Search --}}
        <form method="GET" action="{{ route('gpc-knowledge.index') }}" style="margin-bottom:16px">
            @if (request('source'))
                <input type="hidden" name="source" value="{{ request('source') }}">
            @endif
            <input type="text" name="q" value="{{ request('q') }}" placeholder="🔍 بحث في النصوص..." class="mz-inp" style="max-width:400px">
        </form>

        {{-- Articles --}}
        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse ($articles as $article)
                <div class="mz-card">
                    <div class="mz-card-body" style="padding:14px 18px">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:8px;flex-wrap:wrap">
                            <div>
                                <div style="font-size:14px;font-weight:700;color:var(--gold)">{{ $article->article_label }}</div>
                                @if ($article->topic)
                                    <div style="font-size:12px;color:var(--cream);margin-top:2px">{{ $article->topic }}</div>
                                @endif
                            </div>
                            <span class="mz-badge mz-badge-grey" style="font-size:10px">{{ $sources[$article->source] ?? $article->source }}</span>
                        </div>

                        @if ($article->chapter)
                            <div style="font-size:11px;color:var(--mute);margin-bottom:8px">📑 {{ $article->chapter }}</div>
                        @endif

                        <div style="font-size:13px;color:var(--cream);line-height:1.85;white-space:pre-wrap">{{ $article->content }}</div>

                        @if (! empty($article->keywords))
                            <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:10px">
                                @foreach ($article->keywords as $kw)
                                    <span style="font-size:10px;background:rgba(200,169,75,.1);color:var(--gold);padding:2px 8px;border-radius:8px">{{ $kw }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="mz-card">
                    <div class="mz-card-body" style="text-align:center;padding:48px;color:var(--mute)">
                        <div style="font-size:48px;margin-bottom:12px">📚</div>
                        <div>لا توجد نتائج مطابقة</div>
                    </div>
                </div>
            @endforelse
        </div>

        @if ($articles->hasPages())
            <div style="margin-top:16px">{{ $articles->withQueryString()->links() }}</div>
        @endif
    </div>

    <style>
        .mz-chip {
            display: inline-block;
            padding: 6px 14px;
            background: var(--card2);
            border: 1px solid var(--borderl);
            border-radius: 20px;
            font-size: 12px;
            color: var(--cream);
            text-decoration: none;
            transition: all .15s;
        }
        .mz-chip:hover { border-color: var(--gold); }
        .mz-chip.active { background: var(--gold); color: var(--card); border-color: var(--gold); font-weight: 700; }
    </style>
</x-app-layout>
