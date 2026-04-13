<?php

namespace App\Services;

use App\Models\Tender;
use App\Models\TenderReview;
use App\Models\TenderSimilarityResult;
use Illuminate\Support\Collection;

/**
 * TenderSimilarityService
 * ───────────────────────
 * Multi-layered similarity engine that compares tender scopes against
 * historical tenders within the same organization.
 *
 * Three scoring layers:
 *   1. Text similarity — keyword/token overlap + title matching
 *   2. Semantic similarity — normalized n-gram + phrase matching
 *   3. Structural similarity — sections, deliverables, evaluation, timeline
 *
 * The final score is a weighted composite. Configurable weights and thresholds.
 *
 * Architecture note: designed so embeddings can replace layer 2 later.
 */
class TenderSimilarityService
{
    /** Weights for the composite score (must sum to 1.0). */
    private const TEXT_WEIGHT       = 0.35;
    private const SEMANTIC_WEIGHT   = 0.45;
    private const STRUCTURAL_WEIGHT = 0.20;

    /** Similarity level thresholds. */
    private const EXACT_MATCH     = 95;
    private const HIGH_SIMILARITY = 80;
    private const MED_SIMILARITY  = 65;
    private const WEAK_SIMILARITY = 50;

    public function __construct(
        private ArabicTextNormalizerService $normalizer,
    ) {}

    // ════════════════════════════════════════════════════════════════
    // Public API
    // ════════════════════════════════════════════════════════════════

