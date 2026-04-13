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
 * Renders a Tender as PDF or Word .docx with full organization branding:
 * logo, header/footer, colors, contact info.
 */
class TenderExportService
{
    public function exportPdf(Tender $tender): string
    {
        $html = $this->buildHtml($tender);
        $relPath = "tenders/{$tender->id}.pdf";
        $absPath = storage_path('app/public/' . $relPath);
        @mkdir(dirname($absPath), 0755, true);

        try {
            Browsershot::html($html)
                ->format('A4')
                ->margins(20, 20, 25, 20)
                ->showBackground()
                ->waitUntilNetworkIdle()
                ->save($absPath);
        } catch (Throwable $e) {
            file_put_contents(storage_path('app/public/tenders/' . $tender->id . '.html'), $html);
            throw new \RuntimeException('فشل توليد PDF: ' . $e->getMessage()
                . '. ملف HTML متاح للتنزيل والطباعة من المتصفح.');
        }

        return $relPath;
    }

    public function exportDocx(Tender $tender): string
    {
        $org = $tender->organization;
        $primaryColor = ltrim($org?->primary_color ?? '#1a3a52', '#');
        $accentColor = ltrim($org?->accent_color ?? '#c8a94b', '#');

        $word = new PhpWord();
        $word->getSettings()->setThemeFontLang(new Language('ar-SA'));
        $word->setDefaultParagraphStyle(['align' => 'right', 'spaceAfter' => 200]);
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(11);

        // Header/Footer style
        $headerText = $org?->header_text ?? '';
        $footerText = $org?->footer_text ?? $this->buildFooterText($org);

        $section = $word->addSection([
            'pageSizeW' => 11906, 'pageSizeH' => 16838,
            'marginTop' => 1400, 'marginBottom' => 1400,
            'marginLeft' => 1200, 'marginRight' => 1200,
        ]);

        // Word header
        $header = $section->addHeader();
        if ($org?->logo_path && file_exists(storage_path('app/public/' . $org->logo_path))) {
            $header->addImage(storage_path('app/public/' . $org->logo_path), [
                'width' => 60, 'height' => 40, 'alignment' => 'right',
            ]);
        }
        if ($headerText) {
            $header->addText($headerText, ['size' => 8, 'color' => '888888'], ['align' => 'center']);
        }

        // Word footer
        $footer = $section->addFooter();
        if ($footerText) {
            $footer->addText($footerText, ['size' => 8, 'color' => '888888'], ['align' => 'center']);
        }
        $footer->addPreserveText('صفحة {PAGE} من {NUMPAGES}', ['size' => 8, 'color' => 'AAAAAA'], ['align' => 'center']);

        // Title page — logo
        if ($org?->logo_path && file_exists(storage_path('app/public/' . $org->logo_path))) {
            $section->addImage(storage_path('app/public/' . $org->logo_path), [
                'width' => 120, 'height' => 80, 'alignment' => 'center',
            ]);
            $section->addTextBreak(1);
        }

        $section->addText($tender->title, [
            'name' => 'Arial', 'size' => 22, 'bold' => true, 'color' => $primaryColor,
        ], ['align' => 'center', 'spaceAfter' => 400]);

        $section->addText('كراسة الشروط والمواصفات', [
            'size' => 16, 'color' => '666666',
        ], ['align' => 'center', 'spaceAfter' => 200]);

        $section->addText($org?->name_ar ?? 'الجهة الحكومية', [
            'size' => 14, 'bold' => true,
        ], ['align' => 'center', 'spaceAfter' => 400]);

        if ($org?->name_en) {
            $section->addText($org->name_en, [
                'size' => 12, 'color' => '888888',
            ], ['align' => 'center', 'spaceAfter' => 600]);
        }

        $section->addText('التاريخ: ' . now()->locale('ar')->isoFormat('D MMMM YYYY'), [
            'size' => 12, 'color' => '888888',
        ], ['align' => 'center']);

        if ($org?->address || $org?->phone) {
            $section->addTextBreak(2);
            $contactLine = implode(' · ', array_filter([$org?->address, $org?->phone, $org?->email]));
            $section->addText($contactLine, ['size' => 10, 'color' => 'AAAAAA'], ['align' => 'center']);
        }

        $section->addPageBreak();

        // Sections
        foreach ($tender->sections as $tenderSection) {
            $section->addText($tenderSection->title, [
                'name' => 'Arial', 'size' => 16, 'bold' => true, 'color' => $primaryColor,
            ], ['align' => 'right', 'spaceAfter' => 200, 'spaceBefore' => 400,
                'borderBottomSize' => 6, 'borderBottomColor' => $accentColor]);

            foreach (explode("\n", $tenderSection->content ?? '') as $line) {
                $line = trim($line);
                if ($line === '') { $section->addTextBreak(1); continue; }
                $section->addText($line, ['size' => 11], ['align' => 'right', 'spaceAfter' => 100]);
            }
        }

        $relPath = "tenders/{$tender->id}.docx";
        $absPath = storage_path('app/public/' . $relPath);
        @mkdir(dirname($absPath), 0755, true);
        IOFactory::createWriter($word, 'Word2007')->save($absPath);

        return $relPath;
    }

