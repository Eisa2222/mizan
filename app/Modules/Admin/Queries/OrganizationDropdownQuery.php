<?php

namespace Modules\Admin\Queries;

use App\Models\Organization;
use Illuminate\Support\Collection;

/**
 * Returns organizations keyed by id mapped to their Arabic name — the shape
 * expected by the user-management dropdowns.
 */
class OrganizationDropdownQuery
{
    public function run(): Collection
    {
        return Organization::query()
            ->orderBy('name_ar')
            ->pluck('name_ar', 'id');
    }
}
