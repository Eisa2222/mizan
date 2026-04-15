<?php

namespace App\Services;

use App\Models\Tender;
use Mpdf\Mpdf;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\JcTable;
use PhpOffice\PhpWord\Style\Language;
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
        $relPath = "tenders/{$tender->id}.pdf";
        $absPath = storage_path('app/public/' . $relPath);
        @mkdir(dirname($absPath), 0755, true);

        // Ensure mPDF temp directories exist
        $tempDir = storage_path('app/mpdf');
        @mkdir($tempDir, 0755, true);
        @mkdir($tempDir . '/ttfontdata', 0755, true);

        try {
            $mpdf = new Mpdf([
                'mode'              => 'utf-8',
                'format'            => 'A4',
                'orientation'       => 'P',
                'default_font'      => 'dejavusans', // built-in font with Arabic glyphs
                'default_font_size' => 12,
                'tempDir'           => $tempDir,
                'margin_left'       => 18,
                'margin_right'      => 18,
                'margin_top'        => 22,
                'margin_bottom'     => 22,
                'margin_header'     => 8,
                'margin_footer'     => 8,
                'autoScriptToLang'  => true,
                'autoLangToFont'    => true,
                'useSubstitutions'  => true,
            ]);

            // RTL direction for Arabic
            $mpdf->SetDirectionality('rtl');
            $mpdf->SetTitle($tender->title);
            $mpdf->SetAuthor($tender->organization?->name_ar ?? 'ميزان');

            // Header/Footer
            $org = $tender->organization;
            $headerText = $org?->header_text ?? '';
            $footerText = $org?->footer_text ?? $this->buildFooterText($org);

            if ($headerText) {
                $mpdf->SetHTMLHeader('<div style="text-align:center;font-size:9pt;color:#888">' . e($headerText) . '</div>');
            }
            $mpdf->SetHTMLFooter('<div style="text-align:center;font-size:8pt;color:#999;border-top:1px solid #ddd;padding-top:4px">'
                . ($footerText ? e($footerText) . ' · ' : '')
                . 'صفحة {PAGENO} من {nbpg}'
                . '</div>');

            $html = $this->buildHtml($tender);
            $mpdf->WriteHTML($html);
            $mpdf->Output($absPath, \Mpdf\Output\Destination::FILE);
        } catch (Throwable $e) {
            // Fallback: save HTML for manual print
            file_put_contents(storage_path('app/public/tenders/' . $tender->id . '.html'), $this->buildHtml($tender));
            throw new \RuntimeException('فشل توليد PDF: ' . $e->getMessage()
                . '. ملف HTML متاح كخيار بديل.');
        }

        return $relPath;
    }

    public function exportDocx(Tender $tender): string
    {
        $org = $tender->organization;
        $primaryColor = ltrim($org?->primary_color ?? '#1a3a52', '#');
        $accentColor = ltrim($org?->accent_color ?? '#c8a94b', '#');

        $word = new PhpWord();
        // Force RTL language + Arabic complex-script support at document level
        $lang = new Language('ar-SA', 'ar-SA', 'ar-SA');
        $word->getSettings()->setThemeFontLang($lang);
        $word->setDefaultParagraphStyle([
            'align' => 'right',
            'spaceAfter' => 200,
            'bidi' => true, // RTL direction for all paragraphs by default
        ]);
        // Use Amiri/Traditional Arabic — better Arabic shaping than Arial
        $word->setDefaultFontName('Traditional Arabic');
        $word->setDefaultFontSize(12);

        // Header/Footer style
        $headerText = $org?->header_text ?? '';
        $footerText = $org?->footer_text ?? $this->buildFooterText($org);

        $section = $word->addSection([
            'pageSizeW' => 11906, 'pageSizeH' => 16838,
            'marginTop' => 1400, 'marginBottom' => 1400,
            'marginLeft' => 1200, 'marginRight' => 1200,
        ]);

        // Arabic-safe font settings for every text run
        $arFont = ['name' => 'Traditional Arabic', 'rtl' => true, 'lang' => 'ar-SA'];

        // Word header
        $header = $section->addHeader();
        if ($org?->logo_path && file_exists(storage_path('app/public/' . $org->logo_path))) {
            $header->addImage(storage_path('app/public/' . $org->logo_path), [
                'width' => 60, 'height' => 40, 'alignment' => 'right',
            ]);
        }
        if ($headerText) {
            $header->addText($headerText, [...$arFont, 'size' => 9, 'color' => '888888'], ['align' => 'center', 'bidi' => true]);
        }

        // Word footer
        $footer = $section->addFooter();
        if ($footerText) {
            $footer->addText($footerText, [...$arFont, 'size' => 9, 'color' => '888888'], ['align' => 'center', 'bidi' => true]);
        }
        $footer->addPreserveText('صفحة {PAGE} من {NUMPAGES}', [...$arFont, 'size' => 9, 'color' => 'AAAAAA'], ['align' => 'center', 'bidi' => true]);

        // Title page — logo
        if ($org?->logo_path && file_exists(storage_path('app/public/' . $org->logo_path))) {
            $section->addImage(storage_path('app/public/' . $org->logo_path), [
                'width' => 120, 'height' => 80, 'alignment' => 'center',
            ]);
            $section->addTextBreak(1);
        }

        $section->addText($tender->title, [
            ...$arFont, 'size' => 24, 'bold' => true, 'color' => $primaryColor,
        ], ['align' => 'center', 'spaceAfter' => 400, 'bidi' => true]);

        $section->addText('كراسة الشروط والمواصفات', [
            ...$arFont, 'size' => 18, 'color' => '666666',
        ], ['align' => 'center', 'spaceAfter' => 200, 'bidi' => true]);

        $section->addText($org?->name_ar ?? 'الجهة الحكومية', [
            ...$arFont, 'size' => 16, 'bold' => true,
        ], ['align' => 'center', 'spaceAfter' => 400, 'bidi' => true]);

        if ($org?->name_en) {
            $section->addText($org->name_en, [
                'name' => 'Calibri', 'size' => 13, 'color' => '888888',
            ], ['align' => 'center', 'spaceAfter' => 600]);
        }

        $section->addText('التاريخ: ' . now()->locale('ar')->isoFormat('D MMMM YYYY'), [
            ...$arFont, 'size' => 13, 'color' => '888888',
        ], ['align' => 'center', 'bidi' => true]);

        if ($org?->address || $org?->phone) {
            $section->addTextBreak(2);
            $contactLine = implode(' · ', array_filter([$org?->address, $org?->phone, $org?->email]));
            $section->addText($contactLine, [...$arFont, 'size' => 11, 'color' => 'AAAAAA'], ['align' => 'center', 'bidi' => true]);
        }

        $section->addPageBreak();

        // Sections
        foreach ($tender->sections as $tenderSection) {
            $section->addText($tenderSection->title, [
                ...$arFont, 'size' => 18, 'bold' => true, 'color' => $primaryColor,
            ], ['align' => 'right', 'spaceAfter' => 200, 'spaceBefore' => 400, 'bidi' => true,
                'borderBottomSize' => 6, 'borderBottomColor' => $accentColor]);

            foreach (explode("\n", $tenderSection->content ?? '') as $line) {
                $line = trim($line);
                if ($line === '') { $section->addTextBreak(1); continue; }
                $section->addText($line, [...$arFont, 'size' => 12], ['align' => 'right', 'spaceAfter' => 100, 'bidi' => true]);
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

        // mPDF-optimized HTML — uses built-in DejaVu Sans (has Arabic glyphs)
        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <style>
        body {
            font-family: dejavusans, sans-serif;
            font-size: 11pt;
            line-height: 1.9;
            color: #1a1a1a;
        }
        .cover {
            text-align: center;
            padding: 40mm 0 20mm;
            page-break-after: always;
        }
        .cover .logo-cover { margin-bottom: 12mm; }
        .cover h1 {
            font-size: 26pt;
            color: {$primaryColor};
            margin: 0 0 10mm;
            font-weight: bold;
        }
        .cover .subtitle {
            font-size: 15pt;
            color: #666;
            margin-bottom: 6mm;
        }
        .cover .org {
            font-size: 14pt;
            font-weight: bold;
            margin-bottom: 3mm;
        }
        .cover .org-en {
            font-size: 11pt;
            color: #888;
            margin-bottom: 15mm;
        }
        .cover .meta {
            font-size: 10pt;
            color: #888;
            margin-bottom: 4mm;
        }
        .cover .contact {
            font-size: 9pt;
            color: #aaa;
            margin-top: 10mm;
        }
        .page-section {
            page-break-inside: avoid;
            margin-bottom: 8mm;
        }
        .page-section h2 {
            font-size: 15pt;
            color: {$primaryColor};
            border-bottom: 2px solid {$accentColor};
            padding-bottom: 3mm;
            margin: 6mm 0 4mm;
            font-weight: bold;
        }
        .page-section .content {
            font-size: 11pt;
            color: #333;
            text-align: justify;
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