    public function buildHtml(Tender $tender): string
    {
        $org = $tender->organization;
        $title = e($tender->title);
        $orgName = e($org?->name_ar ?? 'الجهة الحكومية');
        $orgNameEn = e($org?->name_en ?? '');
        $date = now()->locale('ar')->isoFormat('D MMMM YYYY');
        $typeLabel = e(Tender::TYPES[$tender->type] ?? '');
        $primaryColor = e($org?->primary_color ?? '#1a3a52');
        $accentColor = e($org?->accent_color ?? '#c8a94b');
        $headerText = e($org?->header_text ?? '');
        $footerText = e($org?->footer_text ?? $this->buildFooterText($org));
        $logoUrl = $org?->logo_url ?? '';
        $contactLine = e(implode(' · ', array_filter([$org?->address, $org?->phone, $org?->email, $org?->website])));

        // Build logo HTML
        $logoHtml = $logoUrl
            ? "<img src=\"{$logoUrl}\" style=\"max-height:60px;max-width:160px\" alt=\"\">"
            : '';

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
        @page {
            size: A4;
            margin: 25mm 20mm 30mm 20mm;
            @top-center {
                content: "{$headerText}";
                font-size: 8pt;
                color: #888;
            }
            @bottom-center {
                content: "{$footerText}";
                font-size: 8pt;
                color: #888;
            }
        }
        body {
            font-family: 'Tajawal', 'Arial', sans-serif;
            font-size: 12pt;
            line-height: 1.8;
            color: #1a1a1a;
            direction: rtl;
        }
        .header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 3px solid {$accentColor};
            padding-bottom: 10px;
            margin-bottom: 8mm;
        }
        .header-bar .logo-side {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .header-bar .header-label {
            font-size: 9pt;
            color: #888;
        }
        .footer-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #999;
            border-top: 1px solid #ddd;
            padding: 6px 20mm;
        }
        .cover {
            text-align: center;
            padding: 50mm 0 30mm;
            page-break-after: always;
        }
        .cover .logo-cover {
            margin-bottom: 15mm;
        }
        .cover h1 {
            font-size: 28pt;
            color: {$primaryColor};
            margin: 0 0 12mm;
        }
        .cover .subtitle {
            font-size: 16pt;
            color: #666;
            margin-bottom: 8mm;
        }
        .cover .org {
            font-size: 15pt;
            font-weight: 700;
            margin-bottom: 4mm;
        }
        .cover .org-en {
            font-size: 12pt;
            color: #888;
            margin-bottom: 20mm;
        }
        .cover .meta {
            font-size: 11pt;
            color: #888;
            margin-bottom: 6mm;
        }
        .cover .contact {
            font-size: 9pt;
            color: #aaa;
            margin-top: 15mm;
        }
        .page-section {
            page-break-inside: avoid;
            margin-bottom: 12mm;
        }
        .page-section h2 {
            font-size: 16pt;
            color: {$primaryColor};
            border-bottom: 2px solid {$accentColor};
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
        <div class="logo-cover">{$logoHtml}</div>
        <h1>{$title}</h1>
        <div class="subtitle">كراسة الشروط والمواصفات</div>
        <div class="org">{$orgName}</div>
        <div class="org-en">{$orgNameEn}</div>
        <div class="meta">{$typeLabel} · {$date}</div>
        <div class="contact">{$contactLine}</div>
    </div>
    {$sectionsHtml}
    <div class="footer-bar">{$footerText}</div>
</body>
</html>
HTML;
    }

    private function buildFooterText($org): string
    {
        if (! $org) return '';
        $parts = array_filter([$org->name_ar, $org->phone, $org->email]);
        return implode(' · ', $parts);
    }
}
