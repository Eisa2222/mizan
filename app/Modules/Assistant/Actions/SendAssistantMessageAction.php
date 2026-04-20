<?php

namespace Modules\Assistant\Actions;

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Services\ClaudeService;

/**
 * Persists a user turn, calls Claude with the conversation's context,
 * then persists the assistant reply. Returns both messages so the
 * controller can surface them in a single JSON response.
 *
 * @return array{user:AiMessage, assistant:AiMessage}
 */
class SendAssistantMessageAction
{
    public function __construct(
        private readonly ClaudeService $claude,
        private readonly BuildAssistantContextAction $contextBuilder,
    ) {
    }

    public function execute(AiConversation $conversation, string $content): array
    {
        $userMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => AiMessage::ROLE_USER,
            'content'         => $content,
        ]);

        [$systemPrompt, $contextChunkIds] = $this->contextBuilder->execute($conversation, $content);

        $apiMessages = $conversation->messages()
            ->orderBy('id')
            ->get(['role', 'content'])
            ->map(fn ($message) => ['role' => $message->role, 'content' => $message->content])
            ->toArray();

        $result = $this->claude->chat(
            messages:  $apiMessages,
            system:    $systemPrompt,
            maxTokens: 2048,
        );

        $assistantMessage = AiMessage::create([
            'conversation_id' => $conversation->id,
            'role'            => AiMessage::ROLE_ASSISTANT,
            'content'         => $result['text'],
            'context'         => $contextChunkIds,
            'input_tokens'    => $result['input_tokens'],
            'output_tokens'   => $result['output_tokens'],
        ]);

        return ['user' => $userMessage, 'assistant' => $assistantMessage];
    }
}
