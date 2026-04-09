<?php

namespace App\Services;

/**
 * ArabicTextNormalizerService
 * ───────────────────────────
 * A focused service for normalizing Arabic text. Each normalization step
 * is exposed as its own method so callers can compose what they need —
 * `normalize()` runs the full pipeline.
 *
 * Responsibilities (only these):
 *   • remove diacritics (tashkeel)
 *   • remove tatweel (ـ)
 *   • normalize alef variants  (أ إ آ ٱ → ا)
 *   • normalize yaa / alif maqsura  (ى → ي)   [optional]
 *   • normalize taa marbuta  (ة → ه)          [optional]
 *   • collapse whitespace
 *
 * NOT responsible for: stemming, stop-words, punctuation, lowercasing,
 * tokenization, or anything search-specific. Keep those in a separate layer.
 */
class ArabicTextNormalizerService
{
    /** Tashkeel (fatha, damma, kasra, sukun, shadda, tanween, etc.) + dagger alef */
    private const DIACRITICS = '/[\x{064B}-\x{065F}\x{0670}]/u';

    /** Tatweel character (kashida) */
    private const TATWEEL = "\u{0640}";

    public function __construct(
        private bool $normalizeAlifMaqsura = true,
        private bool $normalizeTaaMarbuta  = true,
    ) {}

    /**
     * Run the full normalization pipeline on a string.
     */
    public function normalize(string $text): string
    {
        $text = $this->removeDiacritics($text);
        $text = $this->removeTatweel($text);
        $text = $this->normalizeAlef($text);

        if ($this->normalizeAlifMaqsura) {
            $text = $this->normalizeYaa($text);
        }

        if ($this->normalizeTaaMarbuta) {
            $text = $this->normalizeTaaMarbutaStep($text);
        }

        $text = $this->trimExtraSpaces($text);

        return $text;
    }

    // ─────────────────────────────────────────────────────────────
    // Individual steps — each is pure and idempotent
    // ─────────────────────────────────────────────────────────────

    /** Strip all Arabic diacritics (harakat, tanween, shadda, dagger alef). */
    public function removeDiacritics(string $text): string
    {
        return preg_replace(self::DIACRITICS, '', $text) ?? $text;
    }

    /** Strip the tatweel (kashida) character used to elongate words. */
    public function removeTatweel(string $text): string
    {
        return str_replace(self::TATWEEL, '', $text);
    }

    /** Unify alef forms: أ إ آ ٱ → ا */
    public function normalizeAlef(string $text): string
    {
        return str_replace(['أ', 'إ', 'آ', 'ٱ'], 'ا', $text);
    }

    /** Convert alef maqsura to yaa: ى → ي */
    public function normalizeYaa(string $text): string
    {
        return str_replace('ى', 'ي', $text);
    }

    /** Convert taa marbuta to haa: ة → ه (helps "كلمة" match "كلمه") */
    public function normalizeTaaMarbutaStep(string $text): string
    {
        return str_replace('ة', 'ه', $text);
    }

    /** Collapse runs of whitespace into a single space and trim ends. */
    public function trimExtraSpaces(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }

    // ─────────────────────────────────────────────────────────────
    // Configuration helpers (immutable copies)
    // ─────────────────────────────────────────────────────────────

    public function withTaaMarbuta(bool $on): self
    {
        $clone = clone $this;
        $clone->normalizeTaaMarbuta = $on;
        return $clone;
    }

    public function withAlifMaqsura(bool $on): self
    {
        $clone = clone $this;
        $clone->normalizeAlifMaqsura = $on;
        return $clone;
    }
}
