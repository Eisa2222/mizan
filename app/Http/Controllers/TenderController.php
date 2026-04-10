<?php

namespace App\Http\Controllers;

use App\Models\Tender;
use App\Services\ComplianceService;
use App\Services\TenderBuilderService;
use App\Services\TenderExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * TenderController
 * ────────────────
 * The full lifecycle for the smart tender generator:
 *
 *   GET  /tenders                — list
 *   GET  /tenders/create         — wizard (5 steps)
 *   POST /tenders                — store + generate
 *   GET  /tenders/{tender}       — show (editable doc + sidebar + AI)
 *   PATCH /tenders/{tender}/sections/{section}  — update one section
 *   POST /tenders/{tender}/regenerate           — re-run generator
 *   POST /tenders/{tender}/review               — run compliance checker
 *   GET  /tenders/{tender}/export/pdf           — download PDF
 *   GET  /tenders/{tender}/export/docx          — download Word
 *   DELETE /tenders/{tender}     — delete
 */
class TenderController extends Controller
{
    public function __construct(
        private TenderBuilderService $builder,
        private ComplianceService $compliance,
        private TenderExportService $exporter,
    ) {}

    public function index(Request $request)
    {
        $tenders = Tender::where('org_id', $request->user()->org_id)
            ->with(['creator', 'review'])
            ->latest()
            ->paginate(15);

        return view('tenders.index', compact('tenders'));
    }

    public function create()
    {
        return view('tenders.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            'scope_input'          => 'required|string|min:10|max:10000',
            'type'                 => 'required|in:it,construction,consulting,operations,legal',
            'duration'             => 'nullable|string|max:100',
            'deliverables'         => 'nullable|array',
            'deliverables.*'       => 'string|max:500',
            'evaluation_criteria'  => 'nullable|array',
            'special_conditions'   => 'nullable|array',
            'special_conditions.*' => 'string|max:500',
        ]);

        $tender = Tender::create([
            ...$data,
            'org_id'     => $request->user()->org_id,
            'created_by' => $request->user()->id,
            'status'     => 'draft',
        ]);

        // Build sections + clauses immediately (synchronous)
        $tender = $this->builder->build($tender);

        return redirect()->route('tenders.show', $tender)
            ->with('success', 'تم توليد الكراسة بنجاح. راجع الأقسام وعدّلها حسب الحاجة.');
    }

    public function show(Request $request, Tender $tender)
    {
        $this->ensureSameOrg($request, $tender);
        $tender->load(['sections', 'clauses', 'review', 'creator']);

        return view('tenders.show', compact('tender'));
    }

    public function updateSection(Request $request, Tender $tender, int $sectionId)
    {
        $this->ensureSameOrg($request, $tender);
        $data = $request->validate(['content' => 'required|string']);

        $section = $tender->sections()->findOrFail($sectionId);
        $section->update([
            'content'   => $data['content'],
            'is_edited' => true,
        ]);

        return response()->json(['ok' => true]);
    }

    public function regenerate(Request $request, Tender $tender): RedirectResponse
    {
        $this->ensureSameOrg($request, $tender);
        $this->builder->build($tender);
        return back()->with('success', 'تم إعادة توليد الكراسة بنجاح.');
    }

    public function review(Request $request, Tender $tender): RedirectResponse
    {
        $this->ensureSameOrg($request, $tender);
        $this->compliance->check($tender);
        return back()->with('success', 'اكتملت مراجعة الامتثال.');
    }

    public function exportPdf(Request $request, Tender $tender)
    {
        $this->ensureSameOrg($request, $tender);
        try {
            $path = $this->exporter->exportPdf($tender);
            return response()->download(storage_path('app/public/' . $path),
                $tender->title . '.pdf');
        } catch (\Throwable $e) {
            return back()->withErrors(['export' => $e->getMessage()]);
        }
    }

    public function exportDocx(Request $request, Tender $tender)
    {
        $this->ensureSameOrg($request, $tender);
        $path = $this->exporter->exportDocx($tender);
        return response()->download(storage_path('app/public/' . $path),
            $tender->title . '.docx');
    }

    public function destroy(Request $request, Tender $tender): RedirectResponse
    {
        $this->ensureSameOrg($request, $tender);
        $tender->delete();
        return redirect()->route('tenders.index')->with('success', 'تم حذف الكراسة.');
    }

    private function ensureSameOrg(Request $request, Tender $tender): void
    {
        abort_if($tender->org_id !== $request->user()->org_id, 403);
    }
}
