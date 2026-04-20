<?php

namespace Modules\Tenders\Actions;

use App\Models\Tender;
use App\Models\TenderSimilarityIgnore;
use App\Models\User;

class IgnoreSimilarityAction
{
    public function execute(Tender $tender, User $user, int $matchedTenderId, string $reason): void
    {
        TenderSimilarityIgnore::updateOrCreate(
            ['tender_id' => $tender->id, 'matched_tender_id' => $matchedTenderId],
            ['ignored_by' => $user->id, 'ignore_reason' => $reason],
        );
    }
}
