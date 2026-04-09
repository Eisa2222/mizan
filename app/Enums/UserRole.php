<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'SuperAdmin';
    case OrgAdmin = 'OrgAdmin';
    case LegalCounsel = 'LegalCounsel';
    case Researcher = 'Researcher';
    case ReadOnly = 'ReadOnly';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'مدير عام',
            self::OrgAdmin => 'مدير المؤسسة',
            self::LegalCounsel => 'مستشار قانوني',
            self::Researcher => 'باحث',
            self::ReadOnly => 'قراءة فقط',
        };
    }

    /** Hierarchy: higher rank includes all lower ones */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin => 5,
            self::OrgAdmin => 4,
            self::LegalCounsel => 3,
            self::Researcher => 2,
            self::ReadOnly => 1,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn ($r) => [$r->value => $r->label()])->toArray();
    }
}
