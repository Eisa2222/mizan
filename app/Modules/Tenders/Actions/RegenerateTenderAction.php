<?php

namespace Modules\Tenders\Actions;

use App\Models\Tender;
use App\Services\TenderBuilderService;
use App\Services\TenderSimilarityService;
use Throwable;

class RegenerateTenderAction
{
    public function __construct(
        private readonly TenderBuilderService $builder,
        private readonly TenderSimilarityService $similarity,
    ) {
    }

    public function execute(Tender $tender): Tender
    {
        $this->builder->build($tender);

        try {
            $this->similarity->analyze($tender);
        } catch (Throwable) {
        }

        return $tender;
    }
}
