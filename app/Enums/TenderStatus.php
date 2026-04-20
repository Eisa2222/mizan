<?php

namespace App\Enums;

enum TenderStatus: string
{
    case Draft      = 'draft';
    case Generating = 'generating';
    case Ready      = 'ready';
    case Reviewing  = 'reviewing';
    case Finalized  = 'finalized';

    public function label(): string
    {
        return match ($this) {
            self::Draft      => 'مسودة',
            self::Generating => 'جاري التوليد',
            self::Ready      => 'جاهز',
            self::Reviewing  => 'قيد المراجعة',
            self::Finalized  => 'معتمد',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $c) => [$c->value => $c->label()])
            ->toArray();
    }
}
