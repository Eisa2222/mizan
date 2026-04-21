<?php

namespace Modules\ContractReviews\Queries;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ContractReviewsForOrgQuery
{
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return LegalDocument::query()
            ->where('org_id', $user->org_id)
            ->where('kind', LegalDocument::KIND_CONTRACT_REVIEW)
            ->latest()
            ->paginate($perPage);
    }
}
