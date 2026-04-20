<?php

namespace Modules\Admin\DTOs;

use App\Enums\UserRole;

final class NewUserData
{
    public function __construct(
        public readonly string $name,
        public readonly string $email,
        public readonly string $password,
        public readonly int $orgId,
        public readonly UserRole $role,
    ) {
    }
}
