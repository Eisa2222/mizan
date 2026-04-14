<?php

namespace App\Jobs;

use App\Models\AppNotification;
use App\Models\LegalDocument;
use App\Services\ClaudeService;
use App\Services\GpcKnowledgeService;
use App\Services\TenderAiPromptBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * TenderReviewJob
 * ───────────────
 * Multi-layer analysis of government procurement tender documents against
 * Saudi Competition & Government Procurement Law and its Executive Regulations.
 *
 * Four inspection layers:
 *   1. Legal compliance (نظامي) — mandatory clauses, legal references
 *   2. Formal (شكلي) — structure, numbering, attachments
 *   3. Substantive (موضوعي) — conflicts, ambiguity, risks
 *   4. Fairness & competition (عدالة) — restrictive conditions, bias indicators
 *
 * Output: compliance score + categorized findings + executive summary
 */
class TenderReviewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 600;

    public function __construct(public LegalDocument $document) {}

    public function handle(ClaudeService $claude, GpcKnowledgeService $knowledge, TenderAiPromptBuilder $prompts): void
    {
        $document = $this->document->fresh();
        if (! $document) return;

        if (! $claude->isConfigured()) {
            $this->markStatus($document, 'unavailable', 'ميزة AI غير متاحة.');
            return;
        }

        $content = trim((string) $document->content);
        if ($content === '') {
            $this->markStatus($document, 'failed', 'لا يوجد نص في الكراسة للمراجعة.');
            return;
        }

        $content = mb_substr($content, 0, 80000);

        // Clean broken cmap characters that confuse the AI model.
        $arab = '\x{0600}-\x{06FF}';
        $content = preg_replace("/([$arab])[?|~`^]+([$arab])/u", '$1 $2', $content) ?? $content;
        $content = preg_replace("/([$arab])[A-Za-z]{1,2}([$arab])/u", '$1 $2', $content) ?? $content;
        $content = preg_replace("/([$arab])[A-Za-z]{1,2}([$arab])/u", '$1 $2', $content) ?? $content;

        // ═══ Build system prompts via the centralized TenderAiPromptBuilder ═══
        // Every governance rule, authority hierarchy, RAG context and output
        // schema lives in that one service — this job just dispatches to it.
        $findingsSystem = $prompts->reviewFindingsSystem($content);
        $summarySystem  = $prompts->reviewSummarySystem($content);

        $findings = [];
        $summary = [];
        $lastError = '';

        // Get findings — try up to 3 attempts with flexible parsing.
        // Ollama sometimes returns different JSON shapes (findings/suggestions/issues/etc).
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $raw = $claude->chat(
                    messages: [['role' => 'user', 'content' => "راجع كراسة الشروط والمواصفات التالية والتزم بالهرم الأربعي للسند. ارجع JSON بهذا الشكل بالضبط: {\"findings\":[{\"issue_title\":\"...\",\"severity\":\"Critical|High|Medium|Improvement\",\"why_it_is_an_issue\":\"...\",\"recommendation\":\"...\"}]}\n\nالكراسة:\n" . mb_substr($content, 0, 8000)]],
                    system: $findingsSystem,
                    maxTokens: 4000,
                );
                $text = $raw['text'];
                Log::info("TenderReviewJob findings raw", ['attempt' => $attempt, 'text_len' => mb_strlen($text), 'preview' => mb_substr($text, 0, 200)]);

                $findings = $this->extractFindingsFromResponse($text);
                if (!empty($findings)) break;
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("TenderReviewJob findings attempt $attempt failed", ['error' => $lastError]);
            }
        }

        // Get summary — also use chat() to avoid JSON mode issues
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $raw = $claude->chat(
                    messages: [['role' => 'user', 'content' => "قيّم هذه الكراسة بشكل عام مقابل الهرم الأربعي للسند.\n\nالكراسة:\n" . mb_substr($content, 0, 8000)]],
                    system: $summarySystem,
                    maxTokens: 800,
                );
                if (preg_match('/\{[\s\S]*\}/u', $raw['text'], $m)) {
                    $decoded = json_decode($m[0], true);
                    if (is_array($decoded) && (isset($decoded['overall_score']) || isset($decoded['compliance_score']))) {
                        // Normalize field name: the new schema uses overall_score,
                        // the legacy schema used compliance_score. Accept either.
                        if (isset($decoded['overall_score']) && ! isset($decoded['compliance_score'])) {
                            $decoded['compliance_score'] = $decoded['overall_score'];
                        }
                        $summary = $decoded;
                        break;
                    }
                }
            } catch (Throwable $e) {
                $lastError = $e->getMessage();
                Log::warning("TenderReviewJob summary attempt $attempt failed", ['error' => $lastError]);
            }
        }

        // Proceed as long as we got SOMETHING (even just a score with no findings)
        if (empty($findings) && empty($summary) && empty($summary['compliance_score'] ?? null)) {
            $this->markStatus($document, 'failed', 'فشل مراجعة الكراسة: ' . ($lastError ?: 'لم يتم الحصول على نتائج.'));
            return;
        }

        // Ensure findings is an array of arrays (not a single finding object)
        if (!empty($findings) && (isset($findings['issue_title']) || isset($findings['category']))) {
            $findings = [$findings];
        }

        // Normalize each finding: accept both the old schema (category/title/issue)
        // and the new governance schema (issue_title, basis_type, basis_reference).
        $findings = array_map(function ($f) {
            $sevEn = $this->normalizeSeverity($f['severity'] ?? 'Medium');
            $basisType = $f['basis_type'] ?? 'law';
            return [
                'issue_title'        => $f['issue_title'] ?? $f['title'] ?? '',
                'severity'           => $sevEn, // English for logic (Critical/High/Medium/Improvement)
                'severity_label'     => $this->severityLabel($sevEn), // Arabic for display
                'affected_section'   => $f['affected_section'] ?? $f['category'] ?? '',
                'detected_text'      => $f['detected_text'] ?? '',
                'why_it_is_an_issue' => $f['why_it_is_an_issue'] ?? $f['issue'] ?? '',
                'basis_type'         => $basisType,
                'basis_reference'    => $f['basis_reference'] ?? $f['legal_reference'] ?? '',
                'violation_type'     => $f['violation_type'] ?? null,
                'recommendation'     => $f['recommendation'] ?? '',
                'suggested_rewrite'  => $f['suggested_rewrite'] ?? '',
                // Back-compat keys for existing views (Arabic labels)
                'title'              => $f['issue_title'] ?? $f['title'] ?? '',
                'category'           => $this->categoryLabel($basisType),
                'issue'              => $f['why_it_is_an_issue'] ?? $f['issue'] ?? '',
                'legal_reference'    => $f['basis_reference'] ?? $f['legal_reference'] ?? '',
            ];
        }, $findings);

        // Override severity with Arabic labels for view compatibility
        $findings = array_map(function ($f) {
            $f['severity'] = $f['severity_label']; // Replace English with Arabic
            return $f;
        }, $findings);

        // Count by severity (using Arabic labels now)
        $critical    = collect($findings)->where('severity', 'حرجة')->count();
        $high        = collect($findings)->where('severity', 'عالية')->count();
        $medium      = collect($findings)->where('severity', 'متوسطة')->count();
        $improvement = collect($findings)->where('severity', 'تحسينية')->count();

        $document->analysis = [
            'compliance_score'    => $summary['compliance_score'] ?? $summary['overall_score'] ?? 70,
            'executive_summary'   => $summary['summary'] ?? $summary['executive_summary'] ?? 'تم المراجعة.',
            'ready_for_tender'    => $summary['ready_for_tender'] ?? ($critical === 0),
            'needs_legal_review'  => $summary['needs_legal_review'] ?? ($critical > 0 || $high > 2),
            'readiness_status'    => $summary['readiness_status'] ?? null,
            'final_recommendation' => $summary['final_recommendation'] ?? null,
            'findings'            => $findings,
            'statistics'          => ['critical' => $critical, 'high' => $high, 'medium' => $medium, 'improvement' => $improvement],
        ];
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = 'ready';
        $meta['analyzed_at'] = now()->toIso8601String();
        $document->metadata = $meta;
        $document->save();

        $score = $document->analysis['compliance_score'] ?? '—';
        $findingsCount = count($findings);

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'tender_reviewed',
            title: 'اكتملت مراجعة الكراسة',
            body: "نسبة الامتثال: {$score}% · {$findingsCount} ملاحظة — افتح الكراسة لعرض التقرير.",
            data: ['document_id' => $document->id]
        );
    }

    /**
     * Extract findings from AI response — handles multiple JSON shapes.
     * Accepts any of: findings/issues/suggestions/problems/observations/recommendations
     * as the array key. Also handles nested responses.
     */
    private function extractFindingsFromResponse(string $text): array
    {
        // Strip code fences
        $text = preg_replace('/```(?:json)?\s*/iu', '', $text) ?? $text;
        $text = preg_replace('/```/u', '', $text) ?? $text;

        if (! preg_match('/\{[\s\S]*\}/u', $text, $m)) return [];

        $decoded = json_decode($m[0], true);
        if (! is_array($decoded)) return [];

        // Try common keys
        $candidateKeys = ['findings', 'issues', 'suggestions', 'problems', 'observations', 'recommendations', 'notes', 'remarks'];
        foreach ($candidateKeys as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key]) && !empty($decoded[$key])) {
                return $this->normalizeFindings($decoded[$key]);
            }
        }

        // Sometimes the response wraps findings in a nested object
        foreach ($decoded as $value) {
            if (is_array($value)) {
                foreach ($candidateKeys as $key) {
                    if (isset($value[$key]) && is_array($value[$key]) && !empty($value[$key])) {
                        return $this->normalizeFindings($value[$key]);
                    }
                }
            }
        }

        // Single finding object (has issue/title directly)
        if (isset($decoded['issue_title']) || isset($decoded['issue']) || isset($decoded['title'])) {
            return $this->normalizeFindings([$decoded]);
        }

        // Array of finding-like objects at root
        if (array_is_list($decoded) && !empty($decoded) && is_array($decoded[0])) {
            return $this->normalizeFindings($decoded);
        }

        return [];
    }

    /** Normalize finding entries regardless of original key names. */
    private function normalizeFindings(array $rawFindings): array
    {
        return array_values(array_filter(array_map(function ($f) {
            if (! is_array($f)) return null;
            $title = $f['issue_title'] ?? $f['title'] ?? $f['issue'] ?? $f['name'] ?? $f['problem'] ?? '';
            $issue = $f['why_it_is_an_issue'] ?? $f['issue'] ?? $f['description'] ?? $f['problem'] ?? $f['reason'] ?? '';
            $severity = $f['severity'] ?? $f['priority'] ?? $f['level'] ?? 'Medium';
            $recommendation = $f['recommendation'] ?? $f['suggestion'] ?? $f['fix'] ?? $f['solution'] ?? '';
            if ($title === '' && $issue === '') return null;
            return [
                'issue_title'        => $title,
                'severity'           => $severity,
                'affected_section'   => $f['affected_section'] ?? $f['section'] ?? $f['category'] ?? '',
                'detected_text'      => $f['detected_text'] ?? $f['text'] ?? '',
                'why_it_is_an_issue' => $issue,
                'basis_type'         => $f['basis_type'] ?? 'law',
                'basis_reference'    => $f['basis_reference'] ?? $f['legal_reference'] ?? $f['reference'] ?? '',
                'violation_type'     => $f['violation_type'] ?? null,
                'recommendation'     => $recommendation,
                'suggested_rewrite'  => $f['suggested_rewrite'] ?? $f['suggested'] ?? '',
            ];
        }, $rawFindings)));
    }

    /** Map English severity to Arabic label used in views. */
    private function severityLabel(string $sevEn): string
    {
        return match ($sevEn) {
            'Critical'    => 'حرجة',
            'High'        => 'عالية',
            'Medium'      => 'متوسطة',
            'Improvement' => 'تحسينية',
            default       => 'متوسطة',
        };
    }

    /** Map basis_type to Arabic category used in views (نظامي/شكلي/موضوعي/عدالة). */
    private function categoryLabel(string $basisType): string
    {
        return match (mb_strtolower($basisType)) {
            'law', 'regulation', 'نظامي'      => 'نظامي',
            'template', 'procedure', 'شكلي'    => 'شكلي',
            'guide', 'recommendation', 'policy', 'موضوعي' => 'موضوعي',
            'fairness', 'عدالة'                => 'عدالة',
            default                             => 'موضوعي',
        };
    }

    private function normalizeSeverity(string $sev): string
    {
        $map = [
            'critical'    => 'Critical',
            'high'        => 'High',
            'medium'      => 'Medium',
            'low'         => 'Improvement',
            'improvement' => 'Improvement',
            'حرجة'        => 'Critical',
            'حرج'         => 'Critical',
            'عالية'       => 'High',
            'عالي'        => 'High',
            'متوسطة'      => 'Medium',
            'متوسط'       => 'Medium',
            'تحسينية'     => 'Improvement',
            'تحسيني'      => 'Improvement',
            'منخفضة'      => 'Improvement',
        ];
        $key = mb_strtolower(trim($sev));
        return $map[$key] ?? $map[trim($sev)] ?? 'Medium';
    }

    private function markStatus(LegalDocument $document, string $status, string $reason): void
    {
        $meta = $document->metadata ?? [];
        $meta['analysis_status'] = $status;
        $meta['analysis_error'] = $reason;
        $document->metadata = $meta;
        $document->save();

        AppNotification::notify(
            userId: $document->uploaded_by,
            type: 'tender_review_failed',
            title: 'فشل مراجعة الكراسة',
            body: $reason,
            data: ['document_id' => $document->id]
        );
    }
}
