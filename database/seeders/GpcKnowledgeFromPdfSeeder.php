<?php

namespace Database\Seeders;

use App\Models\GpcArticle;
use App\Services\ArabicTextNormalizerService;
use Illuminate\Database\Seeder;

/**
 * GpcKnowledgeFromPdfSeeder
 * ─────────────────────────
 * Parses the official "ربط النظام باللائحة" PDF (published by Etimad / هيئة
 * المحتوى المحلي والمشتريات الحكومية) into structured gpc_knowledge rows.
 *
 * Document structure: nation articles (labeled "المادة N البــاب | X ... النظام")
 * are followed by their corresponding regulation articles (labeled
 * "اللائحـة المادة Y"). Article numbers reset independently per source.
 *
 * The text extraction comes from TextExtractorService — expect small formatting
 * artifacts (broken ligatures, stray spaces). We clean aggressively before
 * storing so RAG retrieval and display stay readable.
 *
 * Source: expects the extracted text at storage/app/private/gpc-source.txt.
 * Upload the PDF via `php artisan mizaan:import-gpc-pdf /path/to/file.pdf`
 * (see the artisan command) to regenerate the source file, then re-run this
 * seeder.
 */
class GpcKnowledgeFromPdfSeeder extends Seeder
{
    public function run(): void
    {
        $sourcePath = storage_path('app/private/gpc-source.txt');

        if (! is_file($sourcePath)) {
            $this->command->warn("GPC source text not found at {$sourcePath}. Run: php artisan mizaan:import-gpc-pdf /path/to/pdf first.");
            return;
        }

        $raw = file_get_contents($sourcePath);
        $articles = $this->parse($raw);

        $normalizer = app(ArabicTextNormalizerService::class);

        $stats = ['system' => 0, 'regulation' => 0, 'skipped' => 0];

        foreach ($articles as $article) {
            if (mb_strlen($article['content']) < 40) {
                $stats['skipped']++;
                continue;
            }

            GpcArticle::updateOrCreate(
                [
                    'source'         => $article['source'],
                    'article_number' => $article['article_number'],
                ],
                [
                    'source_label'  => GpcArticle::SOURCES[$article['source']] ?? $article['source'],
                    'article_label' => $article['article_label'],
                    'chapter'       => $article['chapter'],
                    'topic'         => $article['topic'],
                    'content'       => $article['content'],
                    'normalized'    => $normalizer->normalize($article['content']),
                    'keywords'      => $article['keywords'],
                ],
            );

            $stats[$article['source']]++;
        }

        $this->command->info(sprintf(
            'GPC from PDF: %d nation articles, %d regulation articles (skipped %d short).',
            $stats['system'], $stats['regulation'], $stats['skipped'],
        ));
    }

