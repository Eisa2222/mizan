<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentChunk extends Model
{
    protected $fillable = [
        'document_id', 'chunk_index', 'content', 'normalized', 'label',
        'char_start', 'char_end', 'token_count', 'embedding', 'indexed_at',
    ];

    protected $casts = [
        'embedding' => 'array',
        'indexed_at' => 'datetime',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }
}
