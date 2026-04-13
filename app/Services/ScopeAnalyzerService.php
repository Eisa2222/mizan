<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ScopeAnalyzerService
 * ────────────────────
 * Takes a raw scope description (a few sentences from the user) and uses
 * the configured AI backend (Ollama or Claude) to expand it into:
 *
 *   - structured tasks (phases of work)
 *   - detected project type (it/construction/consulting/operations/legal)
 *   - extracted deliverables
 *   - identified missing components / clarifications needed
 *
 * Returns null on AI failure so the caller can fall back to a heuristic.
 */
class ScopeAnalyzerService
{
    public function __construct(private ClaudeService $ai) {}

    public function isAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    /**
     * @return array{
     *   tasks: array<int,string>,
     *   detected_type: string,
     *   deliverables: array<int,string>,
     *   missing: array<int,string>,
     *   summary: string
     * }|null
     */
    public function analyze(string $rawScope, ?string $projectName = null, ?string $hintType = null): ?array
    {
        if (! $this->isAvailable()) {
            return $this->heuristicFallback($rawScope, $hintType);
        }

        $title = $projectName ? "اسم المشروع: {$projectName}\n" : '';
        $hint = $hintType ? "النوع المُقترح من المستخدم: {$hintType}\n" : '';

        $system = 'أنت محلل نطاق مشاريع حكومية سعودي. مهمتك توسيع نطاق العمل المختصر إلى مهام تفصيلية.'
            . "\nأرجع JSON فقط بالعربية بهذا الشكل:"
            . "\n" . '{"tasks":["مهمة 1","مهمة 2"],"detected_type":"it","deliverables":["مخرج 1"],"missing":["معلومة ناقصة"],"summary":"وصف موسّع"}'
            . "\ndetected_type يكون إحدى: " . implode(', ', array_keys(\App\Models\Tender::TYPES))
            . "\nاكتب 5-10 مهام تفصيلية ومنطقية. اكتب 3-6 مخرجات. اكتب 2-5 معلومات ناقصة. كل النصوص بالعربية.";

        $user = $title . $hint . "نطاق العمل المختصر:\n" . $rawScope;

        try {
            $raw = $this->ai->chat(
                messages: [['role' => 'user', 'content' => $user]],
                system: $system,
                maxTokens: 2500,
            );
            $text = $raw['text'];

            if (preg_match('/\{[\s\S]*\}/u', $text, $m)) {
                $decoded = json_decode($m[0], true);
                if (is_array($decoded) && isset($decoded['tasks'])) {
                    return [
                        'tasks'         => array_values((array) ($decoded['tasks'] ?? [])),
                        'detected_type' => (string) ($decoded['detected_type'] ?? ($hintType ?? 'it')),
                        'deliverables'  => array_values((array) ($decoded['deliverables'] ?? [])),
                        'missing'       => array_values((array) ($decoded['missing'] ?? [])),
                        'summary'       => (string) ($decoded['summary'] ?? $rawScope),
                    ];
                }
            }
        } catch (Throwable $e) {
            Log::warning('ScopeAnalyzerService failed', ['error' => $e->getMessage()]);
        }

        return $this->heuristicFallback($rawScope, $hintType);
    }

    /**
     * Plain-text heuristic when AI is unavailable: split on common Arabic
     * connectors, return a generic task skeleton based on detected type.
     */
    private function heuristicFallback(string $rawScope, ?string $hintType): array
    {
        $type = $hintType ?: $this->guessType($rawScope);

        $taskSkeletons = [
            'it' => [
                'تحليل المتطلبات الوظيفية وغير الوظيفية',
                'تصميم النظام والبنية التقنية',
                'تطوير الواجهات الأمامية',
                'تطوير الخدمات الخلفية وقواعد البيانات',
                'إجراء اختبارات الجودة والأمان',
                'تدريب المستخدمين وتسليم المشروع',
                'الدعم والصيانة بعد الإطلاق',
            ],
            'construction' => [
                'الدراسات الأولية والتصاميم',
                'إعداد المخططات الهندسية',
                'تجهيز الموقع والتأسيسات',
                'تنفيذ الأعمال الإنشائية',
                'أعمال التشطيبات والتجهيزات',
                'الفحوصات والاختبارات النهائية',
                'التسليم والصيانة',
            ],
            'consulting' => [
                'دراسة الوضع الراهن',
                'تحليل الفجوات',
                'إعداد التوصيات',
                'وضع خطة التنفيذ',
                'تقديم التقارير والمتابعة',
            ],
            'operations' => [
                'حصر الأصول والأنظمة',
                'وضع خطة التشغيل اليومي',
                'الصيانة الوقائية الدورية',
                'الصيانة التصحيحية والطارئة',
                'إدارة المخزون وقطع الغيار',
                'تقارير الأداء الشهرية',
            ],
            'legal' => [
                'مراجعة الوضع القانوني',
                'إعداد المذكرات والوثائق',
                'تمثيل الجهة أمام الجهات المختصة',
                'متابعة الإجراءات والقضايا',
                'تقديم الاستشارات الدورية',
            ],
        ];

        return [
            'tasks'         => $taskSkeletons[$type] ?? $taskSkeletons['it'],
            'detected_type' => $type,
            'deliverables'  => [],
            'missing'       => [
                'حدد مدة المشروع بدقة',
                'حدد معايير التقييم والأوزان',
                'حدد متطلبات التأهيل المسبق',
            ],
            'summary'       => $rawScope,
        ];
    }

    private function guessType(string $text): string
    {
        $lower = mb_strtolower($text);
        $rules = [
            'it'           => ['نظام', 'تطوير', 'تطبيق', 'موقع', 'برمج', 'تقني', 'منصة', 'سحاب'],
            'construction' => ['إنشاء', 'بناء', 'مبنى', 'هندس', 'مقاول', 'موقع', 'تشييد'],
            'consulting'   => ['استشار', 'دراسة', 'تحليل', 'خطة', 'استراتيج'],
            'operations'   => ['تشغيل', 'صيانة', 'نظاف', 'حراس', 'دعم فني'],
            'legal'        => ['قانون', 'محام', 'استشار قانون', 'قضي', 'تقاض'],
        ];
        foreach ($rules as $type => $keywords) {
            foreach ($keywords as $kw) {
                if (mb_strpos($lower, $kw) !== false) return $type;
            }
        }
        return 'it';
    }
}
