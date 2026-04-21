<?php

namespace Modules\Assistant\Actions;

use App\Models\AiConversation;
use App\Services\TasbibatKnowledgeService;
use App\Services\TrainingCorpusService;
use Modules\Assistant\Queries\TopDocumentChunksForQueryQuery;

/**
 * Builds the system prompt (plus the list of chunk ids used) for a
 * conversation turn. For doc-bound conversations it inlines the full
 * content when small, otherwise picks top-K chunks. For general chat
 * it injects the Tasbibat knowledge snippets.
 */
class BuildAssistantContextAction
{
    /** Max characters of full content to inline before falling back to chunk RAG. */
    public const FULL_DOC_INLINE_LIMIT = 50000;

    public const RAG_TOP_K = 6;

    private const ARABIC_RULE = 'تعليمات صارمة: يجب أن تكتب إجابتك بالكامل باللغة العربية الفصحى. لا تستخدم اللغة الإنجليزية أبداً. لا تترجم المصطلحات للإنجليزية. كل كلمة في إجابتك يجب أن تكون عربية.';

    public function __construct(
        private readonly TasbibatKnowledgeService $tasbibat,
        private readonly TopDocumentChunksForQueryQuery $chunksQuery,
        private readonly TrainingCorpusService $corpus,
    ) {
    }

    /**
     * @return array{0:string, 1:array<int>}  [system prompt, used chunk ids]
     */
    public function execute(AiConversation $conversation, string $userQuery): array
    {
        if (! $conversation->document_id) {
            return $this->generalChatContext($userQuery);
        }

        $document = $conversation->document;
        if ($document === null) {
            return ['أنت مساعد قانوني محترف. ' . self::ARABIC_RULE, []];
        }

        $base = "أنت مساعد قانوني متخصص يعمل على وثيقة قانونية سعودية بعنوان \"{$document->title}\" "
            . "(النوع: {$document->type_label}). أجب على أسئلة المستخدم بناءً فقط على المحتوى المرفق أدناه. "
            . "إذا لم تجد إجابة في الوثيقة، قل ذلك صراحة. اقتبس رقم المادة المرجعية عند الإمكان.\n"
            . self::ARABIC_RULE . "\n\n";

        $contentLen = mb_strlen((string) $document->content);

        if ($contentLen > 0 && $contentLen <= self::FULL_DOC_INLINE_LIMIT) {
            return [
                $base . "═══ نص الوثيقة الكامل ═══\n" . $document->content,
                [],
            ];
        }

        $chunks = $this->chunksQuery->run($document->id, $userQuery, self::RAG_TOP_K);

        if ($chunks->isEmpty()) {
            return [
                $base . '(لم يتم تحميل أي مقاطع من الوثيقة كسياق. تصرّف وفقاً لذلك وأخبر المستخدم بأن الوثيقة فارغة أو لم يُعثر على مقاطع مطابقة.)',
                [],
            ];
        }

        $context = "═══ مقاطع ذات صلة من الوثيقة ═══\n\n";
        foreach ($chunks as $chunk) {
            $label = $chunk->label ? "[{$chunk->label}] " : '';
            $context .= $label . $chunk->content . "\n\n---\n\n";
        }

        return [$base . $context, $chunks->pluck('id')->all()];
    }

    /**
     * @return array{0:string, 1:array<int>}
     */
    private function generalChatContext(string $userQuery): array
    {
        $tasbibatCtx = $this->tasbibat->buildContextFor($userQuery, topK: 3);
        $corpusCtx   = $this->corpus->buildContextFor(
            $userQuery,
            kinds: ['document', 'contract', 'memo', 'case', 'tender_review'],
            topK: 3,
        );

        $system = 'أنت مساعد قانوني محترف متخصص في الأنظمة السعودية. ' . self::ARABIC_RULE;
        if ($tasbibatCtx !== '') {
            $system .= "\n\n" . $tasbibatCtx;
        }
        if ($corpusCtx !== '') {
            $system .= "\n\n" . $corpusCtx;
        }

        return [$system, []];
    }
}
