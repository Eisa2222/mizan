<?php

namespace Modules\Memos\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Memos\Actions\StoreMemoAction;
use Modules\Memos\Http\Requests\StoreMemoRequest;
use Modules\Memos\Queries\MemoShowContextQuery;
use Modules\Memos\Queries\MemosForOrgQuery;

class MemoController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, MemosForOrgQuery $query): View
    {
        $docs = $query->paginate($request->user());

        return view('memos.index', compact('docs'));
    }

    public function show(Request $request, LegalDocument $document, MemoShowContextQuery $contextQuery): View
    {
        abort_if($document->kind !== LegalDocument::KIND_MEMO, 404);
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

        return view('memos.show', [
            'document'     => $document,
            'annotations'  => $context['annotations'],
            'discussions'  => $context['discussions'],
            'aiConfigured' => $context['ai_configured'],
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', LegalDocument::class);

        return view('memos.create');
    }

    public function store(StoreMemoRequest $request, StoreMemoAction $action): RedirectResponse
    {
        // AI drafting runs synchronously (60-120s); extend PHP limits.
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $document = $action->execute(
            user:    $request->user(),
            title:   $request->string('title'),
            content: $request->input('content'),
            file:    $request->file('file'),
        );

        return redirect()
            ->route('memos.show', $document)
            ->with('success', __('memos.flash.uploaded'));
    }
}
