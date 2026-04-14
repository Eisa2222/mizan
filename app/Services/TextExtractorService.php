<?php

namespace App\Services;

use Smalot\PdfParser\Parser as PdfParser;
use Throwable;
use ZipArchive;

/**
 * TextExtractorService
 * ────────────────────
 * Extracts plain text from common document formats so it can be chunked,
 * indexed for search, and rendered for highlighting/annotations.
 *
 * Supported:
 *   • PDF  → smalot/pdfparser
 *   • DOCX → unzip + parse word/document.xml
 *   • TXT  → file_get_contents (with charset detection)
 *
 * Returns null if extraction fails so callers can fall back gracefully.
 */
class TextExtractorService
{
    public function extract(string $absolutePath): ?string
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) return null;

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));

        $text = match ($ext) {
            'pdf'        => $this->extractPdf($absolutePath),
            'docx'       => $this->extractDocx($absolutePath),
            'xlsx','xls' => $this->extractXlsx($absolutePath),
            'doc'        => null,
            'txt'        => $this->extractTxt($absolutePath),
            default      => null,
        };

        // Quality check — reject mojibake / garbled Arabic
        if ($text !== null && !$this->looksValid($text)) {
            return null;
        }

        return $text;
    }

    /**
     * Heuristic: extracted text is "valid" if it has a reasonable ratio of
     * letters/digits to noise.
     *
     * Counts use chunked preg_match_all so a single PCRE_BAD_UTF8 hit on a
     * large input doesn't sink the whole judgment. extractPdf() also scrubs
     * malformed UTF-8 upstream as a belt-and-braces.
     */
    private function looksValid(string $text): bool
    {
        $len = mb_strlen($text);
        if ($len < 30) return true; // too short to judge

        $arabic = $this->countMatches('/[\x{0621}-\x{064A}]/u', $text);
        $latin  = $this->countMatches('/[A-Za-z]/u', $text);
        $digits = $this->countMatches('/[0-9\x{0660}-\x{0669}]/u', $text);
        $usable = $arabic + $latin + $digits;

        // At least 25% of the characters should be readable letters/digits.
        return ($usable / $len) >= 0.25;
    }

    /**
     * Run preg_match_all on a string in chunks to side-step PCRE limits
     * and to avoid the silent `false → 0` failure mode when a single
     * malformed byte trips up the whole regex.
     */
    private function countMatches(string $pattern, string $text, int $chunkSize = 16384): int
    {
        $total = 0;
        // Use mb_str_split to keep multi-byte characters intact across chunk
        // boundaries — str_split would corrupt UTF-8 sequences.
        foreach (mb_str_split($text, $chunkSize, 'UTF-8') as $chunk) {
            $count = @preg_match_all($pattern, $chunk);
            if ($count !== false) {
                $total += $count;
            }
        }
        return $total;
    }

    private function extractPdf(string $path): ?string
    {
        // Skip very large PDFs that exhaust memory with smalot/pdfparser
        if (filesize($path) > 30 * 1024 * 1024) return null; // 30MB limit

        try {
            $parser = new PdfParser();
            $pdf = $parser->parseFile($path);
            $text = $pdf->getText();
            // CRITICAL: smalot occasionally emits malformed UTF-8 byte
            // sequences that make every `/u` regex below silently return
            // false. Scrub them with the substitute char before anything
            // else. Without this, looksValid() can return 0 even for
            // perfectly readable text, causing extraction to "fail".
            if (! mb_check_encoding($text, 'UTF-8')) {
                $text = mb_scrub($text, 'UTF-8');
            }
            // PDF-specific quirks fixed in order:
            //   1. Smalot returns unmappable glyphs as `&#NNNNNN;` numeric
            //      references. Scrub the ones outside the Unicode range.
            //   2. fixVisualOrderArabic now handles BOTH the visual/logical
            //      detection AND the ligature decomposition (Unicode NFKC)
            //      because the right way to decompose a ligature depends on
            //      whether the surrounding text is visual or logical order.
            //      Calling NFKC unconditionally before reversal corrupts
            //      ligatures like ﷲ ﳌ ﻻ — they decompose in *logical* order
            //      but the surrounding chars are still in visual order, so
            //      reversing the word leaves the ligature chars backwards.
            $text = $this->stripBrokenGlyphEntities($text);
            $text = $this->fixVisualOrderArabic($text);
            $text = $this->fixBrokenCmapSubstitutions($text);
            return $this->cleanup($text);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Replace numeric character references that point outside the Unicode
     * range (max 0x10FFFF) with a single space. These come from PDF font
     * cmap tables that smalot couldn't translate to actual codepoints.
     */
    private function stripBrokenGlyphEntities(string $text): string
    {
        return preg_replace_callback(
            '/&#(\d+);/',
            function ($m) {
                $code = (int) $m[1];
                // Valid Unicode codepoints are 0..0x10FFFF (1,114,111).
                // Anything beyond that is a glyph index, not a real character.
                return $code > 0x10FFFF ? ' ' : $m[0];
            },
            $text
        ) ?? $text;
    }

    /**
     * Detect "visual-order" Arabic (each word's glyphs reversed) and flip
     * each Arabic word back to logical order so it renders correctly.
     *
     * Heuristic: in correctly-ordered Arabic, the definite article "ال"
     * (alef-then-lam in memory) leads many words. When the PDF stores
     * glyphs in visual order, every word is character-reversed, so the
     * "ال" prefix becomes a "لا" suffix (lam-then-alef in memory).
     *
     * Ligature handling: Arabic Presentation Forms (ﷲ ﳌ ﻻ ...) are stored
     * as a single codepoint per glyph. NFKC decomposition would expand them
     * in *logical* order, but for visual-order text we need them in *visual*
     * order so the subsequent reversal lands the chars back in logical order.
     * That's what expandLigaturesForVisualOrder does — it inlines each
     * ligature's NFKC decomposition reversed.
     *
     * For logical-order text we apply NFKC normally and return.
     */
    private function fixVisualOrderArabic(string $text): string
    {
        // Build a normalized sample so the heuristic can match basic Arabic
        // letters even when the source uses Presentation Forms exclusively.
        $sample = mb_substr($text, 0, 5000);
        if (class_exists(\Normalizer::class)) {
            $normalizedSample = \Normalizer::normalize($sample, \Normalizer::FORM_KC);
            if ($normalizedSample !== false) {
                $sample = $normalizedSample;
            }
        }

        // Words that *start* with ال (alef-lam) — logical order signature
        $logical = preg_match_all('/(?<![\x{0621}-\x{064A}])ال[\x{0621}-\x{064A}]{2,}/u', $sample) ?: 0;
        // Words that *end* with لا (lam-alef in memory) — visual-order signature
        $visual = preg_match_all('/[\x{0621}-\x{064A}]{2,}لا(?![\x{0621}-\x{064A}])/u', $sample) ?: 0;

        // Need clear visual signal: at least 8 reversed-style hits AND more
        // than logical hits. Otherwise it's logical text — apply NFKC and return.
        if ($visual < 8 || $visual <= $logical) {
            if (class_exists(\Normalizer::class)) {
                return \Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text;
            }
            return $text;
        }

        // Visual order: expand each ligature into its REVERSED decomposition
        // first so the characters end up in correct logical order after
        // we reverse the surrounding word.
        $text = $this->expandLigaturesForVisualOrder($text);

        // Step 1: reverse each Arabic-only run so individual words read correctly.
        $text = preg_replace_callback(
            '/[\x{0600}-\x{06FF}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+/u',
            fn ($m) => $this->mbStrrev($m[0]),
            $text
        ) ?? $text;

        // Step 2: PDF visual order also flips word order on each line
        // (left-most word in storage = right-most in logical Arabic). Reverse
        // word order on each line so the RTL flow lines up. This isn't a
        // full Bidi pass — mixed Arabic/Latin/digit tokens may still look
        // off — but for pure-Arabic legal text it's a big readability win.
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            $words = preg_split('/(\s+)/u', $line, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [];
            // Strip the captured whitespace tokens, reverse just the word slots,
            // then re-join with single spaces (collapsed by cleanup() later).
            $only = array_values(array_filter($words, fn ($w) => $w !== '' && !preg_match('/^\s+$/u', $w)));
            if (count($only) > 1) {
                $lines[$i] = implode(' ', array_reverse($only));
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Replace each Arabic Presentation Form ligature with its NFKC
     * decomposition *reversed*. This is the key fix that makes ligatures
     * survive the per-word character reversal in visual-order PDFs.
     *
     * Examples:
     *   ﷲ (Allah, U+FDF2)        NFKC = "الله"  → reversed → "هللا"
     *   ﳌ (lam-meem, U+FCCC)     NFKC = "لم"    → reversed → "مل"
     *   ﻻ (lam-alef, U+FEFB)     NFKC = "لا"    → reversed → "ال"
     *
     * Single-letter shaping forms (initial/medial/final/isolated of one
     * letter) decompose to one char and don't need any reversal.
     */
    private function expandLigaturesForVisualOrder(string $text): string
    {
        if (! class_exists(\Normalizer::class)) {
            return $text;
        }

        return preg_replace_callback(
            '/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u',
            function ($m) {
                $char = $m[0];
                $decomp = \Normalizer::normalize($char, \Normalizer::FORM_KC);
                if ($decomp === false || $decomp === $char) {
                    return $char;
                }
                if (mb_strlen($decomp) <= 1) {
                    return $decomp;
                }
                return $this->mbStrrev($decomp);
            },
            $text
        ) ?? $text;
    }

    /**
     * Fix broken cmap substitutions where the PDF font maps Arabic letters
     * to ASCII characters. This is common in PDFs generated by certain
     * Saudi government publishing tools.
     *
     * Detected pattern: Latin/special chars (`& @ m d r`) appear INSIDE
     * Arabic words where they clearly represent Arabic letters. We replace
     * them only when flanked by Arabic characters to avoid corrupting
     * legitimate Latin text in mixed-language passages.
     *
     * Heuristic activation: we only run these replacements when the text
     * has at least 20 instances of the `ا&` pattern (Arabic alef + ampersand),
     * which is the most reliable signature of this particular cmap bug.
     */
    private function fixBrokenCmapSubstitutions(string $text): string
    {
        // Only activate for texts that clearly have this cmap bug
        if (mb_substr_count($text, 'ا&') < 20) {
            return $text;
        }

        $arab = '\x{0600}-\x{06FF}';

        // In these broken PDFs, the cmap maps specific Arabic letter combos
        // to ASCII chars. The key insight: each broken char replaces a MULTI-
        // character Arabic sequence (usually starting with lam or alef-lam),
        // not a single Arabic letter.

        // r → لا (lam-alef ligature) — "ثrث" → "ثلاث", "خrف" → "خلاف"
        $text = preg_replace("/([$arab])r([$arab])/u", '$1لا$2', $text) ?? $text;
        $text = preg_replace("/([$arab])r([$arab])/u", '$1لا$2', $text) ?? $text;

        // & → لأ (lam + hamza above) — "ا&ول" → "الأول", "ا&حكام" → "الأحكام"
        // The ا before & is the real alef; & replaces لأ (lam-hamza).
        $text = preg_replace("/([$arab])&([$arab])/u", '$1لأ$2', $text) ?? $text;
        $text = preg_replace("/([$arab])&([$arab])/u", '$1لأ$2', $text) ?? $text;

        // @ → لإ (lam + hamza below) — "ا@عاقة" → "الإعاقة", "ا@ستقدام" → "الإستقدام"
        $text = preg_replace("/([$arab])@([$arab])/u", '$1لإ$2', $text) ?? $text;
        $text = preg_replace("/([$arab])@([$arab])/u", '$1لإ$2', $text) ?? $text;

        // d → لإ (variant of above in some cmaps) — "اdجازات" → "الإجازات"
        $text = preg_replace("/([$arab])d([$arab])/u", '$1لإ$2', $text) ?? $text;
        $text = preg_replace("/([$arab])d([$arab])/u", '$1لإ$2', $text) ?? $text;

        // m → لآ (lam + madda) — "اmتية" → "الآتية"
        $text = preg_replace("/([$arab])m([$arab])/u", '$1لآ$2', $text) ?? $text;
        $text = preg_replace("/([$arab])m([$arab])/u", '$1لآ$2', $text) ?? $text;

        return $text;
    }

    /** Multi-byte string reverse (PHP has no built-in mb_strrev). */
    private function mbStrrev(string $str): string
    {
        $chars = preg_split('//u', $str, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return implode('', array_reverse($chars));
    }

    private function extractDocx(string $path): ?string
    {
        if (!class_exists(ZipArchive::class)) return null;

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return null;

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if ($xml === false) return null;

        // Replace <w:p> tags with newlines, then strip all XML
        $text = preg_replace('/<w:p[^>]*>/', "\n", $xml);
        $text = preg_replace('/<[^>]+>/', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');

        return $this->cleanup($text);
    }

    private function extractTxt(string $path): ?string
    {
        $raw = @file_get_contents($path);
        if ($raw === false) return null;

        // Detect encoding and convert to UTF-8 if needed
        // PHP 8.3's mbstring dropped Windows-1256 from its detect list, so we
        // try UTF-8 first, then fall back to iconv for the common Arabic
        // single-byte encodings (Windows-1256 / ISO-8859-6) which iconv still
        // ships with universally.
        if (! mb_check_encoding($raw, 'UTF-8')) {
            foreach (['Windows-1256', 'ISO-8859-6'] as $enc) {
                $converted = @iconv($enc, 'UTF-8//IGNORE', $raw);
                if ($converted !== false && mb_check_encoding($converted, 'UTF-8')) {
                    $raw = $converted;
                    break;
                }
            }
        }

        return $this->cleanup($raw);
    }

    /**
     * Extract text from Excel (.xlsx/.xls) files via PhpSpreadsheet.
     * Reads all sheets and joins cell values with tabs/newlines.
     */
    private function extractXlsx(string $path): ?string
    {
        // Skip large Excel files that exhaust memory with PhpSpreadsheet
        if (filesize($path) > 500 * 1024) return null; // 500KB limit

        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($path);
            $lines = [];
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                $sheetName = $sheet->getTitle();
                if ($spreadsheet->getSheetCount() > 1) {
                    $lines[] = "═══ {$sheetName} ═══";
                }
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = [];
                    foreach ($row->getCellIterator() as $cell) {
                        $val = trim((string) $cell->getFormattedValue());
                        if ($val !== '') $cells[] = $val;
                    }
                    if (! empty($cells)) {
                        $lines[] = implode("\t", $cells);
                    }
                }
            }
            $text = implode("\n", $lines);
            return $text !== '' ? $this->cleanup($text) : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** Trim, collapse runs of whitespace, normalize line endings. */
    private function cleanup(string $text): string
    {
        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Strip null bytes and most control chars (keep \n and \t)
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;

        // Collapse internal runs of spaces (but keep newlines)
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;

        // Collapse 3+ newlines into 2
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
