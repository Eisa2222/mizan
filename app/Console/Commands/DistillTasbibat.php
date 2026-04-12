<?php

namespace App\Console\Commands;

use App\Models\LegalDocument;
use App\Services\ClaudeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('documents:distill-tasbibat {--batch=tasbibat_2026_04 : import_batch marker to filter} {--force : Re-distill even if analysis already exists} {--limit= : Process at most N documents} {--id= : Process a single document id}')]
#[Description('Distill judicial reasoning PDFs into structured analysis (topics, reasoning patterns, cited laws) for RAG retrieval')]
class DistillTasbibat extends Command
{
    public function handle(ClaudeService $ai): int
    {
        if (! $ai->isConfigured()) {
            $this->error('AI غير مُكوَّن. راجع AI_PROVIDER في .env');
            return self::FAILURE;
        }

        $query = LegalDocument::query()
            ->where('kind', LegalDocument::KIND_DOCUMENT)
            ->whereJsonContains('metadata->import_batch', $this->option('batch'));

        if ($id = $this->option('id')) {
            $query->where('id', (int) $id);
        }

        if (! $this->option('force')) {
            // Skip docs that already have a distilled analysis
            $query->where(function ($w) {
                $w->whereNull('analysis')
                  ->orWhereJsonLength('analysis', 0);
            });
        }

        if ($limit = $this->option('limit')) {
            $query->limit((int) $limit);
        }

        $docs = $query->orderBy('id')->get();
        $total = $docs->count();

        if ($total === 0) {
            $this->info('لا توجد مستندات للمعالجة.');
            return self::SUCCESS;
        }

        $this->info("📚 سيتم تقطير {$total} مستند.");
        $this->line('   النموذج: ' . config('services.ai.ollama_model', config('services.anthropic.model', 'unknown')));

        $stats = ['ok' => 0, 'failed' => 0, 'skipped_empty' => 0];
        $startTime = microtime(true);

        foreach ($docs as $i => $doc) {
            $idx = $i + 1;
            $content = trim((string) $doc->content);
            if ($content === '') {
                $this->line("  [{$idx}/{$total}] ⊘ #{$doc->id} — لا يوجد محتوى");
                $stats['skipped_empty']++;
                continue;
            }

            // Truncate to keep prompt + response under Ollama's context window.
            // gemma3:12b has an 8k default; 6000 chars of content + ~2k prompt overhead.
            $truncated = mb_substr($content, 0, 6000);

            $this->line("  [{$idx}/{$total}] ⌛ #{$doc->id} — {$doc->title}");
            $tStart = microtime(true);

            try {
                $result = $ai->chatJson(
                    messages: [[
                        'role'    => 'user',
                        'content' => $truncated,
                    ]],
                    system: $this->distillSystemPrompt($doc),
                    maxTokens: 2048,
                );

                $data = $result['data'];
                // Validate minimum shape before persisting
                if (! is_array($data) || ! isset($data['summary'])) {
                    throw new \RuntimeException('رد AI بدون "summary"');
                }

                // Normalize to stable schema
                $analysis = [
                    'summary'             => (string) ($data['summary'] ?? ''),
                    'court_type'          => (string) ($data['court_type'] ?? ''),
                    'legal_topics'        => array_values((array) ($data['legal_topics'] ?? [])),
                    'reasoning_patterns'  => array_values((array) ($data['reasoning_patterns'] ?? [])),
                    'cited_laws'          => array_values((array) ($data['cited_laws'] ?? [])),
                    'key_phrases'         => array_values((array) ($data['key_phrases'] ?? [])),
                    'applicable_to'       => array_values((array) ($data['applicable_to'] ?? [])),
                    'distilled_at'        => now()->toIso8601String(),
                    'distill_model'       => config('services.ai.ollama_model', 'unknown'),
                ];

                $doc->analysis = $analysis;
                $meta = $doc->metadata ?? [];
                $meta['distill_status'] = 'ready';
                $meta['distilled_at']   = now()->toIso8601String();
                $doc->metadata = $meta;
                $doc->save();

                $elapsed = round(microtime(true) - $tStart, 1);
                $topicsCount = count($analysis['legal_topics']);
                $this->line("     ✓ {$topicsCount} موضوع · {$elapsed}ث");
                $stats['ok']++;
            } catch (Throwable $e) {
                $elapsed = round(microtime(true) - $tStart, 1);
                $this->error("     ✗ فشل ({$elapsed}ث): " . mb_substr($e->getMessage(), 0, 200));
                $stats['failed']++;

                $meta = $doc->metadata ?? [];
                $meta['distill_status'] = 'failed';
                $meta['distill_error']  = mb_substr($e->getMessage(), 0, 500);
                $doc->metadata = $meta;
                $doc->save();
            }
        }

        $totalElapsed = round((microtime(true) - $startTime) / 60, 1);
        $this->newLine();
        $this->info('══════════════════════════════════');
        $this->info("✓ تم تقطير: {$stats['ok']}");
        $this->line("✗ فشل: {$stats['failed']}");
        $this->line("⊘ فارغ: {$stats['skipped_empty']}");
        $this->line("⏱  الوقت: {$totalElapsed} دقيقة");

        return self::SUCCESS;
    }

