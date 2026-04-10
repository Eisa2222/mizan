<?php

namespace App\Services;

/**
 * ClauseService
 * ─────────────
 * Returns standard legal clauses ready to inject into a tender document.
 *
 * Each clause is a static template (Arabic legal language) with optional
 * variable substitution (e.g., {{duration}}, {{penalty_pct}}).
 *
 * Selection logic: each project type gets a curated list of relevant clauses.
 * Callers can also request individual clauses by key.
 */
class ClauseService
{
    /** Standard clause library — keyed by clause type. */
    private const CLAUSES = [
        'sla' => [
            'title' => 'اتفاقية مستوى الخدمة (SLA)',
            'content' => "يلتزم المتعاقد بتحقيق مستويات الخدمة التالية:\n"
                . "• توفر النظام: 99.5% خلال أوقات العمل الرسمية.\n"
                . "• زمن الاستجابة للحوادث الحرجة: خلال 30 دقيقة من الإبلاغ.\n"
                . "• زمن الإصلاح للحوادث الحرجة: خلال 4 ساعات.\n"
                . "• زمن الاستجابة للحوادث المتوسطة: خلال ساعتين.\n"
                . "• تقارير دورية شهرية عن مستوى الخدمة المحقق.\n\n"
                . "يحق للجهة المشترية تطبيق غرامات في حال الإخلال بمستويات الخدمة المتفق عليها.",
        ],
        'penalties' => [
            'title' => 'الغرامات والجزاءات',
            'content' => "تطبّق الغرامات التالية في حال إخلال المتعاقد بالتزاماته:\n"
                . "• تأخير في التسليم: غرامة 1% من قيمة العقد عن كل أسبوع تأخير، بحد أقصى 10%.\n"
                . "• إخلال بمستوى الخدمة: خصم نسبي من المستحقات الشهرية.\n"
                . "• عدم الالتزام بالمواصفات الفنية: إصلاح على نفقة المتعاقد.\n"
                . "• تكرار المخالفات: يحق للجهة فسخ العقد ومصادرة الضمان النهائي.\n\n"
                . "تطبيق الغرامات يكون وفقاً لأحكام نظام المنافسات والمشتريات الحكومية.",
        ],
        'confidentiality' => [
            'title' => 'السرية وحماية المعلومات',
            'content' => "يلتزم المتعاقد بـ:\n"
                . "• الحفاظ على سرية جميع المعلومات والبيانات التي يطّلع عليها بحكم تنفيذ العقد.\n"
                . "• عدم إفشاء أي معلومة لأي طرف ثالث دون موافقة كتابية مسبقة من الجهة.\n"
                . "• توقيع جميع العاملين في المشروع على اتفاقية عدم إفشاء (NDA).\n"
                . "• إعادة جميع المستندات والبيانات للجهة عند انتهاء العقد.\n"
                . "• استمرار التزام السرية لمدة 5 سنوات بعد انتهاء العقد.\n\n"
                . "أي إخلال بهذا البند يعرّض المتعاقد للمساءلة القانونية.",
        ],
        'data_protection' => [
            'title' => 'حماية البيانات',
            'content' => "يلتزم المتعاقد بـ:\n"
                . "• الالتزام بنظام حماية البيانات الشخصية السعودي ولوائحه التنفيذية.\n"
                . "• تخزين البيانات داخل المملكة العربية السعودية.\n"
                . "• تطبيق ضوابط الهيئة الوطنية للأمن السيبراني (NCA).\n"
                . "• تشفير البيانات أثناء النقل والتخزين.\n"
                . "• إبلاغ الجهة فوراً عن أي اختراق أو تسريب للبيانات.\n"
                . "• عدم نقل البيانات خارج البلاد إلا بموافقة كتابية.",
        ],
        'warranty' => [
            'title' => 'الضمان',
            'content' => "يقدم المتعاقد:\n"
                . "• ضماناً لمدة سنة من تاريخ التسليم النهائي يغطي العيوب الفنية.\n"
                . "• إصلاح أي عيب يكتشف خلال فترة الضمان على نفقته.\n"
                . "• استبدال أي جزء معيب دون أي تكلفة إضافية على الجهة.\n"
                . "• توفير قطع الغيار اللازمة لمدة 5 سنوات على الأقل.\n\n"
                . "لا يشمل الضمان الأعطال الناتجة عن سوء الاستخدام من قِبل الجهة.",
        ],
        'payment' => [
            'title' => 'شروط الدفع',
            'content' => "تتم المدفوعات وفق الجدول التالي:\n"
                . "• 20% دفعة مقدمة عند توقيع العقد، مقابل ضمان بنكي بنفس القيمة.\n"
                . "• 40% بعد إنجاز المرحلة الأولى وقبولها من الجهة.\n"
                . "• 30% بعد إنجاز المرحلة الثانية وقبولها.\n"
                . "• 10% بعد التسليم النهائي وفترة التشغيل التجريبي.\n\n"
                . "تُحوّل المدفوعات خلال 30 يوماً من تاريخ تقديم الفاتورة المعتمدة.",
        ],
        'ip' => [
            'title' => 'الملكية الفكرية',
            'content' => "تنتقل ملكية جميع المخرجات الفكرية الناتجة عن تنفيذ هذا العقد إلى الجهة المشترية، بما في ذلك:\n"
                . "• الكود المصدري للأنظمة المطورة.\n"
                . "• الوثائق الفنية والتصاميم.\n"
                . "• البيانات والتقارير.\n"
                . "• حقوق النشر والاستخدام.\n\n"
                . "يحتفظ المتعاقد بحقوق أدوات التطوير المملوكة له مسبقاً والتي لا تتعلق بالمشروع تحديداً.",
        ],
        'support' => [
            'title' => 'الدعم الفني',
            'content' => "يلتزم المتعاقد بتقديم الدعم الفني التالي بعد التسليم:\n"
                . "• دعم فني مجاني لمدة 6 أشهر بعد التسليم النهائي.\n"
                . "• مكتب مساعدة متاح خلال أوقات العمل الرسمية.\n"
                . "• استجابة لأي استفسار خلال 24 ساعة عمل.\n"
                . "• تحديثات أمنية دورية.\n"
                . "• تدريب فريق الجهة على التشغيل والصيانة.",
        ],
        'termination' => [
            'title' => 'إنهاء العقد',
            'content' => "يحق لأي طرف إنهاء العقد في الحالات التالية:\n"
                . "• إخلال جوهري من الطرف الآخر بالتزاماته العقدية.\n"
                . "• القوة القاهرة التي تستمر أكثر من 60 يوماً.\n"
                . "• الاتفاق المتبادل بين الطرفين.\n\n"
                . "في حال الإنهاء، يتم تسوية المستحقات وفق ما تم إنجازه فعلياً، مع مراعاة أحكام نظام المنافسات والمشتريات الحكومية.",
        ],
    ];

