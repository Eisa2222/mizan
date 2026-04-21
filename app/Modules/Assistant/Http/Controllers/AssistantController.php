<?php

namespace Modules\Assistant\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\LegalDocument;
use App\Services\ClaudeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Assistant\Actions\SendAssistantMessageAction;
use Modules\Assistant\Actions\StartAssistantConversationAction;
use Modules\Assistant\Http\Requests\SendMessageRequest;
use Modules\Assistant\Http\Requests\StartConversationRequest;
use RuntimeException;

class AssistantController extends Controller
{
    use AuthorizesRequests;

    public function __construct(private readonly ClaudeService $claude)
    {
    }

    public function start(StartConversationRequest $request, StartAssistantConversationAction $action): JsonResponse
    {
        if (! $this->claude->isConfigured()) {
            return $this->aiUnavailable();
        }

        $documentId = $request->input('document_id');

        if ($documentId !== null) {
            $document = LegalDocument::findOrFail($documentId);
            $this->authorize('view', $document);
        }

        $conversation = $action->execute(
            user:       $request->user(),
            documentId: $documentId !== null ? (int) $documentId : null,
            title:      $request->input('title'),
        );

        return response()->json([
            'conversation' => [
                'id'          => $conversation->id,
                'document_id' => $conversation->document_id,
                'title'       => $conversation->title,
                'messages'    => [],
            ],
        ]);
    }

    public function show(Request $request, AiConversation $conversation): JsonResponse
    {
        abort_unless($conversation->user_id === $request->user()->id, 403);

        return response()->json([
            'conversation' => [
                'id'          => $conversation->id,
                'document_id' => $conversation->document_id,
                'title'       => $conversation->title,
                'messages'    => $conversation->messages()->get(['id', 'role', 'content', 'created_at']),
            ],
        ]);
    }

    public function sendMessage(SendMessageRequest $request, AiConversation $conversation, SendAssistantMessageAction $action): JsonResponse
    {
        if (! $this->claude->isConfigured()) {
            return $this->aiUnavailable();
        }

        try {
            $result = $action->execute($conversation, $request->string('content'));
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }

        return response()->json([
            'user_message'      => $this->serializeMessage($result['user']),
            'assistant_message' => $this->serializeMessage($result['assistant']),
        ]);
    }

    private function serializeMessage(AiMessage $message): array
    {
        return [
            'id'         => $message->id,
            'role'       => $message->role,
            'content'    => $message->content,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }

    private function aiUnavailable(): JsonResponse
    {
        return response()->json([
            'error' => __('assistant.errors.unavailable'),
        ], 503);
    }
}