    /**
     * Analyze a tender against all other tenders in the same org.
     * Persists results and returns the top matches.
     */
    public function analyze(Tender $tender, int $maxResults = 10): Collection
    {
        $sourceScope = $this->getNormalizedScope($tender);
        $sourceSections = $tender->sections()->pluck('title', 'section_key')->toArray();
        $sourceClauses = $tender->clauses()->pluck('clause_type')->toArray();
        $sourceDeliverables = $tender->deliverables ?? [];
        $sourceEval = $tender->evaluation_criteria ?? [];

        // Fetch candidates from the same org (exclude self)
        $candidates = Tender::where('org_id', $tender->org_id)
            ->where('id', '!=', $tender->id)
            ->with(['sections', 'clauses', 'review'])
            ->get();

        if ($candidates->isEmpty()) return collect();

        // Split source scope into meaningful segments for partial matching
        $sourceSegments = $this->splitIntoSegments($sourceScope);
        $results = [];

        foreach ($candidates as $candidate) {
            $candidateScope = $this->getNormalizedScope($candidate);
            if ($candidateScope === '' && $sourceScope === '') continue;

            // 1. Text similarity (whole scope)
            $textScore = $this->textSimilarity($sourceScope, $candidateScope, $tender->title, $candidate->title);

            // 2. Semantic similarity
            $semanticScore = $this->semanticSimilarity($sourceScope, $candidateScope);

            // 3. Structural similarity
            $structScore = $this->structuralSimilarity(
                $sourceSections, $candidate->sections()->pluck('title', 'section_key')->toArray(),
                $sourceClauses, $candidate->clauses()->pluck('clause_type')->toArray(),
                $sourceDeliverables, $candidate->deliverables ?? [],
                $sourceEval, $candidate->evaluation_criteria ?? [],
                $tender->type, $candidate->type,
                $tender->duration, $candidate->duration,
            );

            // 4. Segment-level matching — which parts of the scope match this candidate?
            $matchedSegments = $this->matchSegments($sourceSegments, $candidateScope, $candidate->title);

            // Composite score
            $finalScore = round(
                ($textScore * self::TEXT_WEIGHT)
                + ($semanticScore * self::SEMANTIC_WEIGHT)
                + ($structScore * self::STRUCTURAL_WEIGHT),
                1
            );

            // If overall score is low but segment match is strong, boost it
            $segmentCoverage = count($matchedSegments) > 0
                ? (collect($matchedSegments)->avg('score') ?? 0)
                : 0;
            if ($finalScore < self::WEAK_SIMILARITY && $segmentCoverage >= 60) {
                $finalScore = max($finalScore, round($segmentCoverage * 0.7, 1));
            }

            // Lower threshold: include partial matches (35%) so combined coverage is visible
            if ($finalScore < 35 && empty($matchedSegments)) continue;

            $level = $this->classifyLevel($finalScore);
            $review = $candidate->review;

            $results[] = [
                'compared_tender_id'          => $candidate->id,
                'text_similarity_score'       => round($textScore, 1),
                'semantic_similarity_score'   => round($semanticScore, 1),
                'structural_similarity_score' => round($structScore, 1),
                'final_similarity_score'      => max($finalScore, 35),
                'similarity_level'            => $finalScore >= self::WEAK_SIMILARITY ? $level : 'partial_match',
                'duplicate_risk'              => $finalScore >= self::EXACT_MATCH,
                'matched_segments'            => $matchedSegments,
                'reusable_sections'           => $this->findReusableSections($sourceSections, $candidate),
                'reusable_clauses'            => $this->findReusableClauses($sourceClauses, $candidate),
                'reusable_criteria'           => $this->findReusableCriteria($sourceEval, $candidate),
                'lessons_learned'             => $review ? $this->extractLessons($review) : [],
                'recommendation'              => $this->generateRecommendation($finalScore, $level, $review, $candidate),
                '_status'          => $candidate->status,
                '_compliance'      => $review?->compliance_score,
                '_reviewed_at'     => $review?->created_at,
            ];
        }

        // Rank: highest score → latest review → best compliance → approved first
        usort($results, function ($a, $b) {
            if ($a['final_similarity_score'] !== $b['final_similarity_score']) {
                return $b['final_similarity_score'] <=> $a['final_similarity_score'];
            }
            $aApproved = $a['_status'] === 'finalized' ? 1 : 0;
            $bApproved = $b['_status'] === 'finalized' ? 1 : 0;
            if ($aApproved !== $bApproved) return $bApproved <=> $aApproved;
            return ($b['_compliance'] ?? 0) <=> ($a['_compliance'] ?? 0);
        });

        $top = array_slice($results, 0, $maxResults);

        // Compute scope coverage: what % of source segments are covered by ANY match
        $allSegmentsCovered = [];
        foreach ($results as $r) {
            foreach ($r['matched_segments'] ?? [] as $seg) {
                $allSegmentsCovered[$seg['segment']] = max(
                    $allSegmentsCovered[$seg['segment']] ?? 0,
                    $seg['score']
                );
            }
        }
        $scopeCoverage = count($sourceSegments) > 0
            ? round((count($allSegmentsCovered) / count($sourceSegments)) * 100, 1)
            : 0;

        // Persist results (replace old ones for this source tender)
        TenderSimilarityResult::where('source_tender_id', $tender->id)->delete();
        foreach ($top as $row) {
            $persist = $row;
            unset($persist['_status'], $persist['_compliance'], $persist['_reviewed_at']);
            // Merge matched_segments into reusable_sections for storage
            $persist['reusable_sections'] = [
                'sections' => $persist['reusable_sections'] ?? [],
                'matched_segments' => $persist['matched_segments'] ?? [],
                'scope_coverage' => $scopeCoverage,
            ];
            unset($persist['matched_segments']);
            TenderSimilarityResult::create([
                'source_tender_id' => $tender->id,
                'org_id'           => $tender->org_id,
                ...$persist,
            ]);
        }

        return $tender->similarityResults()->with('comparedTender.review')->get();
    }

    /**
     * Quick check: compare raw scope text against org tenders before creation.
     * Returns top matches without persisting.
     */
    public function checkScope(string $scopeText, int $orgId, ?string $type = null, int $maxResults = 5): array
    {
        $normalized = $this->normalizer->normalize($scopeText);

        $candidates = Tender::where('org_id', $orgId)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->get();

        $matches = [];
        foreach ($candidates as $candidate) {
            $candidateScope = $this->getNormalizedScope($candidate);
            if ($candidateScope === '') continue;

            $textScore = $this->textSimilarity($normalized, $candidateScope, '', $candidate->title);
            $semanticScore = $this->semanticSimilarity($normalized, $candidateScope);
            // Type match boost (+10 if same type)
            $typeBoost = ($type && $candidate->type === $type) ? 10 : 0;
            $final = round(($textScore * 0.40) + ($semanticScore * 0.60) + $typeBoost, 1);
            $final = min(100, $final);

            if ($final < 40) continue; // Lower threshold for quick scope check

            $review = $candidate->review;
            $matches[] = [
                'tender_id'        => $candidate->id,
                'title'            => $candidate->title,
                'type'             => $candidate->type,
                'type_label'       => $candidate->type_label,
                'status'           => $candidate->status,
                'status_label'     => $candidate->status_label,
                'compliance_score' => $review?->compliance_score,
                'similarity_score' => $final,
                'similarity_level' => $this->classifyLevel($final),
                'duplicate_risk'   => $final >= self::EXACT_MATCH,
            ];
        }

        usort($matches, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);
        return array_slice($matches, 0, $maxResults);
    }

