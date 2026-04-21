<?php

namespace Modules\Documents\Queries;

use App\Models\Annotation;
use App\Models\Discussion;
use App\Models\LegalDocument;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Support\Collection;

/**
 * Aggregates the per-user context the document-show page needs:
 *   • Whether the viewer is watching the document
 *   • Visible annotations (with their authors)
 *   • Visible discussions (with authors + replies)
 *
 * Keeps these reads out of the Blade view and eliminates 3 queries
 * that used to live inside show.blade.php.
 */
class DocumentShowContextQuery
{
    /**
     * @return array{is_watching:bool,annotations:Collection,discussions:Collection}
     */
    public function run(LegalDocument $document, User $user): array
    {
        return [
            'is_watching' => Watchlist::query()
                ->where('user_id', $user->id)
                ->where('document_id', $document->id)
                ->exists(),

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
