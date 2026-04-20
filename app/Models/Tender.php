<?php

namespace App\Models;

use App\Enums\TenderStatus;
use App\Enums\TenderWorkflowStatus;
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
        'workflow_status', 'submitted_by', 'submitted_at', 'approved_by', 'approved_at', 'rejection_reason',
    ];

    protected $casts = [
        'deliverables' => 'array',
        'evaluation_criteria' => 'array',
        'special_conditions' => 'array',
        'boq_items' => 'array',
        'expanded_scope' => 'array',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
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

    /** Workflow: منشئ → معتمد */
    public const WORKFLOW = [
        'draft'     => 'مسودة',
        'submitted' => 'مرسل للاعتماد',
        'approved'  => 'معتمد',
        'rejected'  => 'مرفوض',
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

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function getWorkflowLabelAttribute(): string
    {
        return self::WORKFLOW[$this->workflow_status] ?? '—';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::typeLabel($this->type);
    }

    public function getStatusLabelAttribute(): string
    {
        return self::statusLabel($this->status);
    }

    /**
     * Translate a tender type key. Falls back to the Arabic label hard-coded
     * in self::TYPES so old callers that rely on the const still work; prefer
     * this helper everywhere new so labels can be translated per locale
     * without touching the model.
     */
    public static function typeLabel(?string $type): string
    {
        if ($type === null) {
            return '—';
        }

        $translated = trans("tenders.types.$type");
        if (is_string($translated) && $translated !== "tenders.types.$type") {
            return $translated;
        }

        return self::TYPES[$type] ?? '—';
    }

    public static function statusLabel(?string $status): string
    {
        if ($status === null) {
            return '—';
        }

        $translated = trans("tenders.statuses.$status");
        if (is_string($translated) && $translated !== "tenders.statuses.$status") {
            return $translated;
        }

        return self::STATUSES[$status] ?? '—';
    }

    public static function workflowLabel(?string $workflow): string
    {
        if ($workflow === null) {
            return '—';
        }

        $translated = trans("tenders.workflow.$workflow");
        if (is_string($translated) && $translated !== "tenders.workflow.$workflow") {
            return $translated;
        }

        return self::WORKFLOW[$workflow] ?? '—';
    }

    /** Typed view of the string status column. */
    public function statusEnum(): ?TenderStatus
    {
        return $this->status !== null ? TenderStatus::tryFrom((string) $this->status) : null;
    }

    /** Typed view of the string workflow_status column. */
    public function workflowEnum(): ?TenderWorkflowStatus
    {
        return $this->workflow_status !== null
            ? TenderWorkflowStatus::tryFrom((string) $this->workflow_status)
            : null;
    }
}
