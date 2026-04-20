<?php

namespace Modules\ArticleUpdates\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Modules\ArticleUpdates\Actions\CreateArticleUpdateAction;
use Modules\ArticleUpdates\Http\Requests\StoreArticleUpdateRequest;
use Modules\ArticleUpdates\Http\Resources\ArticleUpdateResource;
use Modules\ArticleUpdates\Queries\ArticleUpdatesForDocumentQuery;

class ArticleUpdateController extends Controller
{
    use AuthorizesRequests;

    public function index(LegalDocument $document, ArticleUpdatesForDocumentQuery $query): JsonResponse
    {
        $this->authorize('view', $document);

        return response()->json([
            'updates' => ArticleUpdateResource::collection($query->run($document)),
        ]);
    }

    public function store(StoreArticleUpdateRequest $request, LegalDocument $document, CreateArticleUpdateAction $action): RedirectResponse
    {
        $action->execute($document, $request->user(), $request->validated());

        return redirect()
            ->route('documents.show', $document)
            ->with('success', __('article-updates.flash.created'));
    }

    public function destroy(ArticleUpdate $articleUpdate): RedirectResponse
    {
        $this->authorize('delete', $articleUpdate);

        $documentId = $articleUpdate->document_id;
        $articleUpdate->delete();

        return redirect()
            ->route('documents.show', $documentId)
            ->with('success', __('article-updates.flash.deleted'));
    }
}
