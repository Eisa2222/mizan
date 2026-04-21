<?php

namespace Modules\Discussions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Discussion;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Modules\Discussions\Actions\CreateDiscussionAction;
use Modules\Discussions\Actions\ReplyToDiscussionAction;
use Modules\Discussions\Http\Requests\ReplyDiscussionRequest;
use Modules\Discussions\Http\Requests\StoreDiscussionRequest;

class DiscussionController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreDiscussionRequest $request, LegalDocument $document, CreateDiscussionAction $action): RedirectResponse
    {
        $this->authorize('create', Discussion::class);
        $this->authorize('view', $document);

        $action->execute($document, $request->user(), $request->validated());

        return back()->with('success', __('discussions.flash.created'));
    }

    public function show(Discussion $discussion): View
    {
        $this->authorize('view', $discussion);

        $discussion->load(['user', 'document', 'replies.user']);

        return view('discussions.show', compact('discussion'));
    }

    public function reply(ReplyDiscussionRequest $request, Discussion $discussion, ReplyToDiscussionAction $action): RedirectResponse
    {
        $this->authorize('reply', $discussion);

        $action->execute($discussion, $request->user(), $request->string('body'));

        return back()->with('success', __('discussions.flash.replied'));
    }

    public function destroy(Discussion $discussion): RedirectResponse
    {
        $this->authorize('delete', $discussion);

        $discussion->delete();

        return back()->with('success', __('discussions.flash.deleted'));
    }
}
