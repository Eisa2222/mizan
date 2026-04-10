<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderClause extends Model
{
    protected $fillable = ['tender_id', 'clause_type', 'title', 'content', 'order'];

    public const CLAUSE_TYPES = [
        'sla'             => 'اتفاقية مستوى الخدمة',
        'penalties'       => 'الغرامات',
        'confidentiality' => 'السرية',
        'data_protection' => 'حماية البيانات',
        'warranty'        => 'الضمان',
        'payment'         => 'شروط الدفع',
        'ip'              => 'الملكية الفكرية',
        'support'         => 'الدعم الفني',
        'termination'     => 'إنهاء العقد',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }

    public function getTypeLabelAttribute(): string
    {
        return self::CLAUSE_TYPES[$this->clause_type] ?? $this->clause_type;
    }
}