    /**
     * @return list<array{source:string,article_number:string,article_label:string,chapter:?string,topic:?string,content:string,keywords:list<string>}>
     */
    private function parse(string $text): array
    {
        $markers = [];

        // Nation articles — 2-line header:
        //   Line 1: "المادة N البـاب | X [book_name] النظام"
        //   Line 2: "الفصل | Y [chapter_name]"   (optional — early articles may skip it)
        if (preg_match_all(
            '/المادة\s+(\d+)\s+الب[ـ]*اب\s*\|\s*(\d+)\s*([^\n]{1,120}?)النظـ*ـام[\s\n]*(?:الفصل\s*\|\s*(\d+)\s*([^\n]{1,120}))?/u',
            $text,
            $m,
            PREG_OFFSET_CAPTURE,
        )) {
            foreach ($m[0] as $i => $full) {
                $markers[] = [
                    'offset'     => $full[1],
                    'headerLen'  => mb_strlen($full[0]),
                    'source'     => 'system',
                    'number'     => $m[1][$i][0],
                    'bookNum'    => $m[2][$i][0],
                    'bookName'   => trim($m[3][$i][0]),
                    'chapterNum' => $m[4][$i][0] ?? null,
                    'chapterName'=> trim($m[5][$i][0] ?? ''),
                ];
            }
        }

        // Regulation articles: "اللائحـة المادة N"
        // The source uses kashida between ح and ة consistently (اللائحـة).
        if (preg_match_all('/اللائحـ*ـ?[ةه]\s+المادة\s+(\d+)/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $full) {
                $markers[] = [
                    'offset'     => $full[1],
                    'headerLen'  => mb_strlen($full[0]),
                    'source'     => 'regulation',
                    'number'     => $m[1][$i][0],
                    'bookNum'    => null,
                    'bookName'   => null,
                    'chapterNum' => null,
                    'chapterName'=> null,
                ];
            }
        }

        // Sort by offset and deduplicate (first occurrence of a given source+number wins)
        usort($markers, fn ($a, $b) => $a['offset'] <=> $b['offset']);

        $seen = [];
        $deduped = [];
        foreach ($markers as $marker) {
            $key = $marker['source'] . '#' . $marker['number'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $marker;
        }
        $markers = $deduped;

        $articles = [];
        for ($i = 0, $n = count($markers); $i < $n; $i++) {
            $current = $markers[$i];
            $end = $i + 1 < $n ? $markers[$i + 1]['offset'] : mb_strlen($text);

            // Content starts AFTER the header, ends at the next marker's offset.
            $contentStart = $current['offset'] + $current['headerLen'];
            $content = mb_substr($text, $contentStart, $end - $contentStart);

            // Carry forward the last known nation chapter context onto regulation articles
            if ($current['source'] === 'regulation') {
                $lastNation = $this->previousNation($markers, $i);
                $current['bookNum']    = $lastNation['bookNum']    ?? null;
                $current['bookName']   = $lastNation['bookName']   ?? null;
                $current['chapterNum'] = $lastNation['chapterNum'] ?? null;
                $current['chapterName']= $lastNation['chapterName']?? null;
            }

            $articles[] = [
                'source'         => $current['source'],
                'article_number' => $current['number'],
                'article_label'  => $current['source'] === 'system'
                    ? 'المادة ' . $current['number']
                    : 'اللائحة - المادة ' . $current['number'],
                'chapter'        => $current['bookName']
                    ? trim('الباب ' . $current['bookNum'] . ' - ' . $current['bookName'])
                    : null,
                'topic'          => $current['chapterName']
                    ? trim('الفصل ' . $current['chapterNum'] . ' - ' . $current['chapterName'])
                    : null,
                'content'        => $this->cleanContent($content),
                'keywords'       => $this->extractKeywords($content),
            ];
        }

        return $articles;
    }

    /** @param list<array<string,mixed>> $markers */
    private function previousNation(array $markers, int $upTo): ?array
    {
        for ($j = $upTo - 1; $j >= 0; $j--) {
            if ($markers[$j]['source'] === 'system') {
                return $markers[$j];
            }
        }
        return null;
    }

    private function cleanContent(string $content): string
    {
        // Strip page numbers scattered through: " 45 | " or stand-alone digits
        $content = preg_replace('/\n\s*\d{1,3}\s*\|\s*/u', "\n", $content) ?? $content;
        $content = preg_replace('/\n\s*\d{1,3}\s*\n/u', "\n", $content) ?? $content;

        // Collapse tatweel used for visual padding (keep meaningful ones)
        $content = preg_replace('/ـ{2,}/u', '', $content) ?? $content;

        // Collapse multiple spaces + strip leading/trailing noise
        $content = preg_replace('/[ \t]+/u', ' ', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;

        return trim($content);
    }

    /**
     * Pick 5-8 keyword-like tokens by length + frequency — simple but good
     * enough for the LIKE-based RAG retriever.
     *
     * @return list<string>
     */
    private function extractKeywords(string $content): array
    {
        $tokens = preg_split('/[\s،؛.()\-—]+/u', $content) ?: [];
        $counts = [];
        foreach ($tokens as $token) {
            $token = trim($token);
            if (mb_strlen($token) < 4 || mb_strlen($token) > 20) continue;
            if (preg_match('/[0-9]/u', $token)) continue;
            // Skip common filler words
            if (in_array($token, ['التي', 'الذي', 'التي', 'عليها', 'بحسب', 'يجب', 'يتم', 'بعد', 'قبل', 'وفقا', 'وفقاً'], true)) continue;
            $counts[$token] = ($counts[$token] ?? 0) + 1;
        }
        arsort($counts);

        return array_values(array_slice(array_keys($counts), 0, 8));
    }
}
