<?php

return [

    'flash' => [
        'created'            => 'تم توليد الكراسة بنجاح. راجع الأقسام وعدّلها حسب الحاجة.',
        'regenerated'        => 'تم إعادة توليد الكراسة بنجاح.',
        'reviewed'           => 'اكتملت مراجعة الامتثال.',
        'submitted'          => 'تم إرسال الكراسة للاعتماد.',
        'approved'           => 'تم اعتماد الكراسة.',
        'rejected'           => 'تم رفض الكراسة مع ذكر السبب.',
        'deleted'            => 'تم حذف الكراسة.',
        'similarity_ignored' => 'تم تجاهل التنبيه.',
        'sections_reused'    => 'تم نسخ :count قسم من الكراسة السابقة.',
    ],

    'errors' => [
        'cannot_submit'         => 'لا يمكن إرسال هذه الكراسة.',
        'not_pending_approval'  => 'الكراسة ليست في حالة انتظار الاعتماد.',
        'approval_denied'       => 'ليس لديك صلاحية الاعتماد.',
    ],

    'notifications' => [
        'submitted_title' => 'كراسة مرسلة للاعتماد',
        'submitted_body'  => 'الكراسة ":title" مرسلة للاعتماد بواسطة :name.',
        'approved_title'  => 'تم اعتماد الكراسة',
        'approved_body'   => 'الكراسة ":title" تم اعتمادها بواسطة :name.',
        'rejected_title'  => 'تم رفض الكراسة',
        'rejected_body'   => 'الكراسة ":title" تم رفضها. السبب: :reason',
    ],

    // Project types — mirrors the keys in App\Models\Tender::TYPES. Adding a
    // new type requires updating both places; labels live here so they can
    // be translated, the const array keeps the same keys for validation.
    'types' => [
        'it'                 => 'مشروع تقني',
        'it_supply'          => 'توريد تقني وتراخيص',
        'it_install'         => 'توريد وتركيب تقني',
        'it_consulting'      => 'استشارات تقنية',
        'construction'       => 'مشروع إنشاءات',
        'engineering_design' => 'خدمات هندسية (تصميم)',
        'engineering_super'  => 'خدمات هندسية (إشراف)',
        'consulting'         => 'خدمات استشارية',
        'legal'              => 'خدمات قانونية',
        'training'           => 'تدريب وتأهيل',
        'operations'         => 'تشغيل وصيانة',
        'cleaning'           => 'نظافة وخدمات بيئية',
        'security'           => 'حراسة وأمن',
        'supply'             => 'توريد عام',
        'medical_supply'     => 'توريد طبي',
        'catering'           => 'خدمات إعاشة',
        'transport'          => 'نقل ومواصلات',
        'framework'          => 'اتفاقية إطارية',
        'other'              => 'أخرى',
    ],

    'statuses' => [
        'draft'      => 'مسودة',
        'generating' => 'جاري التوليد',
        'ready'      => 'جاهز',
        'reviewing'  => 'قيد المراجعة',
        'finalized'  => 'معتمد',
    ],

    'workflow' => [
        'draft'     => 'مسودة',
        'submitted' => 'مرسل للاعتماد',
        'approved'  => 'معتمد',
        'rejected'  => 'مرفوض',
    ],

    'similarity' => [
        'exact_match'       => 'يوجد نطاق مطابق أو شبه مطابق لكراسة سابقة داخل نفس الجهة.',
        'high'              => 'تم العثور على كراسة مشابهة بنسبة :score%. يوصى بمراجعتها قبل المتابعة.',
        'reuse_opportunity' => 'تم العثور على كراسة معتمدة سابقة يمكن استخدامها كمرجع أو كنقطة بداية.',
        'weak'              => 'تم العثور على كراسات متشابهة بنسبة ضعيفة.',
        'scope_duplicate'   => 'قد يكون هذا المشروع مكررًا مع مشروع سابق داخل الجهة.',
        'scope_high'        => 'تم العثور على كراسة مشابهة بنسبة :score%. يوصى بمراجعتها.',
        'scope_medium'      => 'تم العثور على كراسة سابقة يمكن استخدامها كمرجع.',
    ],

];
