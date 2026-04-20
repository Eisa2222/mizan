<?php

namespace Modules\Assistant\Queries;

use App\Models\DocumentChunk;
use App\Services\ArabicTextNormalizerService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Retrieves the top-K chunks of a document that look relevant to a user's
 * query via normalized LIKE matching. Used as the RAG retrieval step for
 * documents too large to inline wholesale into the prompt.
 */
class TopDocumentChunksForQueryQuery
{
    public function __construct(private readonly ArabicTextNormalizerService $normalizer)
    {
    }

    public function run(int $documentId, string $query, int $topK = 6): Collection
    {
        $normalized = $this->normalizer->normalize($query);
        $tokens = collect(preg_split('/\s+/u', $normalized) ?: [])
            ->filter(fn ($token) => mb_strlen($token) >= 2)
            ->take(8)
            ->values();

        $builder = DocumentChunk::query()->where('document_id', $documentId);

        if ($tokens->isEmpty()) {
            return $builder->orderBy('chunk_index')->limit($topK)->get();
        }

        $builder->where(function ($scope) use ($tokens) {
            foreach ($tokens as $token) {
                $scope->orWhere('normalized', 'like', "%{$token}%");
            }
        });

        return $builder->orderBy('chunk_index')->limit($topK)->get();
    }
}