    /** Default clause set per project type. */
    private const TYPE_CLAUSES = [
        'it'           => ['sla', 'penalties', 'confidentiality', 'data_protection', 'warranty', 'payment', 'ip', 'support', 'termination'],
        'construction' => ['penalties', 'warranty', 'payment', 'termination'],
        'consulting'   => ['confidentiality', 'ip', 'payment', 'termination'],
        'operations'   => ['sla', 'penalties', 'warranty', 'payment', 'support', 'termination'],
        'legal'        => ['confidentiality', 'payment', 'termination'],
    ];

    /** Get all available clause types as key => Arabic title. */
    public function availableClauses(): array
    {
        $list = [];
        foreach (self::CLAUSES as $key => $clause) {
            $list[$key] = $clause['title'];
        }
        return $list;
    }

    /** Get the default clause keys for a project type. */
    public function defaultClausesFor(string $type): array
    {
        return self::TYPE_CLAUSES[$type] ?? self::TYPE_CLAUSES['it'];
    }

    /**
     * Build a list of clause records (ready for the tender_clauses table).
     *
     * @return array<int,array{clause_type:string,title:string,content:string,order:int}>
     */
    public function buildClauses(string $type, array $variables = []): array
    {
        $keys = $this->defaultClausesFor($type);
        $result = [];
        $order = 0;
        foreach ($keys as $key) {
            if (! isset(self::CLAUSES[$key])) continue;
            $clause = self::CLAUSES[$key];
            $result[] = [
                'clause_type' => $key,
                'title'       => $clause['title'],
                'content'     => $this->interpolate($clause['content'], $variables),
                'order'       => $order++,
            ];
        }
        return $result;
    }

    /** Render clauses as a single text block (for use in template's {{clauses_block}}). */
    public function renderAsBlock(array $clauses): string
    {
        $lines = [];
        foreach ($clauses as $c) {
            $lines[] = '── ' . $c['title'] . ' ──';
            $lines[] = $c['content'];
            $lines[] = '';
        }
        return implode("\n", $lines);
    }

    private function interpolate(string $template, array $vars): string
    {
        return preg_replace_callback('/\{\{(\w+)\}\}/', function ($m) use ($vars) {
            return $vars[$m[1]] ?? $m[0];
        }, $template) ?? $template;
    }
}
