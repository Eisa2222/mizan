<?php

namespace Modules\GpcKnowledge\Queries;

use App\Models\GpcArticle;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class GpcArticlesQuery
{
    public function paginate(?string $source, ?string $term, int $perPage = 20): LengthAwarePaginator
    {
        return GpcArticle::query()
            ->when($source, fn (Builder $q, string $s) => $q->where('source', $s))
            ->when($term, fn (Builder $q, string $t) => $this->applySearch($q, $t))
            ->orderBy('source')
            ->orderByRaw('CAST(article_number AS INTEGER)')
            ->paginate($perPage);
    }

    public function totalsBySource(): array
    {
        return GpcArticle::query()
            ->selectRaw('source, COUNT(*) as cnt')
            ->groupBy('source')
            ->pluck('cnt', 'source')
            ->toArray();
    }

    private function applySearch(Builder $query, string $term): void
    {
        $like = "%{$term}%";

        $query->where(function (Builder $scope) use ($like) {
            $scope->where('content', 'like', $like)
                ->orWhere('topic', 'like', $like)
                ->orWhere('article_label', 'like', $like);
        });
    }
}
