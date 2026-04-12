<?php

namespace App\Services;

use App\Models\DistilledKnowledge;
use Illuminate\Support\Collection;

/**
 * TasbibatKnowledgeService
 * ────────────────────────
 * RAG retrieval over the distilled legal knowledge base.
 *
 * Reads from the standalone `distilled_knowledge` table (not legal_documents),
 * so source documents can be deleted without losing the AI knowledge.
 *
 * Scoring weights:
 *   • court_type match (+8 boost)
 *   • legal_topics (5 per hit)
 *   • key_phrases (4 per hit)
 *   • applicable_to (3 per hit)
 *   • cited_laws (3 per hit)
 */
class TasbibatKnowledgeService
{
    public function __construct(private ArabicTextNormalizerService $normalizer) {}

    /**
     * Retrieve the top-K most relevant distilled entries for the given text.
     */
    public function retrieve(string $content, ?string $courtType = null, int $topK = 5): Collection
    {
        $normalized = $this->normalizer->normalize($content);
        $queryWords = collect(preg_split('/\s+/u', $normalized))
            ->filter(fn ($w) => mb_strlen($w) >= 3)
            ->unique()
            ->values();

        $entries = DistilledKnowledge::all();

        $scored = $entries->map(function (DistilledKnowledge $entry) use ($normalized, $queryWords, $courtType) {
            $score = 0;

            if ($courtType && $entry->court_type) {
                if (mb_stripos($entry->court_type, $courtType) !== false) {
                    $score += 8;
                }
            }

            foreach ($entry->legal_topics as $topic) {
                if (! is_string($topic)) continue;
                if (mb_stripos($normalized, $this->normalizer->normalize($topic)) !== false) {
                    $score += 5;
                }
                foreach ($queryWords as $word) {
                    if (mb_stripos($topic, $word) !== false) {
                        $score += 2;
                    }
                }
            }

            foreach ($entry->key_phrases as $phrase) {
                if (! is_string($phrase)) continue;
                if (mb_stripos($normalized, $this->normalizer->normalize($phrase)) !== false) {
                    $score += 4;
                }
            }

            foreach ($entry->applicable_to as $applicableTo) {
                if (! is_string($applicableTo)) continue;
                $normApplicable = $this->normalizer->normalize($applicableTo);
                foreach ($queryWords as $word) {
                    if (mb_stripos($normApplicable, $word) !== false) {
                        $score += 3;
                        break;
                    }
                }
            }

            foreach ($entry->cited_laws as $law) {
                if (! is_string($law)) continue;
                if (mb_stripos($normalized, $this->normalizer->normalize($law)) !== false) {
                    $score += 3;
                }
            }

            if ($score === 0) return null;

            return [
                'entry'    => $entry,
                'score'    => $score,
                'patterns' => $entry->reasoning_patterns,
                'laws'     => $entry->cited_laws,
                'topics'   => $entry->legal_topics,
                'court'    => $entry->court_type ?? '',
            ];
        })
            ->filter()
            ->sortByDesc('score')
            ->take($topK)
            ->values();

        return $scored;
    }

    public function buildContext(Collection $results): string
    {
        if ($results->isEmpty()) return '';

        $lines = ["═══ معرفة قانونية مستخلصة ذات صلة (من قاعدة المعرفة) ═══\n"];

        foreach ($results as $i => $row) {
            $entry = $row['entry'];
            $num = $i + 1;

            $lines[] = "── [{$num}] {$entry->title} ({$row['court']}) ──";
            $lines[] = 'الموضوعات: ' . implode('، ', $row['topics']);

            if (! empty($row['patterns'])) {
                $lines[] = 'أنماط التسبيب:';
                foreach ($row['patterns'] as $j => $pattern) {
                    $lines[] = '  ' . ($j + 1) . '. ' . $pattern;
                }
            }

            if (! empty($row['laws'])) {
                $lines[] = 'الأنظمة المُستشهد بها: ' . implode(' · ', $row['laws']);
            }

            $lines[] = '';
        }

        $lines[] = '═══ نهاية المعرفة ═══';
        $lines[] = '';
        $lines[] = 'تعليمات: استفد من أنماط التسبيب أعلاه كقوالب لتقوية الحجج القانونية. استشهد بالمصدر (اسم الوثيقة) عند الاقتباس. لا تنسب تسبيباً لوثيقة غير مذكورة.';

        return implode("\n", $lines);
    }

    public function buildContextFor(string $content, ?string $courtType = null, int $topK = 5): string
    {
        return $this->buildContext($this->retrieve($content, $courtType, $topK));
    }

    public function totalDistilled(): int
    {
        return DistilledKnowledge::count();
    }
}
