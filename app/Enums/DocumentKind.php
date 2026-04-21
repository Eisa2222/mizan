<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Document        = 'document';
    case Contract        = 'contract';
    case CaseFile        = 'case';
    case ContractReview  = 'contract_review';
    case Memo            = 'memo';
    case TenderReview    = 'tender_review';

    public function label(): string
    {
        return match ($this) {
            self::Document       => 'مستند قانوني',
            self::Contract       => 'عقد',
            self::CaseFile       => 'قضية',
            self::ContractReview => 'مراجعة عقد',
            self::Memo           => 'مسودة مذكرة',
            self::TenderReview   => 'مراجعة كراسة',
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