    /**
     * Compare an uploaded tender review (LegalDocument) against:
     *   1. Previously reviewed tenders (kind=tender_review) in same org
     *   2. Previously generated tenders (Tender model) in same org
     *
     * Returns matches array and stores in document metadata.
     */
    public function compareReview(\App\Models\LegalDocument $document, int $maxResults = 10): array
    {
        $content = trim((string) $document->content);
        if (mb_strlen($content) < 20) return [];

        $normalized = $this->normalizer->normalize(mb_substr($content, 0, 15000));
        $orgId = $document->org_id;
        $matches = [];

        // 1. Compare with other reviewed tenders (LegalDocument kind=tender_review)
        $reviewedDocs = \App\Models\LegalDocument::where('org_id', $orgId)
            ->where('kind', \App\Models\LegalDocument::KIND_TENDER_REVIEW)
            ->where('id', '!=', $document->id)
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->get();

        foreach ($reviewedDocs as $doc) {
            $docNorm = $this->normalizer->normalize(mb_substr($doc->content, 0, 15000));
            $textScore = $this->textSimilarity($normalized, $docNorm, $document->title, $doc->title);
            $semanticScore = $this->semanticSimilarity($normalized, $docNorm);
            $final = round(($textScore * 0.40) + ($semanticScore * 0.60), 1);

            if ($final < self::WEAK_SIMILARITY) continue;

            $analysis = $doc->analysis ?? [];
            $lessons = [];
            if (! empty($analysis['compliance_score'])) {
                $lessons[] = "نسبة امتثال الكراسة السابقة: {$analysis['compliance_score']}%";
            }
            foreach ($analysis['findings'] ?? [] as $f) {
                $sev = $f['severity'] ?? '';
                $title = $f['issue_title'] ?? $f['title'] ?? '';
                if (in_array($sev, ['Critical', 'High', 'حرجة', 'عالية']) && $title) {
                    $lessons[] = "[{$sev}] {$title}";
                }
            }

            $matches[] = [
                'source'           => 'review',
                'id'               => $doc->id,
                'title'            => $doc->title,
                'type_label'       => 'كراسة مراجعة سابقة',
                'status'           => $analysis['compliance_score'] ?? null ? 'تمت المراجعة' : 'مرفوعة',
                'compliance_score' => $analysis['compliance_score'] ?? null,
                'similarity_score' => $final,
                'similarity_level' => $this->classifyLevel($final),
                'duplicate_risk'   => $final >= self::EXACT_MATCH,
                'lessons_learned'  => $lessons,
                'url'              => "/tender-reviews/{$doc->id}",
            ];
        }

        // 2. Compare with generated tenders (Tender model)
        $tenders = Tender::where('org_id', $orgId)->get();
        foreach ($tenders as $tender) {
            $tenderScope = $this->getNormalizedScope($tender);
            if ($tenderScope === '') continue;

            $textScore = $this->textSimilarity($normalized, $tenderScope, $document->title, $tender->title);
            $semanticScore = $this->semanticSimilarity($normalized, $tenderScope);
            $final = round(($textScore * 0.40) + ($semanticScore * 0.60), 1);

            if ($final < self::WEAK_SIMILARITY) continue;

            $review = $tender->review;
            $lessons = $review ? $this->extractLessons($review) : [];

            $matches[] = [
                'source'           => 'generated',
                'id'               => $tender->id,
                'title'            => $tender->title,
                'type_label'       => $tender->type_label . ' (مولّدة)',
                'status'           => $tender->status_label,
                'compliance_score' => $review?->compliance_score,
                'similarity_score' => $final,
                'similarity_level' => $this->classifyLevel($final),
                'duplicate_risk'   => $final >= self::EXACT_MATCH,
                'lessons_learned'  => $lessons,
                'url'              => "/tenders/{$tender->id}",
            ];
        }

        usort($matches, fn ($a, $b) => $b['similarity_score'] <=> $a['similarity_score']);
        $top = array_slice($matches, 0, $maxResults);

        // Store in document metadata
        $meta = $document->metadata ?? [];
        $meta['similarity_matches'] = $top;
        $meta['similarity_checked_at'] = now()->toIso8601String();
        $document->updateQuietly(['metadata' => $meta]);

        return $top;
    }

