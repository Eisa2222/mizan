<?php

namespace Modules\TenderReviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\TenderReviews\Actions\StoreTenderReviewAction;
use Modules\TenderReviews\Http\Requests\StoreTenderReviewRequest;
use Modules\TenderReviews\Queries\TenderReviewShowContextQuery;
use Modules\TenderReviews\Queries\TenderReviewsForOrgQuery;

class TenderReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TenderReviewsForOrgQuery $query): View
    {
        return view('tender-reviews.index', [
            'docs' => $query->paginate($request->user()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LegalDocument::class);

        return view('tender-reviews.create');
    }

    public function store(StoreTenderReviewRequest $request, StoreTenderReviewAction $action): RedirectResponse
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $document = $action->execute($request->user(), $request->validated(), $request->file('file'));

        return redirect()
            ->route('tender-reviews.show', $document)
            ->with('success', __('tender-reviews.flash.uploaded'));
    }

    public function show(Request $request, LegalDocument $document, TenderReviewShowContextQuery $contextQuery): View
    {
        abort_if($document->kind !== LegalDocument::KIND_TENDER_REVIEW, 404);
        $this->authorize('view', $document);

        $document->load(['uploader']);

        $context = $contextQuery->run($document, $request->user());

        return view('tender-reviews.show', [
            'document'    => $document,
            'annotations' => $context['annotations'],
            'discussions' => $context['discussions'],
        ]);
    }
}
