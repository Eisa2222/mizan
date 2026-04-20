<?php

namespace Modules\Search\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Search\Actions\SearchDocumentsAction;
use Modules\Search\Http\Requests\SearchDocumentsRequest;

class SearchController extends Controller
{
    public function __invoke(SearchDocumentsRequest $request, SearchDocumentsAction $action): JsonResponse
    {
        return response()->json($action->execute(
            query:   $request->term(),
            filters: $request->filters(),
            page:    $request->page(),
            size:    $request->size(),
            useAi:   $request->useAi(),
        ));
    }
}