    // ════════════════════════════════════════════════════════════════
    // Layer 1: Text Similarity
    // ════════════════════════════════════════════════════════════════

    private function textSimilarity(string $a, string $b, string $titleA, string $titleB): float
    {
        if ($a === '' || $b === '') return 0;

        $tokensA = $this->tokenize($a);
        $tokensB = $this->tokenize($b);
        if (empty($tokensA) || empty($tokensB)) return 0;

        // Token overlap (Jaccard)
        $intersection = count(array_intersect($tokensA, $tokensB));
        $union = count(array_unique(array_merge($tokensA, $tokensB)));
        $jaccard = $union > 0 ? ($intersection / $union) * 100 : 0;

        // Sequence similarity (good for short texts where Jaccard underperforms)
        similar_text($a, $b, $seqPct);

        // Title similarity
        $titleScore = 0;
        if ($titleA !== '' && $titleB !== '') {
            $normA = $this->normalizer->normalize($titleA);
            $normB = $this->normalizer->normalize($titleB);
            similar_text($normA, $normB, $titleScore);
        }

        return ($jaccard * 0.35) + ($seqPct * 0.40) + ($titleScore * 0.25);
    }

    // ════════════════════════════════════════════════════════════════
    // Layer 2: Semantic Similarity (n-gram + phrase matching)
    // ════════════════════════════════════════════════════════════════

    private function semanticSimilarity(string $a, string $b): float
    {
        if ($a === '' || $b === '') return 0;

        // Bigram overlap
        $bigramsA = $this->ngrams($a, 2);
        $bigramsB = $this->ngrams($b, 2);
        $bigramScore = $this->overlapScore($bigramsA, $bigramsB);

        // Trigram overlap
        $trigramsA = $this->ngrams($a, 3);
        $trigramsB = $this->ngrams($b, 3);
        $trigramScore = $this->overlapScore($trigramsA, $trigramsB);

        // Key phrase matching (longer phrases = stronger signal)
        $phraseScore = $this->phraseOverlap($a, $b);

        // For short texts (<200 chars), n-grams underperform — boost with
        // substring containment check (does one scope contain the other?)
        $containment = 0;
        $lenA = mb_strlen($a);
        $lenB = mb_strlen($b);
        if ($lenA > 0 && $lenB > 0) {
            $shorter = $lenA < $lenB ? $a : $b;
            $longer  = $lenA < $lenB ? $b : $a;
            // Check if most tokens of the shorter text appear in the longer
            $shortTokens = $this->tokenize($shorter);
            $found = 0;
            foreach ($shortTokens as $t) {
                if (mb_stripos($longer, $t) !== false) $found++;
            }
            $containment = count($shortTokens) > 0 ? ($found / count($shortTokens)) * 100 : 0;
        }

        return ($bigramScore * 0.20) + ($trigramScore * 0.20) + ($phraseScore * 0.30) + ($containment * 0.30);
    }

    // ════════════════════════════════════════════════════════════════
    // Layer 3: Structural Similarity
    // ════════════════════════════════════════════════════════════════

