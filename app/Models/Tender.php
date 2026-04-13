<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tender extends Model
{
    protected $fillable = [
        'org_id', 'created_by', 'title', 'description', 'scope_input',
        'type', 'duration', 'deliverables', 'evaluation_criteria',
        'special_conditions', 'boq_items', 'expanded_scope', 'normalized_scope', 'status',
    ];

    protected $casts = [
        'deliverables' => 'array',
        'evaluation_criteria' => 'array',
        'special_conditions' => 'array',
        'boq_items' => 'array',
        'expanded_scope' => 'array',
    ];

    /** Project type → Arabic label */
    public const TYPES = [
        // تقنية
        'it'                 => 'مشروع تقني',
        'it_supply'          => 'توريد تقني وتراخيص',
        'it_install'         => 'توريد وتركيب تقني',
        'it_consulting'      => 'استشارات تقنية',
        // إنشاءات وهندسة
        'construction'       => 'مشروع إنشاءات',
        'engineering_design' => 'خدمات هندسية (تصميم)',
        'engineering_super'  => 'خدمات هندسية (إشراف)',
        // استشارات
        'consulting'         => 'خدمات استشارية',
        'legal'              => 'خدمات قانونية',
        'training'           => 'تدريب وتأهيل',
        // تشغيل وصيانة
        'operations'         => 'تشغيل وصيانة',
        'cleaning'           => 'نظافة وخدمات بيئية',
        'security'           => 'حراسة وأمن',
        // توريد
        'supply'             => 'توريد عام',
        'medical_supply'     => 'توريد طبي',
        'catering'           => 'خدمات إعاشة',
        'transport'          => 'نقل ومواصلات',
        // اتفاقيات
        'framework'          => 'اتفاقية إطارية',
        // أخرى
        'other'              => 'أخرى',
    ];

    public const STATUSES = [
        'draft'      => 'مسودة',
        'generating' => 'جاري التوليد',
        'ready'      => 'جاهز',
        'reviewing'  => 'قيد المراجعة',
        'finalized'  => 'معتمد',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(TenderSection::class)->orderBy('order');
    }

    public function clauses(): HasMany
    {
        return $this->hasMany(TenderClause::class)->orderBy('order');
    }

    public function review(): HasOne
    {
        return $this->hasOne(TenderReview::class)->latestOfMany();
    }

    public function similarityResults(): HasMany
    {
        return $this->hasMany(TenderSimilarityResult::class, 'source_tender_id')->orderByDesc('final_similarity_score');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? '—';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? '—';
    }
}
