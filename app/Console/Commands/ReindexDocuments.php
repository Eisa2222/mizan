<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\ElasticsearchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('documents:reindex {--id= : Reindex a single document by ID} {--org= : Limit to one organization} {--fresh : Drop and recreate the ES index first}')]
#[Description('Chunk all legal documents and (re)index them into Elasticsearch')]
class ReindexDocuments extends Command
{
    public function handle(ElasticsearchService $es): int
    {
        $this->info('🔍 Mizaan reindex starting...');
        $this->line('   ES available: ' . ($es->isAvailable() ? '✓ yes' : '✗ no (will only chunk)'));

        if ($this->option('fresh') && $es->isAvailable()) {
            $es->deleteIndex();
            $this->line('   🗑  Deleted old index');
        }

        // Single document mode
        if ($id = $this->option('id')) {
            $doc = LegalDocument::findOrFail($id);
            $count = $es->reindexDocument($doc);
            $this->info("✓ Reindexed #{$doc->id} \"{$doc->title}\" → {$count} chunks");
            return self::SUCCESS;
        }

        // Bulk mode
        $stats = $es->reindexAll($this->option('org') ? (int) $this->option('org') : null);

        $this->newLine();
        $this->info("✓ Done. {$stats['documents']} documents | {$stats['chunks']} chunks | {$stats['indexed']} indexed");
        return self::SUCCESS;
    }
}
