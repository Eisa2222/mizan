<?php

namespace App\Services;

use App\Models\LegalDocument;
use App\Models\Organization;

/**
 * TrainingCorpusService
 * ─────────────────────
 * Retrieves reference-grade passages from the "Training Library" organisation
 * — the corpus imported via `php artisan mizaan:train-from-folder`. Used by
 * the AI jobs (contract/tender/memo/case review) to inject real precedent
 * excerpts into Claude prompts alongside GPC + distilled knowledge.
 *
 * Training docs are tagged via metadata.training_source=true and categorised
 * by folder into kinds (document / contract / tender_review / memo / case).
 * This service filters by kind so each job only sees its own sub-corpus.
 */
class TrainingCorpusService
{
    private const CACHE_KEY = 'training_corpus_org_id';

    public function __construct(private readonly ArabicTextNormalizerService $normalizer)
    {
    }

    /**
     * Top-K relevant passages for the given query, scoped to `kinds`.
     *
     * @param  list<string>  $kinds  e.g. ['document', 'contract']
     * @return list<array{title:string, snippet:string, source:string}>
     */
    public function retrieve(string $query, array $kinds, int $topK = 4): array
    {
        $orgId = $this->trainingOrgId();
        if ($orgId === null) {
            return [];
        }

        $tokens = $this->tokens($query);
        if (empty($tokens)) {
            return [];
        }

        $docs = LegalDocument::query()
            ->where('org_id', $orgId)
            ->whereIn('kind', $kinds)
            ->whereNotNull('content')
            ->where(function ($scope) use ($tokens) {
                foreach ($tokens as $token) {
                    $scope->orWhere('content', 'like', "%{$token}%");
                }
            })
            ->limit(40)
            ->get(['id', 'title', 'content', 'metadata']);

        $scored = $docs->map(function (LegalDocument $doc) use ($tokens) {
            $content = (string) $doc->content;
            $score = 0;
            foreach ($tokens as $token) {
                $hits = mb_substr_count($content, $token);
                $score += $hits;
                if (mb_stripos($doc->title, $token) !== false) {
                    $score += 3;
                }
            }

            if ($score === 0) {
                return null;
            }

            return [
                'title'   => $doc->title,
                'snippet' => $this->snippet($content, $tokens),
                'source'  => $doc->metadata['category_tag'] ?? 'مرجعي',
                '_score'  => $score,
            ];
        })->filter();

        $top = $scored->sortByDesc('_score')->take($topK)->values();

        return $top->map(fn ($row) => [
            'title'   => $row['title'],
            'snippet' => $row['snippet'],
            'source'  => $row['source'],
        ])->all();
    }

    /**
     * Convenience wrapper: returns a pre-formatted prompt block.
     *
     * @param  list<string>  $kinds
     */
    public function buildContextFor(string $query, array $kinds, int $topK = 4): string
    {
        $results = $this->retrieve($query, $kinds, $topK);
        if (empty($results)) {
            return '';
        }

        $lines = ["═══ مراجع من المكتبة المرجعية (نماذج قانونية سابقة) ═══\n"];
        foreach ($results as $i => $row) {
            $num = $i + 1;
            $lines[] = "── [{$num}] {$row['title']} ({$row['source']}) ──";
            $lines[] = $row['snippet'];
            $lines[] = '';
        }
        $lines[] = '═══ نهاية المراجع ═══';
        $lines[] = '';
        $lines[] = 'تعليمات: استفد من هذه المراجع لصياغة أكثر دقّة أو لاقتباس صياغات مُجرَّبة. لا تنسخ حرفياً دون مواءمة للسياق الحالي.';

        return implode("\n", $lines);
    }

    /** @return list<string> */
    private function tokens(string $query): array
    {
        $normalized = $this->normalizer->normalize($query);

        return collect(preg_split('/\s+/u', $normalized) ?: [])
            ->filter(fn ($t) => mb_strlen($t) >= 3 && ! preg_match('/^[0-9]+$/', $t))
            ->unique()
            ->take(10)
            ->values()
            ->all();
    }

    /** @param  list<string>  $tokens */
    private function snippet(string $content, array $tokens, int $window = 260): string
    {
        if (empty($tokens)) {
            return mb_substr($content, 0, $window);
        }

        $firstHit = null;
        foreach ($tokens as $token) {
            $pos = mb_stripos($content, $token);
            if ($pos !== false && ($firstHit === null || $pos < $firstHit)) {
                $firstHit = $pos;
            }
        }

        if ($firstHit === null) {
            return mb_substr($content, 0, $window);
        }

        $start = max(0, $firstHit - 80);
        $excerpt = mb_substr($content, $start, $window);

        return ($start > 0 ? '… ' : '') . trim($excerpt) . ($start + $window < mb_strlen($content) ? ' …' : '');
    }

    private function trainingOrgId(): ?int
    {
        return cache()->remember(self::CACHE_KEY, 300, function () {
            return Organization::query()
                ->where('domain', 'training.library')
                ->value('id');
        });
    }
}
