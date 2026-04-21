<?php

namespace Modules\Relations\Actions;

use App\Models\DocumentRelation;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Creates a relation between two documents, enforcing:
 *   • no self-links
 *   • both endpoints in the same organization
 *   • caller has `view` permission on the target (prevents leaking private docs)
 *   • idempotent on (from, to, type)
 */
class CreateDocumentRelationAction
{
    /**
     * @param  array{to_document_id:int, relation_type:string, note:?string}  $data
     */
    public function execute(LegalDocument $from, User $user, array $data): DocumentRelation
    {
        if ((int) $data['to_document_id'] === $from->id) {
            throw ValidationException::withMessages([
                'to_document_id' => __('relations.errors.self_link'),
            ]);
        }

        $target = LegalDocument::find($data['to_document_id']);

        if ($target === null || ! Gate::forUser($user)->allows('view', $target)) {
            throw ValidationException::withMessages([
                'to_document_id' => __('relations.errors.target_inaccessible'),
            ]);
        }

        if ($target->org_id !== $from->org_id) {
            throw ValidationException::withMessages([
                'to_document_id' => __('relations.errors.cross_org'),
            ]);
        }

        return DocumentRelation::firstOrCreate(
            [
                'from_document_id' => $from->id,
                'to_document_id'   => $target->id,
                'relation_type'    => $data['relation_type'],
            ],
            [
                'note'       => $data['note'] ?? null,
                'created_by' => $user->id,
            ],
        );
    }
}
