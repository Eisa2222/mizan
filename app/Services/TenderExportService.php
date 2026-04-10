<?php

namespace App\Services;

use App\Models\Tender;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * TenderExportService
 * ───────────────────
 * Renders a Tender as either a PDF (via Browsershot/Chrome headless) or
 * a Word .docx (via PhpWord). Both formats produce a clean, RTL Arabic
 * document suitable for official distribution.
 *
 * Files are written to storage/app/public/tenders/ and the public URL
 * is returned. The HTML used for PDF is also reused as a preview.
 */
class TenderExportService
{
    /**
     * Generate a PDF and return the relative storage path.
     */
    public function exportPdf(Tender $tender): string
    {
        $html = $this->buildHtml($tender);
        $relPath = "tenders/{$tender->id}.pdf";
        $absPath = storage_path('app/public/' . $relPath);

        // Ensure directory exists
        @mkdir(dirname($absPath), 0755, true);

        try {
            Browsershot::html($html)
                ->format('A4')
                ->margins(20, 20, 20, 20)
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->save($absPath);
        } catch (Throwable $e) {
            // Fallback: write HTML and let user open it in browser
            file_put_contents(storage_path('app/public/tenders/' . $tender->id . '.html'), $html);
            throw new \RuntimeException('فشل توليد PDF: ' . $e->getMessage()
                . '. ملف HTML متاح للتنزيل والطباعة من المتصفح.');
        }

        return $relPath;
    }

    /**
     * Generate a Word .docx and return the relative storage path.
     */
    public function exportDocx(Tender $tender): string
    {
        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new Language('ar-SA'));
        $word->setDefaultParagraphStyle([
            'align' => 'right',
            'spaceAfter' => 200,
        ]);
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);

        $section = $word->addSection([
            'pageSizeW' => 11906,
            'pageSizeH' => 16838,
            'marginTop' => 1200,
            'marginBottom' => 1200,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Title page
        $section->addText($tender->title, [
            'name' => 'Arial', 'size' => 22, 'bold' => true, 'color' => '1a3a52',
        ], ['align' => 'center', 'spaceAfter' => 400]);

        $section->addText('كراسة الشروط والمواصفات', [
            'size' => 16, 'color' => '666666',
        ], ['align' => 'center', 'spaceAfter' => 200]);

        $section->addText($tender->organization?->name ?? 'الجهة الحكومية', [
            'size' => 14,
        ], ['align' => 'center', 'spaceAfter' => 1200]);

        $section->addText('التاريخ: ' . now()->locale('ar')->isoFormat('D MMMM YYYY'), [
            'size' => 12, 'color' => '888888',
        ], ['align' => 'center']);

        $section->addPageBreak();

        // Sections
        foreach ($tender->sections as $tenderSection) {
            $section->addText($tenderSection->title, [
                'name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => '1a3a52',
            ], ['align' => 'right', 'spaceAfter' => 200, 'spaceBefore' => 400]);

            $lines = explode("\n", $tenderSection->content ?? '');
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    $section->addTextBreak(1);
                    continue;
                }
                $section->addText($line, [
                    'size' => 11,
                ], ['align' => 'right', 'spaceAfter' => 100]);
            }
        }

        $relPath = "tenders/{$tender->id}.docx";
        $absPath = storage_path('app/public/' . $relPath);
        @mkdir(dirname($absPath), 0755, true);

        $writer = IOFactory::createWriter($word, 'Word2007');
        $writer->save($absPath);

        return $relPath;
    }

    /**
     * Build the HTML representation used for PDF rendering and preview.
     */
    public function buildHtml(Tender $tender): string
    {
        $title = e($tender->title);
        $org = e($tender->organization?->name ?? 'الجهة الحكومية');
        $date = now()->locale('ar')->isoFormat('D MMMM YYYY');
        $typeLabel = e(Tender::TYPES[$tender->type] ?? '');

        $sectionsHtml = '';
        foreach ($tender->sections as $section) {
            $sectionsHtml .= '<section class="page-section">'
                . '<h2>' . e($section->title) . '</h2>'
                . '<div class="content">' . nl2br(e($section->content ?? '')) . '</div>'
                . '</section>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        @page { size: A4; margin: 20mm; }
        body {
            font-family: 'Tajawal', 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.8;
            color: #1a1a1a;
            direction: rtl;
        }
        .cover {
            text-align: center;
            padding: 80mm 0;
            page-break-after: always;
        }
        .cover h1 {
            font-size: 28pt;
            color: #1a3a52;
            margin: 0 0 20mm;
        }
        .cover .subtitle {
            font-size: 16pt;
            color: #666;
            margin-bottom: 10mm;
        }
        .cover .org {
            font-size: 14pt;
            margin-bottom: 30mm;
        }
        .cover .meta {
            font-size: 11pt;
            color: #888;
        }
        .page-section {
            page-break-inside: avoid;
            margin-bottom: 12mm;
        }
        .page-section h2 {
            font-size: 16pt;
            color: #1a3a52;
            border-bottom: 2px solid #c8a94b;
            padding-bottom: 4mm;
            margin-bottom: 6mm;
        }
        .page-section .content {
            font-size: 11pt;
            color: #333;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="cover">
        <h1>{$title}</h1>
        <div class="subtitle">كراسة الشروط والمواصفات</div>
        <div class="org">{$org}</div>
        <div class="meta">{$typeLabel} · {$date}</div>
    </div>
    {$sectionsHtml}
</body>
</html>
HTML;
    }
}
