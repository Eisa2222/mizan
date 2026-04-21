<?php

namespace Modules\Admin\Queries;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Collection;

class OrganizationsWithUserCountsQuery
{
    public function run(): Collection
    {
        return Organization::query()
            ->withCount('users')
            ->orderBy('name_ar')
            ->get();
    }
}
