<?php

namespace App\Enums;

enum UserRole: string
{
    case SuperAdmin = 'SuperAdmin';
    case OrgAdmin = 'OrgAdmin';
    case LegalCounsel = 'LegalCounsel';
    case Researcher = 'Researcher';
    case OrgUser = 'OrgUser';
    case ReadOnly = 'ReadOnly';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin   => 'مدير عام النظام',
            self::OrgAdmin     => 'مدير المؤسسة',
            self::LegalCounsel => 'مستشار قانوني',
            self::Researcher   => 'باحث قانوني',
            self::OrgUser      => 'مستخدم جهة',
            self::ReadOnly     => 'قراءة فقط',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin   => 'صلاحيات كاملة على النظام وجميع الجهات',
            self::OrgAdmin     => 'إدارة كاملة للمؤسسة والمستخدمين',
            self::LegalCounsel => 'مراجعة وتعديل جميع المستندات والكراسات',
            self::Researcher   => 'رفع مستندات قانونية + جميع الخدمات',
            self::OrgUser      => 'جميع الخدمات (كراسات، عقود، مذكرات، قضايا) بدون رفع مستندات قانونية',
            self::ReadOnly     => 'عرض المستندات فقط',
        };
    }

    /** Hierarchy: higher rank includes all lower ones */
    public function rank(): int
    {
        return match ($this) {
            self::SuperAdmin   => 6,
            self::OrgAdmin     => 5,
            self::LegalCounsel => 4,
            self::Researcher   => 3,
            self::OrgUser      => 2,
            self::ReadOnly     => 1,
        };
    }

    /** Whether this role can upload legal documents (المستندات القانونية). */
    public function canUploadDocuments(): bool
    {
        return $this->rank() >= self::Researcher->rank();
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
