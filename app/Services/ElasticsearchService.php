<?php

namespace App\Services;

use App\Models\LegalDocument;
use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Throwable;

/**
 * ElasticsearchService
 * ─────────────────────
 * Wrapper around the Elasticsearch PHP client tailored for Arabic legal search.
 *
 * Capabilities:
 *   • createIndex / deleteIndex / indexExists
 *   • bulkIndexChunks(LegalDocument)         — bulk-index all chunks of a doc
 *   • reindexDocument(LegalDocument)         — re-chunk + re-index one doc
 *   • reindexAll(?int $orgId)                — re-chunk + re-index everything
 *   • search(query, filters, page, size)     — full-text search with highlights
 *
 * Mapping is built for the suggested fields:
 *   title (text+keyword), document_type (keyword), source_entity (keyword),
 *   issue_date (date), article_number (keyword), chunk_text (text),
 *   normalized_text (text), metadata (object), document_id (keyword).
 *
 * Analyzer strategy:
 *   1. Try a rich Arabic analyzer (arabic_normalization + arabic_stemmer + stop words)
 *   2. If ES rejects the mapping (e.g. plugin missing), fall back to a baseline
 *      mapping that uses the standard analyzer. Either way, indexing keeps working.
 *
 * Graceful degradation: every method short-circuits to a safe value if ES is
 * unreachable, so the rest of the app keeps working with the DB fallback.
 */
class ElasticsearchService
{
    private ?Client $client = null;
    private bool $available = false;
    public string $index;

    public function __construct(private DocumentChunker $chunker)
    {
        $this->index = config('services.elasticsearch.index', 'mizaan_legal');

        try {
            $hosts = [config('services.elasticsearch.host', 'http://localhost:9200')];
            $this->client = ClientBuilder::create()->setHosts($hosts)->build();
            $this->available = $this->client->ping()->asBool();
        } catch (Throwable $e) {
            $this->available = false;
        }
    }

