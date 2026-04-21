<?php

namespace Modules\ContractReviews\Queries;

use App\Models\Annotation;
use App\Models\Discussion;
use App\Models\LegalDocument;
use App\Models\User;
use App\Services\ClaudeService;
use Illuminate\Support\Collection;

/**
 * Aggregates everything the contract-review show page needs so the Blade
 * view stays free of model and service calls.
 */
class ContractReviewShowContextQuery
{
    public function __construct(private readonly ClaudeService $claude)
    {
    }

    /**
     * @return array{annotations:Collection, discussions:Collection, ai_configured:bool}
     */
    public function run(LegalDocument $document, User $user): array
    {
        return [
            'annotations' => Annotation::query()
                ->with('user')
                ->where('document_id', $document->id)
                ->visibleTo($user)
                ->latest()
                ->get(),

            'discussions' => Discussion::query()
                ->with(['user', 'replies'])
                ->where('document_id', $document->id)
                ->visibleTo($user)
                ->latest()
                ->get(),

            'ai_configured' => $this->claude->isConfigured(),
        ];
    }
}
