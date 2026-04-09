<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LegalDocument extends Model
{
    protected $fillable = [
        'org_id', 'title', 'title_en', 'type', 'kind', 'summary', 'content',
        'issued_at', 'reference_number', 'source_entity', 'metadata', 'analysis',
        'file_path', 'file_name', 'file_size', 'uploaded_by', 'is_private',
    ];

    protected $casts = [
        'issued_at' => 'date',
        'metadata'  => 'array',
        'analysis'  => 'array',
        'is_private' => 'boolean',
    ];

    public const TYPES = [
        1 => 'نظام',
        2 => 'لائحة',
        3 => 'مرسوم ملكي',
        4 => 'قرار وزاري',
        5 => 'تعميم',
        6 => 'حكم قضائي',
        7 => 'فتوى',
    ];

    /** Per-row "kind" — distinguishes regular documents from specialized types that get AI analysis. */
    public const KIND_DOCUMENT      = 'document';
    public const KIND_CONTRACT      = 'contract';
    public const KIND_CASE          = 'case';
    public const KIND_CONTRACT_REVIEW = 'contract_review';
    public const KIND_MEMO          = 'memo';

    public const KINDS = [
        self::KIND_DOCUMENT        => 'مستند قانوني',
        self::KIND_CONTRACT        => 'عقد',
        self::KIND_CASE            => 'قضية',
        self::KIND_CONTRACT_REVIEW => 'مراجعة عقد',
        self::KIND_MEMO            => 'مسودة مذكرة',
    ];

    /** Allowed relation_type values for the document_relations pivot table. */
    public const RELATION_TYPES = [
        'implements'  => 'تنفيذية لـ',
        'amends'      => 'تعديل لـ',
        'supersedes'  => 'تلغي',
        'references'  => 'تشير إلى',
        'cites'       => 'تستشهد بـ',
        'related'     => 'ذات صلة بـ',
    ];

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class, 'document_id')->orderBy('chunk_index');
    }

    public function folders(): BelongsToMany
    {
        return $this->belongsToMany(Folder::class, 'folder_documents', 'document_id', 'folder_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class, 'org_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function articleUpdates(): HasMany
    {
        return $this->hasMany(ArticleUpdate::class, 'document_id')->orderByDesc('update_date');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class, 'document_id')->orderBy('version_number');
    }

    /** Outgoing relations: this document points to others ("this implements X"). */
    public function relationsFrom(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'from_document_id');
    }

    /** Incoming relations: other documents point to this one ("X implements this"). */
    public function relationsTo(): HasMany
    {
        return $this->hasMany(DocumentRelation::class, 'to_document_id');
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? '—';
    }

    public function getKindLabelAttribute(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS[self::KIND_DOCUMENT];
    }
}
