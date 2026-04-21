<?php

namespace Modules\Admin\Actions;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Modules\Admin\DTOs\NewOrganizationData;

/**
 * Provisions a new organization along with its first admin user in a single
 * transaction — either both rows are written, or neither.
 */
class CreateOrganizationWithAdminAction
{
    public function execute(NewOrganizationData $data): Organization
    {
        return DB::transaction(function () use ($data) {
            $org = Organization::create([
                'name_ar' => $data->nameAr,
                'name_en' => $data->nameEn,
                'domain'  => $data->domain,
                'phone'   => $data->phone,
                'email'   => $data->email,
                'website' => $data->website,
                'address' => $data->address,
            ]);

            User::create([
                'name'     => $data->adminName,
                'email'    => $data->adminEmail,
                'password' => Hash::make($data->adminPassword),
                'org_id'   => $org->id,
                'role'     => $data->adminRole->value,
            ]);

            return $org;
        });
    }
}
