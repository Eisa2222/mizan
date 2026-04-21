<?php

namespace Modules\Memos\Queries;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MemosForOrgQuery
{
    public function paginate(User $user, int $perPage = 15): LengthAwarePaginator
    {
        return LegalDocument::query()
            ->where('org_id', $user->org_id)
            ->where('kind', LegalDocument::KIND_MEMO)
            ->latest()
            ->paginate($perPage);
    }
}
