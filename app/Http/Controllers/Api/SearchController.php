<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DocumentChunk;
use App\Models\LegalDocument;
use App\Services\ArabicTextNormalizerService;
use App\Services\ElasticsearchService;
use App\Services\SemanticSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Search API for legal documents.
 *
 * GET /api/v1/search?q=...&type=&from=&to=&page=&size=
 *
 * Returns a JSON envelope with results from Elasticsearch when available,
 * otherwise falls back to a normalized SQL LIKE search on document_chunks.
 */
class SearchController extends Controller
{
    public function __construct(
        private ElasticsearchService $es,
        private ArabicTextNormalizerService $normalizer,
        private SemanticSearchService $semantic,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'q'     => 'required|string|min:2|max:300',
            'type'  => 'nullable|integer|between:1,7',
            'from'  => 'nullable|date',
            'to'    => 'nullable|date',
            'page'  => 'nullable|integer|min:1|max:100',
            'size'  => 'nullable|integer|min:1|max:50',
            'ai'    => 'nullable|boolean',
        ]);

        $query = $data['q'];
        $page = (int) ($data['page'] ?? 1);
        $size = (int) ($data['size'] ?? 20);
        $useAi = filter_var($data['ai'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $filters = [
            'org_id' => $request->user()?->org_id,
            'type'   => $data['type'] ?? null,
            'from'   => $data['from'] ?? null,
            'to'     => $data['to'] ?? null,
        ];

        // 1. Semantic re-ranking (opt-in via ?ai=1)
        if ($useAi) {
            $semanticResult = $this->semantic->search($query, $filters, $page, $size);
            if ($semanticResult !== null) {
                return response()->json([
                    'engine' => 'semantic',
                    'query'  => $query,
                    ...$semanticResult,
                ]);
            }
            // Semantic returned null only when ES itself was unreachable —
            // fall through to the DB path below.
        }

        // 2. Try Elasticsearch first
        $esResult = $this->es->search($query, $filters, $page, $size);

        if ($esResult !== null) {
            return response()->json([
                'engine' => 'elasticsearch',
                'query'  => $query,
                ...$esResult,
            ]);
        }

        // 3. Fallback: normalized DB search
        return response()->json([
            'engine' => 'database',
            'query'  => $query,
            ...$this->databaseSearch($query, $filters, $page, $size),
        ]);
    }

    private function databaseSearch(string $query, array $filters, int $page, int $size): array
    {
        $normalized = $this->normalizer->normalize($query);
        $tokens = array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            fn ($t) => mb_strlen($t) >= 2
        );

        $q = DocumentChunk::query()
            ->join('legal_documents', 'legal_documents.id', '=', 'document_chunks.document_id')
            ->select([
                'document_chunks.document_id',
                'document_chunks.chunk_index',
                'document_chunks.label',
                'document_chunks.content',
                'legal_documents.title',
                'legal_documents.type',
                'legal_documents.issued_at',
            ]);

        if (!empty($filters['org_id'])) {
            $q->where('legal_documents.org_id', $filters['org_id']);
        }
        if (!empty($filters['type'])) {
            $q->where('legal_documents.type', $filters['type']);
        }
        if (!empty($filters['from'])) {
            $q->where('legal_documents.issued_at', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $q->where('legal_documents.issued_at', '<=', $filters['to']);
        }

        // Match on normalized text against any token
        $q->where(function ($w) use ($tokens, $normalized) {
            $w->where('document_chunks.normalized', 'like', "%{$normalized}%");
            foreach ($tokens as $tok) {
                $w->orWhere('document_chunks.normalized', 'like', "%{$tok}%");
            }
        });

        $total = (clone $q)->count();

        $rows = $q->orderBy('document_chunks.id', 'desc')
            ->skip(($page - 1) * $size)
            ->take($size)
            ->get();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'hits'  => $rows->map(function ($r) use ($normalized) {
                $snippet = $this->makeSnippet($r->content, $normalized);
                return [
                    'document_id' => (int) $r->document_id,
                    'chunk_index' => (int) $r->chunk_index,
                    'title'       => $r->title,
                    'type'        => (int) $r->type,
                    'label'       => $r->label,
                    'snippet'     => $snippet,
                    'score'       => 1.0, // DB doesn't compute relevance
                ];
            })->toArray(),
        ];
    }

    private function makeSnippet(string $content, string $needle, int $window = 200): string
    {
        if ($needle === '') return mb_substr($content, 0, $window);
        $pos = mb_stripos($content, mb_strtok($needle, ' '));
        if ($pos === false) return mb_substr($content, 0, $window);
        $start = max(0, $pos - 60);
        return ($start > 0 ? '… ' : '') . mb_substr($content, $start, $window);
    }
}
