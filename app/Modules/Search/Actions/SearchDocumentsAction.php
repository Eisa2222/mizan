<?php

namespace Modules\Search\Actions;

use App\Services\ElasticsearchService;
use App\Services\SemanticSearchService;
use Modules\Search\Queries\DatabaseSearchQuery;

/**
 * Orchestrates the three-tier search fallback:
 *   1. Semantic re-ranking (only if `ai=true` and ES is reachable)
 *   2. Elasticsearch keyword match
 *   3. Normalized SQL LIKE over document_chunks
 *
 * Returns an envelope `{engine, query, ...payload}` that the controller can
 * emit directly as JSON.
 */
class SearchDocumentsAction
{
    public function __construct(
        private readonly ElasticsearchService $elasticsearch,
        private readonly SemanticSearchService $semantic,
        private readonly DatabaseSearchQuery $databaseSearch,
    ) {
    }

    public function execute(string $query, array $filters, int $page, int $size, bool $useAi): array
    {
        if ($useAi) {
            $semanticResult = $this->semantic->search($query, $filters, $page, $size);
            if ($semanticResult !== null) {
                return ['engine' => 'semantic', 'query' => $query, ...$semanticResult];
            }
        }

        $esResult = $this->elasticsearch->search($query, $filters, $page, $size);
        if ($esResult !== null) {
            return ['engine' => 'elasticsearch', 'query' => $query, ...$esResult];
        }

        return [
            'engine' => 'database',
            'query'  => $query,
            ...$this->databaseSearch->run($query, $filters, $page, $size),
        ];
    }
}
