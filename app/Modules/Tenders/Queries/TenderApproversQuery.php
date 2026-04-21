<?php

namespace Modules\Tenders\Queries;

use App\Models\Tender;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * IDs of users eligible to approve a tender for this organization, excluding
 * the submitter themselves. Used to fan out submit notifications.
 */
class TenderApproversQuery
{
    public function run(Tender $tender, User $submitter): Collection
    {
        return User::query()
            ->where('org_id', $tender->org_id)
            ->whereIn('role', ['SuperAdmin', 'OrgAdmin', 'LegalCounsel'])
            ->where('id', '!=', $submitter->id)
            ->pluck('id');
    }
}
