<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRelation extends Model
{
    protected $fillable = [
        'from_document_id', 'to_document_id', 'relation_type', 'note', 'created_by',
    ];

    public function fromDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'from_document_id');
    }

    public function toDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'to_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getRelationLabelAttribute(): string
    {
        return LegalDocument::RELATION_TYPES[$this->relation_type] ?? $this->relation_type;
    }
}
