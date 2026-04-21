<?php

namespace Modules\Annotations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Annotation;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Modules\Annotations\Actions\CreateAnnotationAction;
use Modules\Annotations\Http\Requests\StoreAnnotationRequest;

class AnnotationController extends Controller
{
    use AuthorizesRequests;

    public function store(StoreAnnotationRequest $request, LegalDocument $document, CreateAnnotationAction $action): RedirectResponse
    {
        $this->authorize('create', Annotation::class);
        $this->authorize('view', $document);

        $action->execute($document, $request->user(), $request->validated());

        return back()->with('success', __('annotations.flash.created'));
    }

    public function destroy(Annotation $annotation): RedirectResponse
    {
        $this->authorize('delete', $annotation);

        $annotation->delete();

        return back()->with('success', __('annotations.flash.deleted'));
    }
}
