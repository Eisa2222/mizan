<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderReview extends Model
{
    protected $fillable = [
        'tender_id', 'compliance_score', 'issues', 'recommendations',
        'statistics', 'summary',
    ];

    protected $casts = [
        'issues' => 'array',
        'recommendations' => 'array',
        'statistics' => 'array',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
