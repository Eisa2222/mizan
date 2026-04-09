<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * SemanticSearchService
 * ─────────────────────
 * Wraps ElasticsearchService::search() to add a Claude-powered re-ranking
 * step. Returns the same shape as the underlying search so the
 * SearchController can swap engines transparently.
 *
 * Strategy:
 *   1. Pull a wide BM25 candidate set from Elasticsearch (top 30).
 *   2. Send the query + the candidates to Claude with a JSON-only prompt
 *      asking it to rank them and provide a short relevance reasoning.
 *   3. Merge the model's reasoning back into each hit and return the
 *      reordered list trimmed to the requested page size.
 *
 * Graceful degradation: if Claude isn't configured OR the call/parse fails,
 * we fall back to the BM25 order — search still works, it just isn't
 * re-ranked.
 */
class SemanticSearchService
{
    /** How many candidates to pull from ES before re-ranking. */
    private const CANDIDATE_POOL = 30;

    /** Cap on snippet length sent to Claude — keeps token usage reasonable. */
    private const MAX_SNIPPET_CHARS = 600;

    public function __construct(
        private ElasticsearchService $es,
        private ClaudeService $claude,
    ) {}

    public function isAvailable(): bool
    {
        return $this->claude->isConfigured() && $this->es->isAvailable();
    }

    /**
     * Run a search and re-rank the results with Claude.
     *
     * Returns null when ES is unreachable so the caller can fall back to the
     * DB search path (same convention as ElasticsearchService::search).
     *
     * @return array{total:int, page:int, size:int, hits:array<int,array>, engine_note?:string}|null
     */
    public function search(string $query, array $filters, int $page, int $size): ?array
    {
        // Always pull a wide pool from ES (page 1, large size) regardless of
        // the user's requested page so we have the full re-ranking surface.
        $esResult = $this->es->search($query, $filters, page: 1, size: self::CANDIDATE_POOL);
        if ($esResult === null) {
            return null; // ES unreachable — caller falls back to DB
        }

        $hits = $esResult['hits'] ?? [];

        // No candidates or AI unavailable: return BM25 order, just paginated.
        if (empty($hits) || ! $this->claude->isConfigured()) {
            return $this->paginate($esResult, $page, $size, note: $this->claude->isConfigured() ? null : 'AI غير مكوّن — تم استخدام ترتيب BM25.');
        }

        try {
            $ranked = $this->rerank($query, $hits);
            return $this->paginate(
                ['total' => count($ranked), 'hits' => $ranked],
                $page,
                $size,
                note: 'تم إعادة الترتيب بواسطة Claude'
            );
        } catch (\Throwable $e) {
            Log::warning('SemanticSearchService: rerank failed, falling back to BM25', [
                'error' => $e->getMessage(),
            ]);
            return $this->paginate($esResult, $page, $size, note: 'تعذّر إعادة الترتيب — تم استخدام ترتيب BM25.');
        }
    }

    /**
     * Ask Claude to re-rank the candidate hits and return the merged result.
     *
     * @param  array<int, array>  $hits
     * @return array<int, array>
     */
    private function rerank(string $query, array $hits): array
    {
        // Build a compact candidate list for the prompt
        $candidates = [];
        foreach ($hits as $i => $hit) {
            $snippet = mb_substr((string) ($hit['snippet'] ?? $hit['chunk_text'] ?? ''), 0, self::MAX_SNIPPET_CHARS);
            $candidates[] = [
                'i'       => $i,
                'title'   => $hit['title'] ?? '',
                'label'   => $hit['label'] ?? null,
                'snippet' => $snippet,
            ];
        }

        $system = <<<'PROMPT'
أنت مُقيّم نتائج بحث قانوني عربي. ستتلقى سؤال المستخدم وقائمة من المقاطع المرشّحة من قاعدة بيانات قانونية.
مهمتك: أعد ترتيب المقاطع وفق صلتها الحقيقية بالسؤال (الأكثر صلة أولاً)، واختر أفضل 10 فقط.

أرجع كائن JSON بالشكل التالي بالضبط:
{
  "ranked": [
    {"i": <رقم المقطع كما ورد>, "score": <0-100>, "reason": "<شرح موجز بالعربية>"},
    ...
  ]
}

لا ترجع أي مقاطع غير صالحة. إذا لم يكن أي مقطع ذا صلة، أرجع قائمة فارغة.
PROMPT;

        $userMessage = "السؤال: \"{$query}\"\n\nالمرشحون:\n" . json_encode($candidates, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        $result = $this->claude->chatJson(
            messages: [['role' => 'user', 'content' => $userMessage]],
            system: $system,
            maxTokens: 1500,
        );

        $ranked = $result['data']['ranked'] ?? [];
        if (! is_array($ranked)) {
            throw new RuntimeException('Claude rerank: missing or invalid "ranked" key');
        }

        // Merge reasoning back into the original hits
        $merged = [];
        foreach ($ranked as $entry) {
            $idx = $entry['i'] ?? null;
            if (! is_int($idx) || ! isset($hits[$idx])) continue;
            $hit = $hits[$idx];
            $hit['ai_score'] = (int) ($entry['score'] ?? 0);
            $hit['ai_reason'] = (string) ($entry['reason'] ?? '');
            $merged[] = $hit;
        }

        return $merged;
    }

    /**
     * Slice the hit list to the requested page and add an engine note.
     */
    private function paginate(array $result, int $page, int $size, ?string $note = null): array
    {
        $hits = $result['hits'] ?? [];
        $total = $result['total'] ?? count($hits);

        $offset = ($page - 1) * $size;
        $sliced = array_slice($hits, $offset, $size);

        return [
            'total'       => $total,
            'page'        => $page,
            'size'        => $size,
            'hits'        => $sliced,
            'engine_note' => $note,
        ];
    }
}
