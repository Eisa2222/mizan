<?php

namespace App\Jobs;

use App\Models\Tender;
use App\Services\TenderSimilarityService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Runs similarity analysis in the background after a tender is created/updated.
 */
class AnalyzeTenderSimilarityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 120;

    public function __construct(public Tender $tender) {}

    public function handle(TenderSimilarityService $similarity): void
    {
        try {
            $similarity->analyze($this->tender);
        } catch (\Throwable $e) {
            Log::warning('TenderSimilarity analysis failed', [
                'tender_id' => $this->tender->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}
