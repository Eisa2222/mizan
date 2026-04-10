<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderSection extends Model
{
    protected $fillable = [
        'tender_id', 'section_key', 'title', 'content', 'order', 'is_edited',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
    ];

    public function tender(): BelongsTo
    {
        return $this->belongsTo(Tender::class);
    }
}
