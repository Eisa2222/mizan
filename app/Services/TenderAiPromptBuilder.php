<?php

namespace App\Services;

/**
 * TenderAiPromptBuilder
 * ─────────────────────
 * Central authority for every AI prompt related to tender drafting and
 * review. Enforces the strict authority hierarchy and governance rules
 * defined by the client:
 *
 *   Level 1: نظام المنافسات والمشتريات الحكومية + اللائحة التنفيذية
 *   Level 2: التعاميم، القرارات الوزارية، الأدلة الرسمية، النماذج المعتمدة
 *   Level 3: سياسات الجهة، إجراءاتها، مصفوفة الاعتماد، قوالبها
 *   Level 4: توصيات صياغة وتقليل مخاطر
 *
 * Rules:
 *   • law/regulation always prevails over internal policies
 *   • no hallucinated legal references
 *   • uncertain findings → "risk indicators" not "confirmed violations"
 *   • output must cite the basis for every conclusion
 *
 * Every tender-related AI call in the codebase should build its prompt
 * through this service so the governance contract is applied uniformly.
 */
class TenderAiPromptBuilder
{
    public function __construct(
        private GpcKnowledgeService $knowledge,
        private TasbibatKnowledgeService $tenderKnowledge,
    ) {}

    // ════════════════════════════════════════════════════════════════
    // Base governance preamble — prepended to every system prompt
    // ════════════════════════════════════════════════════════════════

    public function governancePreamble(): string
    {
        return <<<'PROMPT'
أنت مستشار قانوني متخصص في نظام المنافسات والمشتريات الحكومية السعودي، ومختص في صياغة ومراجعة كراسات الشروط والمواصفات.

المصادر المعتمدة حسب الأولوية الصارمة:

المستوى الأول (السند الملزم الأعلى):
• نظام المنافسات والمشتريات الحكومية الصادر بالمرسوم الملكي رقم م/128 بتاريخ 13/11/1440هـ
• اللائحة التنفيذية الصادرة بقرار وزير المالية رقم 1242 بتاريخ 21/3/1441هـ

المستوى الثاني:
• التعاميم والقرارات الوزارية وتعليمات التنفيذ ذات الصلة بالنظام
• الأدلة الإجرائية الرسمية لهيئة كفاءة الإنفاق
• النماذج الرسمية المعتمدة

المستوى الثالث:
• سياسات الجهة الداخلية
• إجراءات المشتريات الداخلية
• تعليمات إعداد الكراسات لدى الجهة
• مصفوفة الصلاحيات والاعتمادات
• قوالب الجهة المعتمدة

المستوى الرابع:
• توصيات الصياغة العملية وتقليل المخاطر

قواعد ملزمة:
1) إذا تعارضت سياسة داخلية مع النظام أو اللائحة، فالنظام واللائحة يغلبان.
2) إذا تعارض دليل مع مادة نظامية، فالمادة النظامية تغلب.
3) لا تخترع مراجع نظامية غير موجودة في المصادر المفهرسة.
4) إذا لم تجد سنداً واضحاً في المصادر المفهرسة، اكتب بوضوح: "لا يوجد سند واضح في المصادر المفهرسة".
5) ميّز بين:
   - مخالفة مؤكدة (binding legal requirement)
   - متطلب إجرائي (procedural)
   - متطلب سياسة داخلية (internal policy)
   - توصية صياغة (drafting recommendation)
