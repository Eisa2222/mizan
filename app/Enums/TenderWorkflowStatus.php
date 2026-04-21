<?php

namespace App\Enums;

enum TenderWorkflowStatus: string
{
    case Draft     = 'draft';
    case Submitted = 'submitted';
    case Approved  = 'approved';
    case Rejected  = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft     => 'مسودة',
            self::Submitted => 'مرسل للاعتماد',
            self::Approved  => 'معتمد',
            self::Rejected  => 'مرفوض',
        };
    }

    public function canSubmit(): bool
    {
        return in_array($this, [self::Draft, self::Rejected], true);
    }

    public function isPendingApproval(): bool
    {
        return $this === self::Submitted;
    }
}
