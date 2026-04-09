<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use App\Services\OcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * ExtractDocumentTextJob
 * ──────────────────────
 * Runs OCR on a document's underlying file in the background:
 *   • Image files (.jpg/.png/.tiff/.webp) → OcrService::ocrImage()
 *   • PDF files where synchronous text extraction failed → OcrService::ocrPdf()
 *
 * On success: fills LegalDocument::content, sets metadata.extraction_status,
 * triggers Elasticsearch reindex, notifies the uploader.
 *
 * On failure (Tesseract missing, OCR returned nothing, etc.): records the
 * reason in metadata.extraction_error and notifies the uploader so they can
 * see why the content is empty on the document page.
 *
 * Dispatched from DocumentController::store() when the file can't be parsed
 * with the synchronous TextExtractorService.
 */
class ExtractDocumentTextJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** OCR failures are usually deterministic — don't retry. */
    public int $tries = 1;

    /** Allow up to 10 minutes for large multi-page PDFs. */
    public int $timeout = 600;

    public function __construct(public LegalDocument $document)
    {
    }

    public function handle(OcrService $ocr, ElasticsearchService $es): void
    {
        $document = $this->document->fresh();
        if (! $document || ! $document->file_path) {
            return;
        }

        $absolutePath = Storage::disk('public')->path($document->file_path);
        if (! is_file($absolutePath)) {
            $this->markFailed($document, 'الملف غير موجود على القرص.');
            return;
        }

        $status = $ocr->status();
        if ($status !== OcrService::STATUS_READY) {
            $this->markFailed($document, match ($status) {
                OcrService::STATUS_MISSING_TESSERACT =>
                    'Tesseract غير منصّب على الخادم. ثبّت tesseract-ocr مع حزمة اللغة العربية (tesseract-ocr-ara) ثم أعد رفع الملف.',
                OcrService::STATUS_MISSING_PDFTOPPM  =>
                    'أداة pdftoppm (Poppler) غير منصّبة على الخادم. ثبّت Poppler ثم أعد رفع الملف.',
                default => 'بيئة OCR غير جاهزة على الخادم.',
            });
            return;
        }

        $ext = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION));
        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'tiff', 'tif', 'webp', 'gif', 'bmp'], true);
        $isPdf = $ext === 'pdf';

        if (! $isImage && ! $isPdf) {
            $this->markFailed($document, 'نوع الملف غير مدعوم لـ OCR.');
            return;
        }

        try {
            $text = $isImage ? $ocr->ocrImage($absolutePath) : $ocr->ocrPdf($absolutePath);

            if ($text === null || trim($text) === '') {
                $this->markFailed($document, 'لم يتمكن OCR من استخراج أي نص من الملف. قد تكون جودة الصورة منخفضة جداً.');
                return;
            }

            $document->content = $text;
            $meta = $document->metadata ?? [];
            $meta['extraction_status'] = 'extracted_via_ocr';
            $meta['extracted_at'] = now()->toIso8601String();
            unset($meta['extraction_error'], $meta['failed_at']);
            $document->metadata = $meta;
            $document->save();

            $es->reindexDocument($document);

            AppNotification::notify(
                userId: $document->uploaded_by,
                type: 'ocr_success',
                title: 'تم استخراج محتوى المستند',
                body: 'اكتمل استخراج النص من المستند "' . $document->title . '" عبر OCR وأصبح قابلاً للبحث.',
                data: ['document_id' => $document->id]
            );
        } catch (Throwable $e) {
            Log::error('OCR job failed', [
                'document_id' => $document->id,
                'error' => $e->getMessage(),
            ]);
            $this->markFailed($document, 'خطأ أثناء معالجة OCR: ' . $e->getMessage());
        }
    }

    private function markFailed(LegalDocument $document, string $reason): void
    {
        $meta = $document->metadata ?? [];
        $meta['extraction_status'] = 'failed';
        $meta['extraction_error'] = $reason;
        $meta['failed_at'] = now()->toIso8601String();
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'ocr_failed',
            title: 'فشل استخراج محتوى المستند',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
