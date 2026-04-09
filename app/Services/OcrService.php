<?php

namespace App\Services;

use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

/**
 * OcrService
 * ──────────
 * Wraps Tesseract OCR (via thiagoalessio/tesseract_ocr) for Arabic document
 * extraction. Handles both standalone images and PDFs (by rasterizing pages
 * via Poppler's `pdftoppm` first, since Tesseract cannot read PDFs directly).
 *
 * Both `tesseract` and `pdftoppm` must be installed on the host machine and
 * available on the PATH. Use status() / isAvailable() to detect this before
 * dispatching work.
 */
class OcrService
{
    public const STATUS_READY = 'ready';
    public const STATUS_MISSING_TESSERACT = 'missing_tesseract';
    public const STATUS_MISSING_PDFTOPPM = 'missing_pdftoppm';

    /** PNG resolution used when rasterizing PDF pages. Higher = better OCR, slower. */
    private const PDF_DPI = 300;

    /** Languages passed to Tesseract. ara+eng covers Arabic docs with mixed Latin. */
    private const LANGS = ['ara', 'eng'];

    public function status(): string
    {
        if (! $this->commandExists('tesseract')) {
            return self::STATUS_MISSING_TESSERACT;
        }
        if (! $this->commandExists('pdftoppm')) {
            return self::STATUS_MISSING_PDFTOPPM;
        }
        return self::STATUS_READY;
    }

    public function isAvailable(): bool
    {
        return $this->status() === self::STATUS_READY;
    }

    /**
     * OCR a single image file. Returns extracted text or null on failure.
     */
    public function ocrImage(string $imagePath): ?string
    {
        if (! is_file($imagePath)) return null;

        try {
            $text = (new TesseractOCR($imagePath))
                ->lang(...self::LANGS)
                ->run();
            return $this->cleanup((string) $text);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * OCR a PDF file by rasterizing each page to PNG via pdftoppm, then
     * running Tesseract on each page. Concatenates the results.
     */
    public function ocrPdf(string $pdfPath): ?string
    {
        if (! is_file($pdfPath)) return null;

        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mizan_ocr_' . uniqid('', true);
        if (! @mkdir($tmpDir, 0777, true) && ! is_dir($tmpDir)) {
            return null;
        }

        try {
            $prefix = $tmpDir . DIRECTORY_SEPARATOR . 'page';
            $cmd = sprintf(
                'pdftoppm -r %d -png %s %s 2>&1',
                self::PDF_DPI,
                escapeshellarg($pdfPath),
                escapeshellarg($prefix)
            );
            @exec($cmd, $output, $exitCode);
            if ($exitCode !== 0) {
                return null;
            }

            $pages = glob($prefix . '-*.png') ?: [];
            if (count($pages) === 0) return null;
            sort($pages);

            $allText = [];
            foreach ($pages as $page) {
                $pageText = $this->ocrImage($page);
                if ($pageText !== null && $pageText !== '') {
                    $allText[] = $pageText;
                }
            }

            if (count($allText) === 0) return null;
            return $this->cleanup(implode("\n\n", $allText));
        } catch (Throwable $e) {
            return null;
        } finally {
            $files = glob($tmpDir . DIRECTORY_SEPARATOR . '*') ?: [];
            foreach ($files as $f) @unlink($f);
            @rmdir($tmpDir);
        }
    }

    /**
     * Cross-platform check for whether a CLI command is on the PATH.
     */
    private function commandExists(string $cmd): bool
    {
        $finder = stripos(PHP_OS, 'WIN') === 0 ? 'where' : 'command -v';
        @exec($finder . ' ' . escapeshellarg($cmd) . ' 2>&1', $out, $code);
        return $code === 0;
    }

    /**
     * Same cleanup logic as TextExtractorService — keep them in sync if changed.
     */
    private function cleanup(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text) ?? $text;
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;
        return trim($text);
    }
}
