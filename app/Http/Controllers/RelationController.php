<?php

namespace App\Http\Controllers;

use App\Models\DocumentRelation;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CRUD for inter-document relations (نظام ↔ لائحة، مادة ↔ حكم، إلخ).
 *
 * Authorization rules:
 *   • store: caller must be able to view BOTH endpoints (LegalDocumentPolicy::view).
 *     This prevents leaking the existence of private documents via the relations
 *     graph.
 *   • destroy: only the relation creator OR an org-admin+ in the same org as
 *     the source document.
 */
class RelationController extends Controller
{
    use AuthorizesRequests;

    public function store(Request $request, LegalDocument $document): RedirectResponse
    {
        $this->authorize('view', $document);

        $data = $request->validate([
            'to_document_id' => 'required|integer|exists:legal_documents,id',
            'relation_type'  => ['required', 'string', Rule::in(array_keys(LegalDocument::RELATION_TYPES))],
            'note'           => 'nullable|string|max:500',
        ]);

        // Self-link guard: Laravel's `different` rule compares two request
        // fields, not a field to a literal — so we check explicitly here.
        if ((int) $data['to_document_id'] === $document->id) {
            return back()->withErrors([
                'to_document_id' => 'لا يمكن ربط المستند بنفسه.',
            ])->withInput();
        }

        $target = LegalDocument::find($data['to_document_id']);
        $this->authorize('view', $target);

        // Same-org guarantee: relations cannot bridge organizations.
        if ($target->org_id !== $document->org_id) {
            return back()->withErrors([
                'to_document_id' => 'لا يمكن ربط مستندات من مؤسسات مختلفة.',
            ])->withInput();
        }

        // Idempotent: don't create duplicate (from, to, type) triples.
        DocumentRelation::firstOrCreate(
            [
                'from_document_id' => $document->id,
                'to_document_id'   => $target->id,
                'relation_type'    => $data['relation_type'],
            ],
            [
                'note'       => $data['note'] ?? null,
                'created_by' => $request->user()->id,
            ]
        );

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تم ربط المستند بنجاح.');
    }

    public function destroy(Request $request, DocumentRelation $relation): RedirectResponse
    {
        // Authorization: creator or org-admin+ in the source doc's org
        $user = $request->user();
        $sourceDoc = $relation->fromDocument;

        $isCreator = $relation->created_by === $user->id;
        $isOrgAdmin = $user->hasAtLeastRole(\App\Enums\UserRole::OrgAdmin)
            && $sourceDoc
            && $sourceDoc->org_id === $user->org_id;

        abort_unless($isCreator || $isOrgAdmin, 403, 'لا تملك صلاحية حذف هذه العلاقة.');

        $documentId = $relation->from_document_id;
        $relation->delete();

        return redirect()
            ->route('documents.show', $documentId)
            ->with('success', 'تم حذف الربط.');
    }
}
