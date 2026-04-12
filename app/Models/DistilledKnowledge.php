<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DistilledKnowledge extends Model
{
    protected $table = 'distilled_knowledge';

    protected $fillable = [
        'title', 'court_type', 'source_label', 'batch', 'summary',
        'legal_topics', 'reasoning_patterns', 'cited_laws',
        'key_phrases', 'applicable_to', 'distill_model',
    ];

    protected $casts = [
        'legal_topics'       => 'array',
        'reasoning_patterns' => 'array',
        'cited_laws'         => 'array',
        'key_phrases'        => 'array',
        'applicable_to'      => 'array',
    ];
}
