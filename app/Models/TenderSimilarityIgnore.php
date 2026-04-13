<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderSimilarityIgnore extends Model
{
    protected $fillable = ['tender_id', 'matched_tender_id', 'ignored_by', 'ignore_reason'];

    public function tender(): BelongsTo { return $this->belongsTo(Tender::class); }
    public function matchedTender(): BelongsTo { return $this->belongsTo(Tender::class, 'matched_tender_id'); }
    public function ignoredByUser(): BelongsTo { return $this->belongsTo(User::class, 'ignored_by'); }
}
