<?php

namespace App\Enums;

enum TaskStatus: int
{
    case New        = 1;
    case InProgress = 2;
    case Review     = 3;
    case Completed  = 4;
    case Cancelled  = 5;

    public function label(): string
    {
        return match ($this) {
            self::New        => 'جديدة',
            self::InProgress => 'قيد التنفيذ',
            self::Review     => 'مراجعة',
            self::Completed  => 'مكتملة',
            self::Cancelled  => 'ملغاة',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::New, self::InProgress, self::Review], true);
    }

    public function isClosed(): bool
    {
        return ! $this->isOpen();
    }

    /** @return array<int,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->toArray();
    }
}
