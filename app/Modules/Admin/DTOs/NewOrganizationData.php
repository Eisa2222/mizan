<?php

namespace Modules\Admin\DTOs;

use App\Enums\UserRole;

final class NewOrganizationData
{
    public function __construct(
        public readonly string $nameAr,
        public readonly ?string $nameEn,
        public readonly string $domain,
        public readonly ?string $phone,
        public readonly ?string $email,
        public readonly ?string $website,
        public readonly ?string $address,
        public readonly string $adminName,
        public readonly string $adminEmail,
        public readonly string $adminPassword,
        public readonly UserRole $adminRole,
    ) {
    }
}
