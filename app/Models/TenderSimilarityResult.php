<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenderSimilarityResult extends Model
{
    protected $fillable = [
        'source_tender_id', 'compared_tender_id', 'org_id',
        'text_similarity_score', 'semantic_similarity_score', 'structural_similarity_score',
        'final_similarity_score', 'similarity_level', 'duplicate_risk',
        'reusable_sections', 'reusable_clauses', 'reusable_criteria',
        'lessons_learned', 'recommendation',
    ];

    protected $casts = [
        'text_similarity_score'       => 'float',
        'semantic_similarity_score'   => 'float',
        'structural_similarity_score' => 'float',
        'final_similarity_score'      => 'float',
        'duplicate_risk'              => 'boolean',
        'reusable_sections'           => 'array',
        'reusable_clauses'            => 'array',
        'reusable_criteria'           => 'array',
        'lessons_learned'             => 'array',
    ];

    public function sourceTender(): BelongsTo { return $this->belongsTo(Tender::class, 'source_tender_id'); }
    public function comparedTender(): BelongsTo { return $this->belongsTo(Tender::class, 'compared_tender_id'); }

    public function getSimilarityLabelAttribute(): string
    {
        return match ($this->similarity_level) {
            'exact_match'       => 'تطابق تام',
            'high_similarity'   => 'تشابه عالي',
            'medium_similarity' => 'تشابه متوسط',
            'weak_similarity'   => 'تشابه ضعيف',
            'partial_match'     => 'تطابق جزئي',
            default             => '—',
        };
    }
}
