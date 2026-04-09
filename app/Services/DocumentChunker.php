<?php

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\LegalDocument;
use App\Services\ArabicTextNormalizerService;

/**
 * Splits a legal document into searchable chunks.
 *
 * Strategy:
 *  1. Try to split by article markers (المادة (X)) — preserves legal structure.
 *  2. Fallback: paragraph splitter (double newline).
 *  3. Long chunks are further split with overlap to keep context.
 *
 * Each chunk gets:
 *  - content     : original text
 *  - normalized  : ArabicTextNormalizerService output (for search)
 *  - label       : "المادة 74" or "الفقرة 3" if detectable
 *  - char_start/end : position in source
 *  - token_count : approx
 */
class DocumentChunker
{
    public function __construct(private ArabicTextNormalizerService $normalizer) {}

    public const MAX_CHUNK_TOKENS = 220;
    public const OVERLAP_TOKENS = 30;
    public const MAX_CHUNKS_PER_DOC = 500;
    public const MIN_CHUNK_TOKENS = 4; // skip very short fragments

    /** Chunk a document and persist DocumentChunk rows. Returns count of chunks created. */
    public function chunkAndStore(LegalDocument $document): int
    {
        // Wipe old chunks for clean reindex
        $document->chunks()->delete();

        $source = trim(($document->summary ? $document->summary . "\n\n" : '') . ($document->content ?? ''));
        if ($source === '') return 0;

        $segments = $this->splitByArticles($source);
        $rows = [];
        $idx = 0;

        foreach ($segments as $seg) {
            if ($idx >= self::MAX_CHUNKS_PER_DOC) break;

            // Further split if too long
            $sub = $this->splitLong($seg['text'], self::MAX_CHUNK_TOKENS, self::OVERLAP_TOKENS);
            foreach ($sub as $piece) {
                if ($idx >= self::MAX_CHUNKS_PER_DOC) break;
                if ($this->approxTokenCount($piece) < self::MIN_CHUNK_TOKENS) continue;

                $rows[] = [
                    'document_id' => $document->id,
                    'chunk_index' => $idx++,
                    'content'     => $piece,
                    'normalized'  => $this->normalizer->normalize($piece),
                    'label'       => $seg['label'],
                    'char_start'  => $seg['start'],
                    'char_end'    => $seg['start'] + mb_strlen($piece),
                    'token_count' => $this->approxTokenCount($piece),
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ];
            }
        }

        if (empty($rows)) return 0;

        // Bulk insert
        DocumentChunk::insert($rows);
        return count($rows);
    }

    /**
     * Split content by article markers like "المادة (74):" or "المادة 74".
     * Returns array of ['text' => ..., 'label' => ..., 'start' => int].
     */
    private function splitByArticles(string $text): array
    {
        // Match: المادة (X), المادة X, المادة الأولى, etc.
        $pattern = '/(المادة\s*[\(\[]?\s*\S{1,20}\s*[\)\]]?)/u';

        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            // No article markers — fallback to paragraph split
            return $this->splitByParagraphs($text);
        }

        $segments = [];
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($text);
            $piece = trim(substr($text, $start, $end - $start));
            if ($piece === '') continue;
            $segments[] = [
                'text'  => $piece,
                'label' => trim($matches[0][$i][0]),
                'start' => mb_strlen(substr($text, 0, $start)),
            ];
        }

        // Prefix (anything before first article) becomes its own segment
        if ($matches[0][0][1] > 0) {
            $prefix = trim(substr($text, 0, $matches[0][0][1]));
            if ($prefix !== '') {
                array_unshift($segments, ['text' => $prefix, 'label' => 'ديباجة', 'start' => 0]);
            }
        }

        return $segments;
    }

    private function splitByParagraphs(string $text): array
    {
        $paras = preg_split('/\n\s*\n/u', $text) ?: [];
        $segments = [];
        $offset = 0;
        foreach ($paras as $i => $p) {
            $p = trim($p);
            if ($p === '') { $offset += 1; continue; }
            $segments[] = ['text' => $p, 'label' => 'فقرة ' . ($i + 1), 'start' => $offset];
            $offset += mb_strlen($p) + 2;
        }
        return $segments ?: [['text' => $text, 'label' => null, 'start' => 0]];
    }

    /** Split long text into ≤ maxTokens pieces with overlap. */
    private function splitLong(string $text, int $maxTokens, int $overlap): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if (count($words) <= $maxTokens) return [$text];

        $pieces = [];
        $start = 0;
        $total = count($words);
        while ($start < $total) {
            $slice = array_slice($words, $start, $maxTokens);
            $pieces[] = implode(' ', $slice);
            if ($start + $maxTokens >= $total) break;
            $start += $maxTokens - $overlap;
        }
        return $pieces;
    }

    private function approxTokenCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text)) ?: []);
    }
}
