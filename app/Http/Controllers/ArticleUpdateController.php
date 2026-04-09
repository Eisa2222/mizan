<?php

namespace App\Http\Controllers;

use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Manual CRUD for ArticleUpdate rows attached to a document.
 *
 * Auto-generated updates (auto_generated=true) come from
 * DiffDocumentVersionJob and use this same model — but only
 * the *manual* path goes through this controller.
 */
class ArticleUpdateController extends Controller
{
    use AuthorizesRequests;

    public function index(LegalDocument $document): JsonResponse
    {
        $this->authorize('view', $document);

        $updates = $document->articleUpdates()
            ->with(['creator:id,name', 'sourceDocument:id,title'])
            ->get()
            ->map(fn (ArticleUpdate $u) => [
                'id'             => $u->id,
                'article_label'  => $u->article_label,
                'update_date'    => $u->update_date->format('Y-m-d'),
                'decree_number'  => $u->decree_number,
                'decree_url'     => $u->decree_url,
                'body'           => $u->body,
                'auto_generated' => (bool) $u->auto_generated,
                'creator'        => $u->creator?->name,
                'source_document' => $u->sourceDocument
                    ? ['id' => $u->sourceDocument->id, 'title' => $u->sourceDocument->title]
                    : null,
            ]);

        return response()->json(['updates' => $updates]);
    }

    public function store(Request $request, LegalDocument $document): RedirectResponse
    {
        $this->authorize('createForDocument', [ArticleUpdate::class, $document]);

        $data = $request->validate([
            'article_label'      => 'required|string|max:100',
            'update_date'        => 'required|date',
            'decree_number'      => 'nullable|string|max:100',
            'decree_url'         => 'nullable|url|max:500',
            'body'               => 'required|string|max:10000',
            'source_document_id' => 'nullable|integer|exists:legal_documents,id',
        ]);

        // If a source document is provided, ensure it's in the same org so a
        // user can't link to a document they have no business referencing.
        if (! empty($data['source_document_id'])) {
            $source = LegalDocument::find($data['source_document_id']);
            if (! $source || $source->org_id !== $document->org_id) {
                return back()->withErrors([
                    'source_document_id' => 'الوثيقة المصدر يجب أن تكون ضمن نفس المؤسسة.',
                ])->withInput();
            }
        }

        ArticleUpdate::create([
            ...$data,
            'document_id'    => $document->id,
            'auto_generated' => false,
            'created_by'     => $request->user()->id,
        ]);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'تمت إضافة التحديث بنجاح.');
    }

    public function destroy(ArticleUpdate $articleUpdate): RedirectResponse
    {
        $this->authorize('delete', $articleUpdate);
        $documentId = $articleUpdate->document_id;
        $articleUpdate->delete();

        return redirect()
            ->route('documents.show', $documentId)
            ->with('success', 'تم حذف التحديث.');
    }
}
