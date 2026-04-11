<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GpcArticle — one article from نظام المنافسات والمشتريات الحكومية or its
 * Executive Regulation, or one section from a كفاءة الإنفاق guide.
 *
 * Used by GpcKnowledgeService to retrieve relevant references for the
 * AI to cite when reviewing tender booklets.
 */
class GpcArticle extends Model
{
    protected $table = 'gpc_knowledge';

    protected $fillable = [
        'source', 'source_label', 'article_number', 'article_label',
        'chapter', 'topic', 'content', 'normalized', 'keywords', 'related_to',
    ];

    protected $casts = [
        'keywords'   => 'array',
        'related_to' => 'array',
    ];

    public const SOURCES = [
        'system'           => 'نظام المنافسات والمشتريات الحكومية (م/128)',
        'regulation'       => 'اللائحة التنفيذية للنظام (1242)',
        'guide_documents'  => 'دليل إعداد وإصدار وثائق المنافسة',
        'guide_qualifying' => 'دليل التأهيل المسبق وطرح العطاءات',
        'guide_awarding'   => 'دليل الترسية وإبرام العقد',
    ];

    public function getSourceLabelAttribute(): string
    {
        return self::SOURCES[$this->source] ?? $this->source;
    }
}