    private function structuralSimilarity(
        array $sectionsA, array $sectionsB,
        array $clausesA, array $clausesB,
        array $deliverablesA, array $deliverablesB,
        array $evalA, array $evalB,
        ?string $typeA, ?string $typeB,
        ?string $durationA, ?string $durationB,
    ): float {
        $scores = [];

        // Section overlap
        if (! empty($sectionsA) && ! empty($sectionsB)) {
            $keysA = array_keys($sectionsA);
            $keysB = array_keys($sectionsB);
            $scores[] = $this->overlapScore($keysA, $keysB);
        }

        // Clause overlap
        if (! empty($clausesA) && ! empty($clausesB)) {
            $scores[] = $this->overlapScore($clausesA, $clausesB);
        }

        // Type match
        $scores[] = ($typeA === $typeB) ? 100 : 30;

        // Deliverables count similarity
        if (! empty($deliverablesA) && ! empty($deliverablesB)) {
            $countA = count($deliverablesA);
            $countB = count($deliverablesB);
            $max = max($countA, $countB);
            $scores[] = $max > 0 ? (1 - abs($countA - $countB) / $max) * 100 : 50;
        }

        // Evaluation structure similarity
        if (! empty($evalA) && ! empty($evalB)) {
            $critA = array_map(fn ($c) => is_array($c) ? ($c['criterion'] ?? '') : $c, $evalA);
            $critB = array_map(fn ($c) => is_array($c) ? ($c['criterion'] ?? '') : $c, $evalB);
            $normA = array_map(fn ($c) => $this->normalizer->normalize($c), array_filter($critA));
            $normB = array_map(fn ($c) => $this->normalizer->normalize($c), array_filter($critB));
            $scores[] = $this->overlapScore($normA, $normB);
        }

        // Duration similarity
        if ($durationA && $durationB) {
            $normDA = $this->normalizer->normalize($durationA);
            $normDB = $this->normalizer->normalize($durationB);
            similar_text($normDA, $normDB, $durPct);
            $scores[] = $durPct;
        }

        return empty($scores) ? 0 : array_sum($scores) / count($scores);
    }

    // ════════════════════════════════════════════════════════════════
    // Reusability Detection
    // ════════════════════════════════════════════════════════════════

    private function findReusableSections(array $sourceSections, Tender $candidate): array
    {
        $reusable = [];
        foreach ($candidate->sections as $section) {
            if (mb_strlen($section->content ?? '') > 50) {
                $reusable[] = [
                    'section_key' => $section->section_key,
                    'title'       => $section->title,
                    'length'      => mb_strlen($section->content),
                ];
            }
        }
        return $reusable;
    }

    private function findReusableClauses(array $sourceClauses, Tender $candidate): array
    {
        return $candidate->clauses->map(fn ($c) => [
            'clause_type' => $c->clause_type,
            'title'       => $c->title,
        ])->toArray();
    }

    private function findReusableCriteria(array $sourceEval, Tender $candidate): array
    {
        if (empty($candidate->evaluation_criteria)) return [];
        return array_map(fn ($c) => is_array($c) ? $c : ['criterion' => $c], $candidate->evaluation_criteria);
    }

    private function extractLessons(?TenderReview $review): array
    {
        if (! $review) return [];
        $lessons = [];

        if ($review->compliance_score !== null) {
            $lessons[] = "نسبة امتثال الكراسة السابقة: {$review->compliance_score}%";
        }

        foreach ($review->issues ?? [] as $issue) {
            $sev = $issue['severity'] ?? '';
            $title = $issue['title'] ?? $issue['issue_title'] ?? '';
            if (in_array($sev, ['critical', 'high', 'Critical', 'High']) && $title) {
                $lessons[] = "[{$sev}] {$title}";
            }
        }

        if (! empty($review->recommendations)) {
            foreach (array_slice($review->recommendations, 0, 3) as $rec) {
                $lessons[] = "توصية: {$rec}";
            }
        }

        return $lessons;
    }

    private function generateRecommendation(float $score, string $level, ?TenderReview $review, Tender $candidate): string
    {
        $statusAr = Tender::STATUSES[$candidate->status] ?? $candidate->status;
        $compliance = $review?->compliance_score;

        if ($level === 'exact_match') {
            return "تحذير: يوجد تطابق شبه تام مع كراسة \"{$candidate->title}\" ({$statusAr}). تأكد من عدم التكرار.";
        }

        if ($compliance && $compliance >= 85 && in_array($candidate->status, ['ready', 'finalized'])) {
            return "يوصى باستخدام كراسة \"{$candidate->title}\" كمرجع — نسبة امتثال {$compliance}% وحالتها: {$statusAr}.";
        }

        if ($level === 'high_similarity') {
            return "تشابه عالي مع \"{$candidate->title}\". راجع الكراسة السابقة قبل المتابعة.";
        }

        return "تشابه متوسط مع \"{$candidate->title}\". يمكن الاستفادة من بعض الأقسام.";
    }

    // ════════════════════════════════════════════════════════════════
    // Helpers
    // ════════════════════════════════════════════════════════════════

