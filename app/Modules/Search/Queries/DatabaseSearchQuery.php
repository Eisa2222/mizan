<?php

namespace Modules\Search\Queries;

use App\Models\DocumentChunk;
use App\Services\ArabicTextNormalizerService;

/**
 * Plain-SQL fallback search over document_chunks.normalized. Used only when
 * both semantic and Elasticsearch engines are unavailable, so correctness
 * matters more than relevance ranking here.
 */
class DatabaseSearchQuery
{
    public function __construct(private readonly ArabicTextNormalizerService $normalizer)
    {
    }

    public function run(string $query, array $filters, int $page, int $size): array
    {
        $normalized = $this->normalizer->normalize($query);
        $tokens = array_filter(
            preg_split('/\s+/u', $normalized) ?: [],
            fn ($t) => mb_strlen($t) >= 2,
        );

        $builder = DocumentChunk::query()
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

        if (! empty($filters['org_id'])) {
            $builder->where('legal_documents.org_id', $filters['org_id']);
        }
        if (! empty($filters['type'])) {
            $builder->where('legal_documents.type', $filters['type']);
        }
        if (! empty($filters['from'])) {
            $builder->where('legal_documents.issued_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $builder->where('legal_documents.issued_at', '<=', $filters['to']);
        }

        $builder->where(function ($scope) use ($tokens, $normalized) {
            $scope->where('document_chunks.normalized', 'like', "%{$normalized}%");
            foreach ($tokens as $token) {
                $scope->orWhere('document_chunks.normalized', 'like', "%{$token}%");
            }
        });

        $total = (clone $builder)->count();

        $rows = $builder
            ->orderBy('document_chunks.id', 'desc')
            ->skip(($page - 1) * $size)
            ->take($size)
            ->get();

        return [
            'total' => $total,
            'page'  => $page,
            'size'  => $size,
            'hits'  => $rows->map(fn ($row) => [
                'document_id' => (int) $row->document_id,
                'chunk_index' => (int) $row->chunk_index,
                'title'       => $row->title,
                'type'        => (int) $row->type,
                'label'       => $row->label,
                'snippet'     => $this->makeSnippet($row->content, $normalized),
                'score'       => 1.0,
            ])->toArray(),
        ];
    }

    private function makeSnippet(string $content, string $needle, int $window = 200): string
    {
        if ($needle === '') {
            return mb_substr($content, 0, $window);
        }

        $firstToken = preg_split('/\s+/u', trim($needle))[0] ?? $needle;
        $position = mb_stripos($content, $firstToken);

        if ($position === false) {
            return mb_substr($content, 0, $window);
        }

        $start = max(0, $position - 60);

        return ($start > 0 ? '… ' : '') . mb_substr($content, $start, $window);
    }
}
