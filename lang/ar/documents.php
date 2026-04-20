<?php

return [

    'flash' => [
        'uploaded_pending_ocr'     => 'تم رفع المستند. جاري استخراج المحتوى عبر OCR في الخلفية — حدّث الصفحة بعد دقائق.',
        'uploaded_contract'        => 'تم رفع العقد بنجاح. جاري تحليل المخاطر في الخلفية.',
        'uploaded_case'            => 'تم رفع القضية بنجاح. جاري تحليلها في الخلفية.',
        'uploaded_contract_review' => 'تم رفع العقد للمراجعة. جاري المراجعة الشاملة في الخلفية.',
        'uploaded_memo'            => 'تم رفع المسودة. جاري تحليلها وتقديم التوصيات في الخلفية.',
        'uploaded_document'        => 'تم رفع المستند بنجاح وفهرسته.',
        'content_updated'          => 'تم تحديث المحتوى وإعادة الفهرسة.',
        'deleted'                  => 'تم حذف المستند.',
    ],

    // Document type IDs 1..7 — mirrors App\Models\LegalDocument::TYPES. Keys
    // are the numeric type IDs stored in the `type` column. Source of truth
    // for labels is here; the const array keeps the IDs for iteration and
    // validation rules that need array_keys().
    'types' => [
        '1' => 'نظام',
        '2' => 'لائحة',
        '3' => 'مرسوم ملكي',
        '4' => 'قرار وزاري',
        '5' => 'تعميم',
        '6' => 'حكم قضائي',
        '7' => 'فتوى',
    ],

    // Document kinds — match App\Models\LegalDocument::KIND_* constants.
    'kinds' => [
        'document'         => 'مستند قانوني',
        'contract'         => 'عقد',
        'case'             => 'قضية',
        'contract_review'  => 'مراجعة عقد',
        'memo'             => 'مسودة مذكرة',
        'tender_review'    => 'مراجعة كراسة',
    ],

    // Allowed relation_type values for the document_relations pivot table.
    'relation_types' => [
        'implements'  => 'تنفيذية لـ',
        'amends'      => 'تعديل لـ',
        'supersedes'  => 'تلغي',
        'references'  => 'تشير إلى',
        'cites'       => 'تستشهد بـ',
        'related'     => 'ذات صلة بـ',
    ],

];
