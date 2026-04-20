<?php

namespace Modules\Annotations\Actions;

use App\Models\Annotation;
use App\Models\LegalDocument;
use App\Models\User;

class CreateAnnotationAction
{
    /**
     * @param  array{selected_text:string, comment:?string, color:string, visibility:?string}  $data
     */
    public function execute(LegalDocument $document, User $user, array $data): Annotation
    {
        return Annotation::create([
            'document_id'   => $document->id,
            'user_id'       => $user->id,
            'selected_text' => $data['selected_text'],
            'comment'       => $data['comment'] ?? null,
            'color'         => $data['color'],
            'visibility'    => $data['visibility'] ?? 'org',
        ]);
    }
}
