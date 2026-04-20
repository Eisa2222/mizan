<?php

namespace Modules\TenderReviews\Queries;

use App\Models\Annotation;
use App\Models\Discussion;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Collection;

class TenderReviewShowContextQuery
{
    /**
     * @return array{annotations:Collection, discussions:Collection}
     */
    public function run(LegalDocument $document, User $user): array
    {
        return [
            'annotations' => Annotation::query()
                ->with('user')
                ->where('document_id', $document->id)
                ->visibleTo($user)
                ->latest()
                ->get(),

            'discussions' => Discussion::query()
                ->with(['user', 'replies'])
                ->where('document_id', $document->id)
                ->visibleTo($user)
                ->latest()
                ->get(),
        ];
    }
}