    private function getNormalizedScope(Tender $tender): string
    {
        if ($tender->normalized_scope) return $tender->normalized_scope;

        $raw = $tender->scope_input ?? $tender->description ?? '';
        if ($raw === '') return '';

        $normalized = $this->normalizer->normalize($raw);
        $tender->updateQuietly(['normalized_scope' => $normalized]);
        return $normalized;
    }

    private function tokenize(string $text): array
    {
        $raw = preg_split('/\s+/u', $text) ?: [];
        $tokens = [];
        foreach ($raw as $t) {
            if (mb_strlen($t) < 3) continue;
            // Strip Arabic definite article (ال) for comparison
            $stripped = preg_replace('/^(ال|وال|بال|فال|كال)/u', '', $t) ?? $t;
            $tokens[] = mb_strlen($stripped) >= 2 ? $stripped : $t;
        }
        return array_values(array_unique($tokens));
    }

    private function ngrams(string $text, int $n): array
    {
        $tokens = $this->tokenize($text);
        if (count($tokens) < $n) return $tokens;
        $ngrams = [];
        for ($i = 0; $i <= count($tokens) - $n; $i++) {
            $ngrams[] = implode(' ', array_slice($tokens, $i, $n));
        }
        return $ngrams;
    }

    private function overlapScore(array $a, array $b): float
    {
        if (empty($a) || empty($b)) return 0;
        $intersection = count(array_intersect($a, $b));
        $union = count(array_unique(array_merge($a, $b)));
        return $union > 0 ? ($intersection / $union) * 100 : 0;
    }

    private function phraseOverlap(string $a, string $b): float
    {
        // Extract 4-word phrases
        $phrasesA = $this->ngrams($a, 4);
        $phrasesB = $this->ngrams($b, 4);
        if (empty($phrasesA) || empty($phrasesB)) return 0;

        $matches = 0;
        foreach ($phrasesA as $p) {
            if (in_array($p, $phrasesB, true)) $matches++;
        }
        $max = max(count($phrasesA), count($phrasesB));
        return $max > 0 ? ($matches / $max) * 100 : 0;
    }

    private function classifyLevel(float $score): string
    {
        if ($score >= self::EXACT_MATCH) return 'exact_match';
        if ($score >= self::HIGH_SIMILARITY) return 'high_similarity';
        if ($score >= self::MED_SIMILARITY) return 'medium_similarity';
        return 'weak_similarity';
    }

    /**
     * Split scope text into meaningful segments (sentences/bullets/paragraphs).
     * Each segment represents a distinct aspect of the scope.
     */
    private function splitIntoSegments(string $normalizedScope): array
    {
        if ($normalizedScope === '') return [];

        // Split on: newlines, bullet points, numbered items, periods, semicolons
        $raw = preg_split('/[\n\r]+|[.،؛;•·]\s+|\d+[.)]\s+/u', $normalizedScope);
        $segments = [];
        foreach ($raw ?: [] as $part) {
            $part = trim($part);
            if (mb_strlen($part) >= 10) { // Meaningful segment
                $segments[] = $part;
            }
        }
        return $segments;
    }

    /**
     * For each source segment, check how well it matches the candidate scope.
     * Returns only segments with meaningful match (score >= 40%).
     */
    private function matchSegments(array $sourceSegments, string $candidateScope, string $candidateTitle): array
    {
        if (empty($sourceSegments) || $candidateScope === '') return [];

        $matched = [];
        foreach ($sourceSegments as $segment) {
            // Token containment: how many tokens of this segment appear in candidate?
            $tokens = $this->tokenize($segment);
            if (empty($tokens)) continue;

            $found = 0;
            foreach ($tokens as $t) {
                if (mb_stripos($candidateScope, $t) !== false) $found++;
            }
            $containment = ($found / count($tokens)) * 100;

            // Sequence similarity
            similar_text($segment, mb_substr($candidateScope, 0, mb_strlen($segment) * 3), $seqPct);

            $score = round(($containment * 0.6) + ($seqPct * 0.4), 1);

            if ($score >= 40) {
                $matched[] = [
                    'segment' => mb_substr($segment, 0, 150),
                    'score'   => $score,
                    'tokens_found' => $found,
                    'tokens_total' => count($tokens),
                ];
            }
        }

        // Sort by score desc
        usort($matched, fn ($a, $b) => $b['score'] <=> $a['score']);
        return $matched;
    }
}
