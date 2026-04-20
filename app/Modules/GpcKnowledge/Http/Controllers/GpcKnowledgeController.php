<?php

namespace Modules\GpcKnowledge\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\GpcArticle;
use Illuminate\View\View;
use Modules\GpcKnowledge\Http\Requests\IndexGpcKnowledgeRequest;
use Modules\GpcKnowledge\Queries\GpcArticlesQuery;

class GpcKnowledgeController extends Controller
{
    public function index(IndexGpcKnowledgeRequest $request, GpcArticlesQuery $query): View
    {
        return view('gpc-knowledge.index', [
            'articles'       => $query->paginate($request->source(), $request->term()),
            'sources'        => GpcArticle::SOURCES,
            'totalsBySource' => $query->totalsBySource(),
        ]);
    }
}
