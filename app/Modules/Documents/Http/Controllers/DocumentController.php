<?php

namespace Modules\Documents\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Documents\Actions\DeleteDocumentAction;
use Modules\Documents\Actions\DispatchDocumentPipelineAction;
use Modules\Documents\Actions\StoreDocumentAction;
use Modules\Documents\Actions\UpdateDocumentContentAction;
use Modules\Documents\Http\Requests\AutocompleteDocumentsRequest;
use Modules\Documents\Http\Requests\IndexDocumentsRequest;
use Modules\Documents\Http\Requests\StoreDocumentRequest;
use Modules\Documents\Http\Requests\UpdateDocumentContentRequest;
use Modules\Documents\Http\Resources\DocumentAutocompleteResource;
use Modules\Documents\Queries\AutocompleteDocumentsQuery;
use Modules\Documents\Queries\DocumentShowContextQuery;
use Modules\Documents\Queries\DocumentsForUserQuery;

class DocumentController extends Controller
{
    use AuthorizesRequests;

    public function index(IndexDocumentsRequest $request, DocumentsForUserQuery $query): View
    {
        $documents = $query->paginate(
            user:    $request->user(),
            search:  $request->search(),
            type:    $request->typeFilter(),
        );

        return view('documents.index', compact('documents'));
    }

    public function create(): View
    {
        $this->authorize('create', LegalDocument::class);

        return view('documents.create');
    }

    public function store(
        StoreDocumentRequest $request,
        StoreDocumentAction $storeAction,
        DispatchDocumentPipelineAction $pipelineAction,
    ): RedirectResponse {
        $result = $storeAction->execute($request->toData());
        $flashKey = $pipelineAction->execute($result['document'], $result['needs_ocr']);

        if ($result['needs_ocr']) {
            return redirect()
                ->route('documents.show', $result['document'])
                ->with('success', __($flashKey));
        }

        return redirect()
            ->route('documents.index')
            ->with('success', __($flashKey));
    }

    public function show(LegalDocument $document, Request $request, DocumentShowContextQuery $contextQuery): View
    {
        $this->authorize('view', $document);

        $document->load([
            'chunks',
            'articleUpdates.creator',
            'articleUpdates.sourceDocument',
            'versions.uploader',
            'relationsFrom.toDocument',
            'relationsFrom.creator',
            'relationsTo.fromDocument',
            'relationsTo.creator',
            'uploader',
        ]);

        $context = $contextQuery->run($document, $request->user());

        return view('documents.show', [
            'document'    => $document,
            'isWatching'  => $context['is_watching'],
            'annotations' => $context['annotations'],
            'discussions' => $context['discussions'],
        ]);
    }

    public function destroy(LegalDocument $document, DeleteDocumentAction $action): RedirectResponse
    {
        $this->authorize('delete', $document);

        $action->execute($document);

        return redirect()
            ->route('documents.index')
            ->with('success', __('documents.flash.deleted'));
    }

    public function updateContent(
        UpdateDocumentContentRequest $request,
        LegalDocument $document,
        UpdateDocumentContentAction $action,
    ): RedirectResponse {
        $action->execute($document, $request->validated());

        return redirect()
            ->route('documents.show', $document)
            ->with('success', __('documents.flash.content_updated'));
    }

    public function autocomplete(AutocompleteDocumentsRequest $request, AutocompleteDocumentsQuery $query): JsonResponse
    {
        $hits = $query->run(
            user:      $request->user(),
            term:      $request->term(),
            excludeId: $request->excludeId(),
        );

        return response()->json([
            'results' => DocumentAutocompleteResource::collection($hits),
        ]);
    }
}
