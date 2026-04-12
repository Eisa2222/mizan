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
        'special_conditions', 'boq_items', 'expanded_scope', 'status',
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
        'it'           => 'مشروع تقني',
        'construction' => 'مشروع إنشاءات',
        'consulting'   => 'خدمات استشارية',
        'operations'   => 'تشغيل وصيانة',
        'legal'        => 'خدمات قانونية',
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

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? '—';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? '—';
    }
}
