<?php

namespace Modules\Documents\Queries;

use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class AutocompleteDocumentsQuery
{
    public function run(User $user, string $term, int $excludeId = 0, int $limit = 10): Collection
    {
        if (mb_strlen($term) < 1) {
            return new Collection();
        }

        $like = "%{$term}%";

        return LegalDocument::query()
            ->where('org_id', $user->org_id)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($like) {
                $q->where('title', 'like', $like)
                    ->orWhere('title_en', 'like', $like)
                    ->orWhere('reference_number', 'like', $like);
            })
            ->orderBy('title')
            ->limit($limit)
            ->get(['id', 'title', 'title_en', 'type', 'reference_number']);
    }
}
