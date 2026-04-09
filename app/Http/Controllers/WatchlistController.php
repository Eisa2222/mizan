<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use App\Models\Watchlist;
use Illuminate\Http\Request;

class WatchlistController extends Controller
{
    public function index(Request $request)
    {
        $items = Watchlist::with('document')
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return view('watchlist.index', compact('items'));
    }

    public function toggle(Request $request, LegalDocument $document)
    {
        abort_if($document->org_id !== $request->user()->org_id, 403);

        $existing = Watchlist::where('user_id', $request->user()->id)
            ->where('document_id', $document->id)
            ->first();

        if ($existing) {
            $existing->delete();
            $msg = 'تم إلغاء المتابعة';
        } else {
            Watchlist::create([
                'user_id' => $request->user()->id,
                'document_id' => $document->id,
            ]);
            $msg = 'بدأت المتابعة';
        }

        return back()->with('success', $msg);
    }
}
