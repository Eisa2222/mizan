<?php

namespace Modules\Tenders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tender;
use App\Services\ComplianceService;
use App\Services\TenderExportService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Tenders\Actions\ApproveTenderAction;
use Modules\Tenders\Actions\CreateTenderAction;
use Modules\Tenders\Actions\RegenerateTenderAction;
use Modules\Tenders\Actions\RejectTenderAction;
use Modules\Tenders\Actions\SubmitTenderForApprovalAction;
use Modules\Tenders\Actions\UpdateTenderSectionAction;
use Modules\Tenders\Http\Requests\RejectTenderRequest;
use Modules\Tenders\Http\Requests\StoreTenderRequest;
use Modules\Tenders\Http\Requests\UpdateTenderSectionRequest;
use Modules\Tenders\Queries\TendersForOrgQuery;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class TenderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, TendersForOrgQuery $query): View
    {
        $this->authorize('viewAny', Tender::class);

        return view('tenders.index', [
            'tenders' => $query->paginate($request->user()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Tender::class);

        return view('tenders.create');
    }

    public function store(StoreTenderRequest $request, CreateTenderAction $action): RedirectResponse
    {
        $tender = $action->execute($request->user(), $request->validated());

        return redirect()
            ->route('tenders.show', $tender)
            ->with('success', __('tenders.flash.created'));
    }

    public function show(Tender $tender): View
    {
        $this->authorize('view', $tender);

        $tender->load(['sections', 'clauses', 'review', 'creator']);

        return view('tenders.show', compact('tender'));
    }

    public function updateSection(UpdateTenderSectionRequest $request, Tender $tender, int $sectionId, UpdateTenderSectionAction $action)
    {
        $this->authorize('update', $tender);

        $action->execute($tender, $sectionId, $request->string('content'));

        return response()->json(['ok' => true]);
    }

    public function regenerate(Tender $tender, RegenerateTenderAction $action): RedirectResponse
    {
        $this->authorize('update', $tender);

        $action->execute($tender);

        return back()->with('success', __('tenders.flash.regenerated'));
    }

    public function review(Tender $tender, ComplianceService $compliance): RedirectResponse
    {
        $this->authorize('update', $tender);

        $compliance->check($tender);

        return back()->with('success', __('tenders.flash.reviewed'));
    }

    public function exportPdf(Tender $tender, TenderExportService $exporter): BinaryFileResponse|RedirectResponse
    {
        $this->authorize('export', $tender);

        try {
            $path = $exporter->exportPdf($tender);
        } catch (Throwable $e) {
            return back()->withErrors(['export' => $e->getMessage()]);
        }

        return response()->download(storage_path('app/public/' . $path), $tender->title . '.pdf');
    }

    public function exportDocx(Tender $tender, TenderExportService $exporter): BinaryFileResponse
    {
        $this->authorize('export', $tender);

        $path = $exporter->exportDocx($tender);

        return response()->download(storage_path('app/public/' . $path), $tender->title . '.docx');
    }

    public function submit(Request $request, Tender $tender, SubmitTenderForApprovalAction $action): RedirectResponse
    {
        $this->authorize('submit', $tender);

        $action->execute($tender, $request->user());

        return back()->with('success', __('tenders.flash.submitted'));
    }

    public function approve(Request $request, Tender $tender, ApproveTenderAction $action): RedirectResponse
    {
        $this->authorize('approve', $tender);

        $action->execute($tender, $request->user());

        return back()->with('success', __('tenders.flash.approved'));
    }

    public function reject(RejectTenderRequest $request, Tender $tender, RejectTenderAction $action): RedirectResponse
    {
        $this->authorize('reject', $tender);

        $action->execute($tender, $request->user(), $request->string('rejection_reason'));

        return back()->with('success', __('tenders.flash.rejected'));
    }

    public function destroy(Tender $tender): RedirectResponse
    {
        $this->authorize('delete', $tender);

        $tender->delete();

        return redirect()
            ->route('tenders.index')
            ->with('success', __('tenders.flash.deleted'));
    }
}
