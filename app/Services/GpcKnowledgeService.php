<?php

namespace App\Services;

use App\Models\GpcArticle;
use Illuminate\Support\Collection;

/**
 * GpcKnowledgeService
 * ───────────────────
 * RAG retrieval over the seeded GPC knowledge base.
 *
 * Given a tender booklet (or any text), retrieve the top-K most relevant
 * articles from نظام المنافسات والمشتريات الحكومية ولائحته التنفيذية + الأدلة
 * الإجرائية. The result is injected into the AI prompt so the model cites
 * real article numbers instead of hallucinating.
 *
 * Retrieval algorithm (no embeddings — works offline with Ollama):
 *   1. Normalize the query text (Arabic-aware).
 *   2. Score each article by:
 *      • keyword hits (high weight)
 *      • normalized substring overlap (medium weight)
 *      • topic match (low weight)
 *   3. Return the top-K records with their full text + reference.
 */
class GpcKnowledgeService
{
    public function __construct(private ArabicTextNormalizerService $normalizer) {}

    /**
     * Retrieve the top-K most relevant articles for a tender content.
     *
     * @return Collection<int, GpcArticle>
     */
    public function retrieve(string $content, int $topK = 8): Collection
    {
        $normalized = $this->normalizer->normalize($content);
        $queryWords = collect(preg_split('/\s+/u', $normalized))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->unique()
            ->values();

        $articles = GpcArticle::all();

        $scored = $articles->map(function (GpcArticle $article) use ($normalized, $queryWords) {
            $score = 0;

            // Keyword hits (weight: 5 each)
            foreach ((array) ($article->keywords ?? []) as $kw) {
                if (mb_stripos($normalized, $kw) !== false) {
                    $score += 5;
                }
            }

            // Topic match (weight: 4)
            if ($article->topic && mb_stripos($normalized, $article->topic) !== false) {
                $score += 4;
            }

            // Substring presence of normalized query words (weight: 1 each)
            $articleNorm = $article->normalized ?? $article->content;
            foreach ($queryWords as $word) {
                if (mb_stripos($articleNorm, $word) !== false) {
                    $score += 1;
                }
            }

            return ['article' => $article, 'score' => $score];
        });

        return $scored
            ->sortByDesc('score')
            ->take($topK)
            ->filter(fn ($row) => $row['score'] > 0)
            ->map(fn ($row) => $row['article'])
            ->values();
    }

    /**
     * Build a context string for AI prompts containing the retrieved articles.
     * Designed to be inlined inside a system prompt or user message.
     */
    public function buildContext(Collection $articles): string
    {
        if ($articles->isEmpty()) {
            return '';
        }

        $lines = ["═══ المراجع النظامية ذات الصلة (مفهرسة من النصوص الرسمية) ═══\n"];
        foreach ($articles as $article) {
            $lines[] = '── ' . $article->article_label . ' — ' . ($article->source_label ?? '') . ' ──';
            if ($article->topic) {
                $lines[] = 'الموضوع: ' . $article->topic;
            }
            $lines[] = $article->content;
            $lines[] = '';
        }
        $lines[] = '═══ نهاية المراجع ═══';
        $lines[] = '';
        $lines[] = 'تعليمات: اعتمد فقط على هذه المراجع عند ذكر أي مادة نظامية. لا تخترع أرقام مواد غير موجودة فيها. إذا لم تجد سنداً مناسباً اكتب: "لم أجد مرجعاً نظامياً صريحاً في المصادر المفهرسة."';

        return implode("\n", $lines);
    }

    /** Convenience: retrieve + build context in one call. */
    public function buildContextFor(string $content, int $topK = 8): string
    {
        return $this->buildContext($this->retrieve($content, $topK));
    }

    /** Total number of articles in the knowledge base. */
    public function totalArticles(): int
    {
        return GpcArticle::count();
    }

    /** Articles grouped by source for the admin UI. */
    public function articlesBySource(): array
    {
        return GpcArticle::orderBy('source')
            ->orderBy('article_number')
            ->get()
            ->groupBy('source')
            ->toArray();
    }
}
