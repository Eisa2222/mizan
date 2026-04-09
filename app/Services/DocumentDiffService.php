<?php

namespace App\Services;

use App\Models\DocumentChunk;
use App\Models\LegalDocument;
use Illuminate\Support\Collection;

/**
 * DocumentDiffService
 * ───────────────────
 * Compares the article-level content of an existing LegalDocument against
 * a freshly extracted text body (typically from a newly uploaded version).
 *
 * Strategy:
 *   1. Re-chunk the new content via DocumentChunker (in-memory only — we
 *      don't write document_chunks for the candidate body, just inspect it).
 *   2. Group chunks by `label` (e.g. "المادة 14") on both sides.
 *   3. For each label, compare the *normalized* content via the existing
 *      ArabicTextNormalizerService. If they differ, emit a diff entry.
 *
 * The result is a Collection of plain arrays the caller can use to:
 *   • create ArticleUpdate rows (auto_generated=true)
 *   • show a summary in a notification
 *
 * Pure logic — no DB writes, no notifications. Easy to unit-test.
 */
class DocumentDiffService
{
    public function __construct(
        private DocumentChunker $chunker,
        private ArabicTextNormalizerService $normalizer,
    ) {}

    /**
     * Diff the existing document's chunks against new text content.
     *
     * @return Collection<int, array{label:string, old:string, new:string, change:string}>
     *   Each entry: label, old text, new text, and a change classifier
     *   (added | removed | modified).
     */
    public function diff(LegalDocument $document, string $newContent): Collection
    {
        // Build a label → text map for the existing chunks (already in DB)
        $oldByLabel = $document->chunks()
            ->whereNotNull('label')
            ->get()
            ->groupBy('label')
            ->map(fn (Collection $group) => $this->joinChunks($group));

        // Build the same map for the new content WITHOUT touching the DB.
        // We synthesize chunks by reusing the chunker's article splitter.
        $newByLabel = $this->chunkInMemory($newContent);

        $changes = collect();

        // Pass 1: anything in old that changed or was removed in new
        foreach ($oldByLabel as $label => $oldText) {
            $newText = $newByLabel->get($label);
            if ($newText === null) {
                // Article exists in old but not new — removed
                $changes->push([
                    'label'  => $label,
                    'old'    => $oldText,
                    'new'    => '',
                    'change' => 'removed',
                ]);
                continue;
            }
            if ($this->normalizedEquals($oldText, $newText)) {
                continue; // unchanged — skip
            }
            $changes->push([
                'label'  => $label,
                'old'    => $oldText,
                'new'    => $newText,
                'change' => 'modified',
            ]);
        }

        // Pass 2: anything in new that didn't exist in old
        foreach ($newByLabel as $label => $newText) {
            if (! $oldByLabel->has($label)) {
                $changes->push([
                    'label'  => $label,
                    'old'    => '',
                    'new'    => $newText,
                    'change' => 'added',
                ]);
            }
        }

        return $changes->values();
    }

    /**
     * Run the same article-splitting logic as DocumentChunker but produce a
     * label→text map in memory (no DB writes). Re-uses the chunker's splitter
     * via reflection-friendly delegation: we ask the chunker to chunk a
     * temporary, unsaved document and read its result.
     *
     * To avoid actually persisting chunks, we use a transient document with
     * id=0 and call the chunker's article splitter through a small helper.
     * Since DocumentChunker::chunkAndStore() persists, we instead replicate
     * the article-split heuristic directly here for zero side-effects.
     */
    private function chunkInMemory(string $content): Collection
    {
        $source = trim($content);
        if ($source === '') {
            return collect();
        }

        // Same article pattern as DocumentChunker::splitByArticles()
        $pattern = '/(المادة\s*[\(\[]?\s*\S{1,20}\s*[\)\]]?)/u';
        if (! preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE)) {
            return collect(); // no labeled articles in the new content
        }

        $byLabel = collect();
        $count = count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $start = $matches[0][$i][1];
            $end = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($source);
            $label = trim($matches[0][$i][0]);
            $text = trim(substr($source, $start, $end - $start));
            if ($text === '') continue;

            // If the same label appears multiple times in the new content,
            // join their bodies — same convention used by joinChunks() for old.
            $existing = $byLabel->get($label, '');
            $byLabel->put($label, $existing === '' ? $text : $existing . "\n" . $text);
        }

        return $byLabel;
    }

    /** Join multiple chunk rows that share a label (e.g. long articles split into pieces). */
    private function joinChunks(Collection $chunks): string
    {
        return $chunks
            ->sortBy('chunk_index')
            ->map(fn (DocumentChunk $c) => $c->content)
            ->implode("\n");
    }

    /** Two texts are equal if their Arabic-normalized forms match exactly. */
    private function normalizedEquals(string $a, string $b): bool
    {
        return $this->normalizer->normalize($a) === $this->normalizer->normalize($b);
    }
}
