<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Models\Organization;
use App\Services\ElasticsearchService;
use Illuminate\Console\Command;

/**
 * Imports a folder of `.txt` batch files produced by the Saudi Board of
 * Grievances / Commercial Courts export tool. Each batch file contains many
 * rulings separated by the marker:
 *
 *     ================================================================================
 *     الحكم رقم [N]
 *     ================================================================================
 *
 * The command splits on that separator, extracts case metadata from the
 * ruling header, and persists each ruling as a standalone LegalDocument
 * with `kind=case` so the AI training corpus and search index treat them
 * individually instead of as a giant blob.
 */
class ImportJudicialRulingsCommand extends Command
{
    protected $signature = 'mizaan:import-rulings
                            {path : Folder containing the .txt batch files}
                            {--org= : Target organization id (defaults to المكتبة المرجعية)}
                            {--dry-run : Parse and print what would import, persist nothing}
                            {--skip-existing : Skip rulings whose reference_number is already stored}';

    protected $description = 'Import a folder of judicial ruling batch .txt files as individual case documents';

    private const TYPE_JUDICIAL_RULING = 6;
    private const SEPARATOR = "================================================================================";
    private const HEADER_PATTERN = '/={80}\s+الحكم رقم \[(\d+)\]\s+={80}/u';

    public function handle(ElasticsearchService $elasticsearch): int
    {
        $path = $this->argument('path');
        if (! is_dir($path)) {
            $this->error("Folder not found: {$path}");
            return self::FAILURE;
        }

        $org = $this->resolveOrganization();
        $this->info("Target organization: {$org->name_ar} (id={$org->id})");

        $files = glob(rtrim($path, '/\\') . '/*.txt') ?: [];
        if (empty($files)) {
            $this->warn('No .txt files found.');
            return self::SUCCESS;
        }

        $dryRun       = (bool) $this->option('dry-run');
        $skipExisting = (bool) $this->option('skip-existing');

        $stats = ['imported' => 0, 'skipped_existing' => 0, 'skipped_empty' => 0, 'errors' => 0];
        $allRulings = [];

        foreach ($files as $file) {
            $this->info('Parsing: ' . basename($file));
            $rulings = $this->parseBatchFile($file);
            $this->line('  → found ' . count($rulings) . ' rulings');
            $allRulings[$file] = $rulings;
        }

        $totalRulings = array_sum(array_map('count', $allRulings));
        $bar = $this->output->createProgressBar($totalRulings);
        $bar->start();

        foreach ($allRulings as $file => $rulings) {
            foreach ($rulings as $ruling) {
                // Ruling numbers repeat across batches (appellate rulings are
                // grouped under one parent ruling_number), so they're not
                // unique. Identify rulings by (batch_file, ruling_index) which
                // IS unique within the export.
                $ref = ($ruling['case_number'] ?? null) ?: $ruling['ruling_number'];
                $unique = basename($file) . '#' . $ruling['index'];

                if ($skipExisting) {
                    $exists = LegalDocument::query()
                        ->where('org_id', $org->id)
                        ->where('kind', LegalDocument::KIND_CASE)
                        ->where('metadata->source', 'judicial_rulings_import')
                        ->where('metadata->batch_file', basename($file))
                        ->where('metadata->ruling_index', $ruling['index'])
                        ->exists();
                    if ($exists) {
                        $stats['skipped_existing']++;
                        $bar->advance();
                        continue;
                    }
                }

                if (mb_strlen(trim($ruling['content'])) < 100) {
                    $stats['skipped_empty']++;
                    $bar->advance();
                    continue;
                }

                if ($dryRun) {
                    $stats['imported']++;
                    $bar->advance();
                    continue;
                }

                try {
                    $doc = LegalDocument::create([
                        'org_id'           => $org->id,
                        'title'            => $this->buildTitle($ruling),
                        'type'             => self::TYPE_JUDICIAL_RULING,
                        'kind'             => LegalDocument::KIND_CASE,
                        'content'          => $ruling['content'],
                        'reference_number' => $ref,
                        'source_entity'    => trim(($ruling['court'] ?? '') . ' - ' . ($ruling['city'] ?? ''), ' -'),
                        'file_name'        => basename($file),
                        'is_private'       => false,
                        'metadata'         => [
                            'source'         => 'judicial_rulings_import',
                            'batch_file'     => basename($file),
                            'ruling_index'   => $ruling['index'],
                            'case_number'    => $ruling['case_number'],
                            'ruling_number'  => $ruling['ruling_number'],
                            'court'          => $ruling['court'],
                            'city'           => $ruling['city'],
                            'hijri_date'     => $ruling['hijri_date'],
                            'tags'           => ['أحكام قضائية سابقة', 'محاكم تجارية'],
                            'extraction_status' => 'imported',
                            'extracted_at'   => now()->toIso8601String(),
                        ],
                    ]);

                    $elasticsearch->reindexDocument($doc);
                    $stats['imported']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->warn(PHP_EOL . 'Failed ruling ' . $ref . ': ' . $e->getMessage());
                }

                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(['Metric', 'Count'], [
            ['Imported',         $stats['imported']],
            ['Skipped existing', $stats['skipped_existing']],
            ['Skipped empty',    $stats['skipped_empty']],
            ['Errors',           $stats['errors']],
        ]);

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{index:int, case_number:?string, ruling_number:?string, court:?string, city:?string, hijri_date:?string, content:string}>
     */
    private function parseBatchFile(string $file): array
    {
        $text = @file_get_contents($file);
        if ($text === false) return [];

        // Strip UTF-8 BOM + normalise line endings
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text);
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        if (! preg_match_all(self::HEADER_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $rulings = [];
        $count = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $headerStart = $matches[0][$i][1];
            $contentStart = $headerStart + strlen($matches[0][$i][0]);
            $contentEnd = ($i + 1 < $count) ? $matches[0][$i + 1][1] : strlen($text);

            $rulingIndex = (int) $matches[1][$i][0];
            $rulingText  = trim(substr($text, $contentStart, $contentEnd - $contentStart));

            $rulings[] = [
                'index'          => $rulingIndex,
                'content'        => $rulingText,
                'case_number'    => $this->extractField($rulingText, '/القضية رقم\s*[:：]?\s*([0-9٠-٩]+)\s*لعام\s*([0-9٠-٩]+)\s*ه?/u', fn ($m) => $m[1] . ' لعام ' . $m[2] . 'هـ'),
                'ruling_number'  => $this->extractField($rulingText, '/رقم الحكم\s*[:：]?\s*([0-9٠-٩]+)/u'),
                'court'          => $this->extractField($rulingText, '/^(المحكمة [^\n]+)$/um'),
                'city'           => $this->extractField($rulingText, '/المدينة\s*[:：]?\s*([^\n]+)/u'),
                'hijri_date'     => $this->extractField($rulingText, '/التاريخ\s*[:：]?\s*([^\n]+)/u'),
            ];
        }

        return $rulings;
    }

    /** Extract with optional transformer; returns null if no match. */
    private function extractField(string $text, string $pattern, ?callable $transform = null): ?string
    {
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $value = $transform !== null ? $transform($m) : ($m[1] ?? null);
        return $value !== null ? trim($value) : null;
    }

    private function buildTitle(array $ruling): string
    {
        $parts = [];

        if ($ruling['court']) $parts[] = $ruling['court'];
        if ($ruling['city'])  $parts[] = $ruling['city'];
        if ($ruling['case_number']) $parts[] = 'قضية ' . $ruling['case_number'];
        else $parts[] = 'حكم رقم ' . ($ruling['ruling_number'] ?? $ruling['index']);

        return mb_substr(implode(' - ', $parts), 0, 250);
    }

    private function resolveOrganization(): Organization
    {
        if ($orgId = $this->option('org')) {
            $org = Organization::find($orgId);
            if (! $org) {
                $this->error("Organization {$orgId} not found");
                exit(self::FAILURE);
            }
            return $org;
        }

        return Organization::firstOrCreate(
            ['name_ar' => 'المكتبة المرجعية'],
            ['name_en' => 'Reference Library']
        );
    }
}
