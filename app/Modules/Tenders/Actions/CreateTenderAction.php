<?php

namespace Modules\Tenders\Actions;

use App\Models\Tender;
use App\Models\User;
use App\Services\TenderBuilderService;
use App\Services\TenderSimilarityService;
use Throwable;

/**
 * Persists a tender, runs the builder to generate sections/clauses, then
 * kicks off a similarity analysis (best-effort — similarity failures must
 * not block tender creation).
 *
 * Also normalizes the "other" type: when custom_type is provided, it's
 * prepended to description before the extra field is dropped.
 */
class CreateTenderAction
{
    public function __construct(
        private readonly TenderBuilderService $builder,
        private readonly TenderSimilarityService $similarity,
    ) {
    }

    /**
     * @param  array<string,mixed>  $data  validated payload from StoreTenderRequest
     */
    public function execute(User $user, array $data): Tender
    {
        if (($data['type'] ?? null) === 'other' && ! empty($data['custom_type'])) {
            $data['description'] = '[نوع: ' . $data['custom_type'] . '] ' . ($data['description'] ?? '');
        }
        unset($data['custom_type']);

        $tender = Tender::create([
            ...$data,
            'org_id'     => $user->org_id,
            'created_by' => $user->id,
            'status'     => 'draft',
        ]);

        $tender = $this->builder->build($tender);

        try {
            $this->similarity->analyze($tender);
        } catch (Throwable) {
            // Similarity is best-effort.
        }

        return $tender;
    }
}
