<?php

namespace Modules\Documents\Support;

/**
 * Splits a legal document's plain-text `content` into individual articles
 * for the reader view. Saudi statutes use "المادة N" as article headers
 * (where N is Arabic-Indic or Western digits). We cut on those headers
 * and keep the preamble text (anything before the first article) as a
 * separate section so it's displayed above the numbered list.
 */
class ArticleParser
{
    /**
     * @return array{preamble:string, articles:array<int,array{number:string, title:?string, body:string}>}
     */
    public static function parse(?string $content): array
    {
        $content = (string) $content;
        if (trim($content) === '') {
            return ['preamble' => '', 'articles' => []];
        }

        // Match "المادة N" or "المادّة N" at the start of a line — also tolerate
        // a colon, optional "الأولى/الثانية/..." ordinal, and Arabic-Indic digits.
        $pattern = '/(?:^|\n)\s*(?:المادّ?ة)\s*[:\-]?\s*((?:[٠-٩]+|[0-9]+|الأولى|الثانية|الثالثة|الرابعة|الخامسة|السادسة|السابعة|الثامنة|التاسعة|العاشرة|الحادية عشرة|الثانية عشرة|الثالثة عشرة|الرابعة عشرة|الخامسة عشرة))[\s:\-]*/u';

        if (! preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
            return ['preamble' => trim($content), 'articles' => []];
        }

        $articles = [];
        $count = count($matches[0]);

        $firstOffset = $matches[0][0][1];
        $preamble = trim(substr($content, 0, $firstOffset));

        for ($i = 0; $i < $count; $i++) {
            $headerStart = $matches[0][$i][1];
            $bodyStart   = $headerStart + strlen($matches[0][$i][0]);
            $bodyEnd     = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($content);

            $number = trim($matches[1][$i][0]);
            $body   = trim(substr($content, $bodyStart, $bodyEnd - $bodyStart));

            // Try to pull a short title from the first line if it's < 80 chars
            // and not already followed by paragraph prose.
            $title = null;
            $lines = preg_split('/\n/', $body, 2);
            if (count($lines) === 2 && mb_strlen($lines[0]) > 0 && mb_strlen($lines[0]) <= 80) {
                $firstLine = trim($lines[0]);
                // Heuristic: treat first line as title if it doesn't end with a
                // full stop or comma (statute headings are usually statements).
                if (! preg_match('/[.،,]\s*$/u', $firstLine)) {
                    $title = $firstLine;
                    $body  = trim($lines[1]);
                }
            }

            $articles[] = [
                'number' => $number,
                'title'  => $title,
                'body'   => $body,
            ];
        }

        return ['preamble' => $preamble, 'articles' => $articles];
    }
}
