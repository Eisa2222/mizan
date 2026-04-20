<?php

namespace Modules\Versions\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Modules\Versions\Actions\StoreDocumentVersionAction;
use Modules\Versions\Http\Requests\StoreVersionRequest;
use RuntimeException;

class VersionController extends Controller
{
    public function store(StoreVersionRequest $request, LegalDocument $document, StoreDocumentVersionAction $action): RedirectResponse
    {
        try {
            $action->execute($document, $request->user(), $request->file('file'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return redirect()
            ->route('documents.show', $document)
            ->with('success', __('versions.flash.uploaded'));
    }
}