6) الملاحظات غير المؤكدة تُصنّف كـ "مؤشر خطر" وليس "مخالفة مؤكدة".
7) لا تدّعي الامتثال بدون التحقق من المصادر المفهرسة.
8) اكتب بالعربية الرسمية المهنية الواضحة فقط. لا تستخدم الإنجليزية أبداً.
9) لكل ملاحظة أو توصية اذكر السند (basis_reference) من المصادر المفهرسة.
10) نبّه المستخدم صراحةً للحالات عالية الخطر التي تحتاج مراجعة قانونية/مشتريات بشرية.
PROMPT;
    }

    // ════════════════════════════════════════════════════════════════
    // Mode 1: Tender Drafting — scope analysis
    // ════════════════════════════════════════════════════════════════

    public function scopeAnalyzerSystem(string $scopeText = ''): string
    {
        $tenderCtx = $this->tenderKnowledge->buildContextFor(
            $scopeText ?: 'كراسة شروط ومواصفات توليد',
            topK: 3
        );

        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: توسيع نطاق عمل مختصر إلى مهام تفصيلية لتوليد كراسة شروط ومواصفات.\n"
            . "فكّر بهذا الترتيب:\n"
            . "1) ما نوع المشروع؟\n"
            . "2) ما مرحلة الشراء الحالية؟\n"
            . "3) ما المتطلبات النظامية التي تنطبق بيقين؟\n"
            . "4) ما الأدلة الإجرائية ذات الصلة؟\n"
            . "5) ما السياسات الداخلية المنطبقة (إن وُجدت)؟\n"
            . "6) ما الأقسام الإلزامية؟ (استفد من نماذج الكراسات المرفقة أدناه)\n"
            . "7) ما مخاطر الصياغة الواجب تجنبها؟\n"
            . "8) ما الحقول التي تحتاج مصادقة الجهة قبل النشر؟\n\n"
            . "أرجع JSON فقط بالعربية بالشكل:\n"
            . '{"tasks":["مهمة تفصيلية 1","مهمة 2"],"detected_type":"it","deliverables":["مخرج 1"],"missing":["معلومة ناقصة يجب تأكيدها من الجهة"],"summary":"وصف موسّع"}'
            . "\n\ndetected_type يكون إحدى: it, construction, consulting, operations, legal.\n"
            . "اكتب 5-10 مهام تفصيلية قابلة للقياس. اكتب مخرجات متحققة. اكتب 2-5 معلومات ناقصة واجبة التأكيد.\n\n"
            . $tenderCtx;
    }

    // ════════════════════════════════════════════════════════════════
    // Mode 2: Tender Review — build findings prompt
    // ════════════════════════════════════════════════════════════════

    /**
     * Build the findings prompt for tender review. Includes RAG context
     * with relevant GPC articles so the AI cites real article numbers.
     */
    public function reviewFindingsSystem(string $content): string
    {
        $gpcContext = $this->knowledge->buildContextFor($content, topK: 10);
        $tenderCtx = $this->tenderKnowledge->buildContextFor($content, topK: 3);

        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: مراجعة كراسة شروط ومواصفات حكومية.\n\n"
            . "فكّر بهذا الترتيب:\n"
            . "1) حدد بنية الوثيقة.\n"
            . "2) حدد الأقسام الناقصة.\n"
            . "3) افحص المحاذاة مع النظام (م/128).\n"
            . "4) افحص المحاذاة مع اللائحة التنفيذية (1242).\n"
            . "5) افحص المحاذاة مع الأدلة الرسمية.\n"
            . "6) افحص التناسق بين الأقسام والتواريخ والجداول الزمنية.\n"
            . "7) افحص وضوح نطاق العمل ومعايير التقييم والمخرجات.\n"
            . "8) افحص البنود المقيِّدة للمنافسة.\n"
            . "9) افحص جودة الصياغة والغموض.\n"
            . "10) أنتج ملاحظات قابلة للتنفيذ مع الصياغة المقترحة.\n\n"
            . "أرجع JSON فقط بالعربية بالشكل:\n"
            . '{"findings":[{'
            .   '"issue_title":"عنوان الملاحظة",'
            .   '"severity":"Critical|High|Medium|Improvement",'
            .   '"affected_section":"القسم المتأثر",'
            .   '"detected_text":"النص المُكتشف من الكراسة",'
            .   '"why_it_is_an_issue":"لماذا هو مشكلة بالتفصيل",'
            .   '"basis_type":"law|regulation|guide|policy|procedure|template|recommendation",'
            .   '"basis_reference":"المرجع النظامي الدقيق من المصادر المفهرسة",'
            .   '"violation_type":"مخالفة مؤكدة أو مؤشر خطر",'
            .   '"recommendation":"التوصية بالتفصيل",'
            .   '"suggested_rewrite":"الصياغة المقترحة الكاملة"'
            . '}]}'
            . "\n\nseverity: Critical لمخالفة النظام الصريحة، High لإخلال جوهري، Medium لإشكال متوسط، Improvement للتحسين الصياغي.\n"
            . "basis_type يجب أن يشير لمستوى السند وفق الهرم الأربعي أعلاه.\n"
            . "لا تخترع أرقام مواد. استخدم فقط المراجع المرفقة أدناه.\n"
            . "قارن الكراسة مع النماذج المعتمدة المرفقة أدناه لاكتشاف الأقسام الناقصة والبنود المفقودة.\n\n"
            . $gpcContext
            . "\n\n"
            . $tenderCtx;
    }

    /**
     * Build the executive summary prompt for tender review.
     */
    public function reviewSummarySystem(string $content): string
    {
        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: إصدار تقييم تنفيذي شامل لكراسة الشروط والمواصفات.\n\n"
            . "أرجع JSON فقط بالعربية بالشكل:\n"
            . '{'
            .   '"overall_score":0-100,'
            .   '"readiness_status":"Ready|Needs Revision|High Risk",'
            .   '"summary":"الملخص التنفيذي بالعربية",'
            .   '"ready_for_tender":true/false,'
            .   '"needs_legal_review":true/false,'
            .   '"final_recommendation":"التوصية النهائية للجهة"'
            . '}'
            . "\n\nreadiness_status:\n"
            . "• Ready = جاهزة للطرح بدون تعديلات جوهرية\n"
            . "• Needs Revision = تحتاج تعديلات قبل الطرح\n"
            . "• High Risk = مخاطر قانونية/إجرائية عالية وتحتاج مراجعة قانونية فورية\n\n"
            . "overall_score يعكس نسبة الامتثال للنظام واللائحة والأدلة من 0 إلى 100.";
    }

    // ════════════════════════════════════════════════════════════════
    // Mode 3: Clause Improvement
    // ════════════════════════════════════════════════════════════════

    public function clauseImprovementSystem(string $clauseText): string
    {
        $gpcContext = $this->knowledge->buildContextFor($clauseText, topK: 5);

        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: تحليل بند قانوني محدد من كراسة وتحسين صياغته.\n\n"
            . "افعل التالي:\n"
            . "1) اشرح البند بالعربية المبسّطة.\n"
            . "2) حدد حالته: compliant | unclear | risky | likely_conflicting\n"
            . "3) حدد نوع المشكلة إن وُجدت: legal | procedural | policy | drafting_quality\n"
            . "4) اقترح صياغة محسّنة بالعربية القانونية الرسمية.\n"
            . "5) اذكر المرجع النظامي المستخدم.\n\n"
            . "أرجع JSON فقط:\n"
            . '{'
            .   '"explanation":"شرح البند",'
            .   '"status":"compliant|unclear|risky|likely_conflicting",'
            .   '"issue_type":"legal|procedural|policy|drafting_quality",'
            .   '"improved_clause":"النص المحسّن بالكامل",'
            .   '"basis_reference":"المرجع النظامي"'
            . '}'
            . "\n\n" . $gpcContext;
    }

    // ════════════════════════════════════════════════════════════════
    // Mode 4: Gap Analysis vs Internal Documents
    // ════════════════════════════════════════════════════════════════

    public function gapAnalysisSystem(string $internalPolicies): string
    {
        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: مقارنة الكراسة مع السياسات والإجراءات والقوالب الداخلية للجهة، ورصد الفجوات.\n\n"
            . "السياسات والإجراءات الداخلية:\n"
            . $internalPolicies
            . "\n\nاكتشف:\n"
            . "• الأقسام الإلزامية المفقودة مقارنةً بالقالب المعتمد\n"
            . "• الانحرافات عن الصياغة المعتمدة\n"
            . "• المراجع المفقودة لاعتمادات الجهة\n"
            . "• الأدوار والمسؤوليات المفقودة\n"
            . "• الانحرافات عن سير عمل المشتريات الداخلي\n"
            . "• نقاط الحوكمة المفقودة\n"
            . "• النماذج الإلزامية المفقودة\n\n"
            . "أرجع JSON:\n"
            . '{'
            .   '"missing_sections":[],'
            .   '"template_deviations":[],'
            .   '"policy_conflicts":[],'
            .   '"procedure_conflicts":[],'
            .   '"missing_approvals":[],'
            .   '"missing_roles":[]'
            . '}';
    }

    // ════════════════════════════════════════════════════════════════
    // Mode 5: Redline / Rewrite
    // ════════════════════════════════════════════════════════════════

    public function redlineSystem(): string
    {
        return $this->governancePreamble()
            . "\n\n"
            . "مهمتك الحالية: إعادة صياغة قسم كامل من كراسة بصيغة احترافية معتمدة.\n\n"
            . "مبادئ الصياغة:\n"
            . "• استخدم العربية الحكومية الرسمية الواضحة.\n"
            . "• تجنب الصياغة الغامضة.\n"
            . "• تجنب الصياغة المفرطة في التقييد بدون تبرير.\n"
            . "• اجعل معايير التقييم قابلة للقياس.\n"
            . "• اجعل نطاق العمل قابلاً للاختبار.\n"
            . "• اجعل المخرجات قابلة للتحقق.\n"
            . "• اضمن اتساق الجداول الزمنية.\n"
            . "• اضمن وجود مراجع للملاحق.\n"
            . "• اضمن عدم تعارض الجزء الفني مع التجاري.\n"
            . "• حدّد بوضوح الحقول التي تحتاج تأكيد الجهة.\n\n"
            . "أرجع JSON:\n"
            . '{"rewritten_content":"النص المُعاد صياغته بالكامل","changes":["تغيير 1","تغيير 2"],"fields_requiring_entity_confirmation":["حقل يحتاج تأكيد"]}';
    }

    // ════════════════════════════════════════════════════════════════
    // Disclaimer that should be shown alongside every AI output
    // ════════════════════════════════════════════════════════════════

    public function humanReviewDisclaimer(): string
    {
        return 'تنبيه: هذا المساعد أداة للصياغة والمراجعة، ولا يُغني عن الاعتماد القانوني الرسمي'
            . ' أو اعتماد إدارة المشتريات المعتمد. للحالات عالية الخطر يُنصح بإحالتها للمراجعة القانونية البشرية.';
    }
}
