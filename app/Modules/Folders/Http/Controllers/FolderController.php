<?php

namespace Modules\Folders\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Folders\Actions\AddFolderMemberAction;
use Modules\Folders\Actions\CreateFolderAction;
use Modules\Folders\Actions\UploadFolderDocumentAction;
use Modules\Folders\Http\Requests\AddFolderMemberRequest;
use Modules\Folders\Http\Requests\StoreFolderRequest;
use Modules\Folders\Http\Requests\UploadFolderDocumentRequest;
use Modules\Folders\Queries\AccessibleRootFoldersQuery;
use Modules\Folders\Queries\FolderAvailableMembersQuery;

class FolderController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, AccessibleRootFoldersQuery $query): View
    {
        $this->authorize('viewAny', Folder::class);

        return view('folders.index', [
            'folders' => $query->run($request->user()),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Folder::class);

        return view('folders.create');
    }

    public function store(StoreFolderRequest $request, CreateFolderAction $action): RedirectResponse
    {
        $folder = $action->execute($request->user(), $request->validated());

        return redirect()
            ->route('folders.show', $folder)
            ->with('success', __('folders.flash.created'));
    }

    public function show(Folder $folder, FolderAvailableMembersQuery $availableMembers): View
    {
        $this->authorize('view', $folder);

        $folder->load([
            'owner',
            'members.user',
            'documents' => fn ($q) => $q->latest('folder_documents.created_at'),
            'children'  => fn ($q) => $q->withCount('documents'),
        ]);

        return view('folders.show', [
            'folder'   => $folder,
            'orgUsers' => $availableMembers->run($folder),
        ]);
    }

    public function destroy(Folder $folder): RedirectResponse
    {
        $this->authorize('delete', $folder);

        $folder->delete();

        return redirect()
            ->route('folders.index')
            ->with('success', __('folders.flash.deleted'));
    }

    public function addMember(AddFolderMemberRequest $request, Folder $folder, AddFolderMemberAction $action): RedirectResponse
    {
        $this->authorize('manageMembers', $folder);

        $target = User::findOrFail($request->input('user_id'));

        $action->execute($folder, $target, $request->string('role'));

        return back()->with('success', __('folders.flash.member_added'));
    }

    public function removeMember(Folder $folder, User $user): RedirectResponse
    {
        $this->authorize('manageMembers', $folder);

        $folder->members()->where('user_id', $user->id)->delete();

        return back()->with('success', __('folders.flash.member_removed'));
    }

    public function uploadDocument(UploadFolderDocumentRequest $request, Folder $folder, UploadFolderDocumentAction $action): RedirectResponse
    {
        $this->authorize('view', $folder);

        $action->execute(
            folder: $folder,
            user:   $request->user(),
            data:   $request->validated(),
            file:   $request->file('file'),
        );

        return back()->with('success', __('folders.flash.document_uploaded'));
    }

    public function removeDocument(Folder $folder, int $documentId): RedirectResponse
    {
        $this->authorize('view', $folder);

        $folder->documents()->detach($documentId);

        return back()->with('success', __('folders.flash.document_removed'));
    }
}
