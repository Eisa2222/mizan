<?php

namespace Modules\Relations\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\DocumentRelation;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Relations\Actions\CreateDocumentRelationAction;
use Modules\Relations\Http\Requests\StoreRelationRequest;

class RelationController extends Controller
{
    public function store(StoreRelationRequest $request, LegalDocument $document, CreateDocumentRelationAction $action): RedirectResponse
    {
        $action->execute($document, $request->user(), $request->validated());

        return redirect()
            ->route('documents.show', $document)
            ->with('success', __('relations.flash.created'));
    }

    public function destroy(Request $request, DocumentRelation $relation): RedirectResponse
    {
        $user = $request->user();
        $sourceDoc = $relation->fromDocument;

        $isCreator = $relation->created_by === $user->id;
        $isOrgAdmin = $user->hasAtLeastRole(UserRole::OrgAdmin)
            && $sourceDoc !== null
            && $sourceDoc->org_id === $user->org_id;

        abort_unless($isCreator || $isOrgAdmin, 403, __('relations.errors.delete_denied'));

        $documentId = $relation->from_document_id;
        $relation->delete();

        return redirect()
            ->route('documents.show', $documentId)
            ->with('success', __('relations.flash.deleted'));
    }
}
