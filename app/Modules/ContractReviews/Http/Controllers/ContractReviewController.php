<?php

namespace Modules\ContractReviews\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\ContractReviews\Actions\StoreContractReviewAction;
use Modules\ContractReviews\Http\Requests\StoreContractReviewRequest;
use Modules\ContractReviews\Queries\ContractReviewShowContextQuery;
use Modules\ContractReviews\Queries\ContractReviewsForOrgQuery;

class ContractReviewController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, ContractReviewsForOrgQuery $query): View
    {
        $docs = $query->paginate($request->user());

        return view('contract-reviews.index', compact('docs'));
    }

    public function show(Request $request, LegalDocument $document, ContractReviewShowContextQuery $contextQuery): View
    {
        abort_if($document->kind !== LegalDocument::KIND_CONTRACT_REVIEW, 404);
        $this->authorize('view', $document);

        $document->load([
            'chunks',
            'articleUpdates.creator',
            'versions.uploader',
            'relationsFrom.toDocument',
            'relationsTo.fromDocument',
            'uploader',
        ]);

        $context = $contextQuery->run($document, $request->user());

        return view('contract-reviews.show', [
            'document'     => $document,
            'annotations'  => $context['annotations'],
            'discussions'  => $context['discussions'],
            'aiConfigured' => $context['ai_configured'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LegalDocument::class);

        return view('contract-reviews.create');
    }

    public function store(StoreContractReviewRequest $request, StoreContractReviewAction $action): RedirectResponse
    {
        // AI review takes 60-120s; override PHP timeout before the Action runs.
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $document = $action->execute(
            user:    $request->user(),
            title:   $request->string('title'),
            summary: $request->input('summary'),
            file:    $request->file('file'),
        );

        return redirect()
            ->route('contract-reviews.show', $document)
            ->with('success', __('contract-reviews.flash.uploaded'));
    }
}