    // ───────────────────────────────────────────────
    // Health
    // ───────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function indexExists(): bool
    {
        if (!$this->available) return false;
        try {
            return $this->client->indices()->exists(['index' => $this->index])->getStatusCode() === 200;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ───────────────────────────────────────────────
    // Index lifecycle
    // ───────────────────────────────────────────────

    /**
     * Create the index if it doesn't exist. Tries the advanced Arabic analyzer
     * first, then falls back to a baseline mapping if creation fails.
     */
    public function createIndex(): bool
    {
        if (!$this->available) return false;
        if ($this->indexExists()) return true;

        // Attempt 1: rich Arabic analyzer
        try {
            $this->client->indices()->create([
                'index' => $this->index,
                'body'  => $this->buildIndexBody(advancedArabic: true),
            ]);
            return true;
        } catch (Throwable $e) {
            // ignored — fall through to baseline
        }

        // Attempt 2: safe baseline mapping
        try {
            $this->client->indices()->create([
                'index' => $this->index,
                'body'  => $this->buildIndexBody(advancedArabic: false),
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function deleteIndex(): bool
    {
        if (!$this->available) return false;
        try {
            if (!$this->indexExists()) return true;
            $this->client->indices()->delete(['index' => $this->index]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    // ───────────────────────────────────────────────
    // Indexing
    // ───────────────────────────────────────────────

    /**
     * Bulk-index every chunk of the given document.
     * Returns the number of chunks pushed to ES.
     */
    public function bulkIndexChunks(LegalDocument $doc): int
    {
        if (!$this->available) return 0;
        if (!$this->createIndex()) return 0;

        $chunks = $doc->chunks()->get();
        if ($chunks->isEmpty()) return 0;

        $bulk = ['body' => []];
        foreach ($chunks as $chunk) {
            $bulk['body'][] = [
                'index' => [
                    '_index' => $this->index,
                    '_id'    => $doc->id . ':' . $chunk->chunk_index,
                ],
            ];
            $bulk['body'][] = [
                'document_id'     => (string) $doc->id,
                'org_id'          => $doc->org_id,
                'chunk_index'     => $chunk->chunk_index,
                'title'           => $doc->title,
                'title_en'        => $doc->title_en,
                'document_type'   => (string) $doc->type,
                'source_entity'   => $doc->source_entity,
                'issue_date'      => optional($doc->issued_at)->toDateString(),
                'article_number'  => $this->extractArticleNumber($chunk->label),
                'chunk_label'     => $chunk->label,
                'chunk_text'      => $chunk->content,
                'normalized_text' => $chunk->normalized,
                'metadata'        => $doc->metadata ?? new \stdClass(),
                'reference'       => $doc->reference_number,
            ];
        }

        try {
            $resp = $this->client->bulk($bulk)->asArray();
            $errors = $resp['errors'] ?? false;
            if ($errors) return 0;

            $doc->chunks()->update(['indexed_at' => now()]);
            return $chunks->count();
        } catch (Throwable $e) {
            return 0;
        }
    }

    /** Re-chunk a single document and re-index it. Returns chunks indexed. */
    public function reindexDocument(LegalDocument $doc): int
    {
        // Drop existing ES rows for this doc
        $this->deleteDocument($doc->id);

        // Re-chunk into DB
        $this->chunker->chunkAndStore($doc);

        // Push to ES
        return $this->bulkIndexChunks($doc->fresh('chunks'));
    }

    /**
     * Reindex every document (optionally scoped to one organization).
     * Returns ['documents' => N, 'chunks' => M, 'indexed' => K].
     */
    public function reindexAll(?int $orgId = null): array
    {
        $query = LegalDocument::query();
        if ($orgId) $query->where('org_id', $orgId);

        $docs = $query->get();
        $totalChunks = 0;
        $totalIndexed = 0;

        foreach ($docs as $doc) {
            $count = $this->chunker->chunkAndStore($doc);
            $totalChunks += $count;
            $totalIndexed += $this->bulkIndexChunks($doc->fresh('chunks'));
        }

        return [
            'documents' => $docs->count(),
            'chunks'    => $totalChunks,
            'indexed'   => $totalIndexed,
        ];
    }

    public function deleteDocument(int $documentId): void
    {
        if (!$this->available || !$this->indexExists()) return;
        try {
            $this->client->deleteByQuery([
                'index' => $this->index,
                'body'  => ['query' => ['term' => ['document_id' => (string) $documentId]]],
                'refresh' => true,
            ]);
        } catch (Throwable $e) { /* ignore */ }
    }

    // ───────────────────────────────────────────────
    // Search
    // ───────────────────────────────────────────────

    /**
     * Full-text search. Returns null if ES is unavailable so callers can fall
     * back to DB. Otherwise returns ['total','page','size','took','hits'=>[]].
     */
    public function search(string $query, array $filters = [], int $page = 1, int $size = 20): ?array
    {
        if (!$this->available) return null;
        if (!$this->createIndex()) return null;

        $must = [
            [
                'multi_match' => [
                    'query'     => $query,
                    'fields'    => ['title^3', 'title_en^2', 'chunk_text', 'normalized_text'],
                    'type'      => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ],
        ];

        $filter = [];
        if (!empty($filters['org_id']))         $filter[] = ['term'  => ['org_id'        => (int) $filters['org_id']]];
        if (!empty($filters['type']))           $filter[] = ['term'  => ['document_type' => (string) $filters['type']]];
        if (!empty($filters['source_entity'])) $filter[] = ['term'  => ['source_entity' => $filters['source_entity']]];
        if (!empty($filters['from']))           $filter[] = ['range' => ['issue_date'    => ['gte' => $filters['from']]]];
        if (!empty($filters['to']))             $filter[] = ['range' => ['issue_date'    => ['lte' => $filters['to']]]];

        $body = [
            'query' => [
                'bool' => [
                    'must'   => $must,
                    'filter' => $filter,
                ],
            ],
            'highlight' => [
                'fields' => [
                    'chunk_text' => ['fragment_size' => 220, 'number_of_fragments' => 1],
                ],
                'pre_tags'  => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
            'from' => ($page - 1) * $size,
            'size' => $size,
        ];

        try {
            $res = $this->client->search(['index' => $this->index, 'body' => $body])->asArray();
            $hits = $res['hits']['hits'] ?? [];

            return [
                'total' => $res['hits']['total']['value'] ?? 0,
                'page'  => $page,
                'size'  => $size,
                'took'  => $res['took'] ?? 0,
                'hits'  => array_map(fn ($h) => [
                    'document_id'    => (int) $h['_source']['document_id'],
                    'chunk_index'    => $h['_source']['chunk_index'],
                    'title'          => $h['_source']['title'],
                    'type'           => (int) ($h['_source']['document_type'] ?? 0),
                    'source_entity'  => $h['_source']['source_entity'] ?? null,
                    'issue_date'     => $h['_source']['issue_date'] ?? null,
                    'article_number' => $h['_source']['article_number'] ?? null,
                    'label'          => $h['_source']['chunk_label'] ?? null,
                    'snippet'        => $h['highlight']['chunk_text'][0]
                                        ?? mb_substr($h['_source']['chunk_text'], 0, 240),
                    'score'          => $h['_score'],
                ], $hits),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    // ───────────────────────────────────────────────
    // Mapping builders
    // ───────────────────────────────────────────────

    private function buildIndexBody(bool $advancedArabic): array
    {
        $analyzerName = $advancedArabic ? 'arabic_legal' : 'standard';

        $settings = [
            'number_of_shards'   => 1,
            'number_of_replicas' => 0,
        ];

        if ($advancedArabic) {
            $settings['analysis'] = [
                'filter' => [
                    'ar_stop' => [
                        'type'      => 'stop',
                        'stopwords' => '_arabic_',
                    ],
                    'ar_stemmer' => [
                        'type'     => 'stemmer',
                        'language' => 'arabic',
                    ],
                ],
                'analyzer' => [
                    'arabic_legal' => [
                        'type'      => 'custom',
                        'tokenizer' => 'standard',
                        'filter'    => [
                            'lowercase',
                            'decimal_digit',
                            'ar_stop',
                            'arabic_normalization',
                            'ar_stemmer',
                        ],
                    ],
                ],
            ];
        }

        return [
            'settings' => $settings,
            'mappings' => [
                'properties' => [
                    'document_id'     => ['type' => 'keyword'],
                    'org_id'          => ['type' => 'long'],
                    'chunk_index'     => ['type' => 'integer'],

                    'title' => [
                        'type'     => 'text',
                        'analyzer' => $analyzerName,
                        'fields'   => [
                            'keyword' => ['type' => 'keyword', 'ignore_above' => 512],
                        ],
                    ],
                    'title_en'       => ['type' => 'text', 'analyzer' => 'standard'],

                    'document_type'  => ['type' => 'keyword'],
                    'source_entity'  => ['type' => 'keyword'],
                    'issue_date'     => ['type' => 'date', 'ignore_malformed' => true],
                    'article_number' => ['type' => 'keyword'],
                    'chunk_label'    => ['type' => 'keyword'],
                    'reference'      => ['type' => 'keyword'],

                    'chunk_text'     => ['type' => 'text', 'analyzer' => $analyzerName],
                    'normalized_text'=> ['type' => 'text', 'analyzer' => 'standard'],

                    'metadata'       => ['type' => 'object', 'enabled' => true],
                ],
            ],
        ];
    }

    /** Extract digits from "المادة (74):" → "74". Returns null if no digits. */
    private function extractArticleNumber(?string $label): ?string
    {
        if (!$label) return null;
        return preg_match('/(\d+)/u', $label, $m) ? $m[1] : null;
    }
}
