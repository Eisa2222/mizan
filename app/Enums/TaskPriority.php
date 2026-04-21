<?php

namespace App\Enums;

enum TaskPriority: int
{
    case Low      = 1;
    case Medium   = 2;
    case High     = 3;
    case Urgent   = 4;

    public function label(): string
    {
        return match ($this) {
            self::Low    => 'منخفضة',
            self::Medium => 'متوسطة',
            self::High   => 'عالية',
            self::Urgent => 'عاجلة',
        };
    }

    /** @return array<int,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->toArray();
    }
}
