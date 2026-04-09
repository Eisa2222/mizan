<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleUpdate extends Model
{
    protected $fillable = [
        'document_id', 'article_label', 'update_date', 'decree_number',
        'decree_url', 'body', 'source_document_id', 'auto_generated', 'created_by',
    ];

    protected $casts = [
        'update_date' => 'date',
        'auto_generated' => 'boolean',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'document_id');
    }

    public function sourceDocument(): BelongsTo
    {
        return $this->belongsTo(LegalDocument::class, 'source_document_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
