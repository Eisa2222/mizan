<?php

namespace Modules\Documents\Queries;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lists regular documents (kind=document) visible to a given user.
 *
 * Visibility rules:
 *   • public documents (is_private=false)
 *   • documents uploaded by the user
 *   • documents inside folders the user owns or is a member of
 */
class DocumentsForUserQuery
{
    public function paginate(User $user, ?string $search, ?int $type, int $perPage = 15): LengthAwarePaginator
    {
        return $this->baseQuery($user)
            ->when($search, fn (Builder $q, string $term) => $this->applySearch($q, $term))
            ->when($type, fn (Builder $q, int $t) => $q->where('type', $t))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    private function baseQuery(User $user): Builder
    {
        return LegalDocument::query()
            ->where('org_id', $user->org_id)
            ->where('kind', LegalDocument::KIND_DOCUMENT)
            ->where(fn (Builder $q) => $this->applyVisibility($q, $user));
    }

    private function applyVisibility(Builder $query, User $user): void
    {
        $query->where('is_private', false)
            ->orWhere('uploaded_by', $user->id)
            ->orWhereExists(function ($sub) use ($user) {
                $sub->select(DB::raw(1))
                    ->from('folder_documents')
                    ->join('folders', 'folders.id', '=', 'folder_documents.folder_id')
                    ->leftJoin('folder_members', function ($j) use ($user) {
                        $j->on('folder_members.folder_id', '=', 'folder_documents.folder_id')
                            ->where('folder_members.user_id', '=', $user->id);
                    })
                    ->whereColumn('folder_documents.document_id', 'legal_documents.id')
                    ->where(function ($w) use ($user) {
                        $w->where('folders.owner_id', $user->id)
                            ->orWhereNotNull('folder_members.id');
                    });
            });
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = "%{$term}%";

        $query->where(function (Builder $q) use ($like) {
            $q->where('title', 'like', $like)
                ->orWhere('title_en', 'like', $like)
                ->orWhere('summary', 'like', $like)
                ->orWhere('content', 'like', $like);
        });
    }
}