    private function distillSystemPrompt(LegalDocument $doc): string
    {
        $specialtyLabel = $doc->metadata['specialty_label'] ?? $doc->metadata['folder_origin'] ?? $doc->source_entity ?? 'قضاء عام';

        return <<<PROMPT
أنت باحث قانوني سعودي متخصص. مهمتك تقطير هذه الوثيقة القانونية من فئة "{$specialtyLabel}" إلى تحليل منظَّم قابل للاسترجاع لاحقاً.

اقرأ النص بعناية واستخرج:
1. **summary**: ملخص واضح في 2-3 أسطر يوضح موضوع الوثيقة وأهميتها العملية للقاضي/المحامي.
2. **court_type**: نوع القضاء المطبَّق (تجاري، جزائي، عمالي، أحوال شخصية، تنفيذ، عام).
3. **legal_topics**: قائمة بأهم 3-8 مواضيع قانونية تعالجها الوثيقة (مثال: "فسخ عقد بيع"، "إثبات الزوجية"، "تعويض عن ضرر"). جمل قصيرة.
4. **reasoning_patterns**: قائمة بأهم 3-6 أنماط تسبيب مستخلصة من الأحكام (مثال: "إذا ثبت العيب الموجب للخيار قبل القبض، فللمشتري الفسخ استناداً إلى..."). اكتب النمط كقالب قابل لإعادة الاستخدام في مذكرات جديدة.
5. **cited_laws**: قائمة بالأنظمة/المواد المُستشهد بها صراحة في الوثيقة (مثال: "المادة 76 من نظام العمل"، "نظام المرافعات الشرعية"). فقط ما ذُكر فعلياً.
6. **key_phrases**: 5-10 عبارات مفتاحية بصياغتها العربية الأصلية تُفيد البحث والاسترجاع لاحقاً.
7. **applicable_to**: قائمة بأنواع القضايا أو الدعاوى التي يصلح هذا التسبيب كقالب لها (مثال: ["دعاوى فسخ البيع", "دعاوى خيار العيب"]).

قواعد صارمة:
- لا تخترع أنظمة أو مواد غير موجودة في النص. إذا لم تُذكر مادة صريحة، اترك cited_laws فارغة.
- اكتب كل شيء بالعربية فقط.
- reasoning_patterns يجب أن تكون قوالب قابلة لإعادة الاستخدام، لا اقتباسات حرفية طويلة.
- summary يجب أن يكون جملتين أو ثلاث فقط، لا فقرة طويلة.

أرجع JSON صالح فقط بالشكل التالي بدون أي نص إضافي:
{
  "summary": "…",
  "court_type": "…",
  "legal_topics": ["…"],
  "reasoning_patterns": ["…"],
  "cited_laws": ["…"],
  "key_phrases": ["…"],
  "applicable_to": ["…"]
}
PROMPT;
    }
}
