<?php

namespace Modules\Watchlist\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Watchlist\Actions\ToggleWatchlistAction;
use Modules\Watchlist\Queries\WatchlistForUserQuery;

class WatchlistController extends Controller
{
    public function index(Request $request, WatchlistForUserQuery $query): View
    {
        return view('watchlist.index', [
            'items' => $query->paginate($request->user()),
        ]);
    }

    public function toggle(Request $request, LegalDocument $document, ToggleWatchlistAction $action): RedirectResponse
    {
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $isWatching = $action->execute($request->user(), $document);

        return back()->with('success', $isWatching
            ? __('watchlist.flash.started')
            : __('watchlist.flash.stopped'));
    }
}
