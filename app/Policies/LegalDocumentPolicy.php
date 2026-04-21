<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\LegalDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LegalDocumentPolicy
{
    private function sameOrg(User $user, LegalDocument $doc): bool
    {
        return $user->org_id !== null && $user->org_id === $doc->org_id;
    }

    /**
     * Visibility check for a single document.
     *  - Public docs (is_private=false): anyone in the same org with ReadOnly+ can see them.
     *  - Private docs: only the uploader, OR a member/owner of any folder containing the doc,
     *    OR an OrgAdmin+ in the same org.
     */
    private function canSeePrivate(User $user, LegalDocument $doc): bool
    {
        if ($doc->uploaded_by === $user->id) return true;
        if ($user->hasAtLeastRole(UserRole::OrgAdmin)) return true;

        // Member or owner of any folder containing this document
        return DB::table('folder_documents')
            ->join('folders', 'folders.id', '=', 'folder_documents.folder_id')
            ->leftJoin('folder_members', function ($j) use ($user) {
                $j->on('folder_members.folder_id', '=', 'folder_documents.folder_id')
                  ->where('folder_members.user_id', '=', $user->id);
            })
            ->where('folder_documents.document_id', $doc->id)
            ->where(function ($w) use ($user) {
                $w->where('folders.owner_id', $user->id)
                  ->orWhereNotNull('folder_members.id');
            })
            ->exists();
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAtLeastRole(UserRole::ReadOnly);
    }

    public function view(User $user, LegalDocument $doc): bool
    {
        // SuperAdmin manages all organisations — including the shared
        // "المكتبة المرجعية" (org 3) that holds imported laws/rulings any
        // org user needs to read.
        if ($user->hasAtLeastRole(UserRole::SuperAdmin)) return true;

        if (!$this->sameOrg($user, $doc)) return false;
        if (!$user->hasAtLeastRole(UserRole::ReadOnly)) return false;

        return $doc->is_private ? $this->canSeePrivate($user, $doc) : true;
    }

    /**
     * Only Researcher+ can upload legal documents.
     * OrgUser role has access to all other services (tenders, contracts,
     * memos, cases) but NOT legal document upload.
     */
    public function create(User $user): bool
    {
        return $user->role?->canUploadDocuments() ?? false;
    }

    public function update(User $user, LegalDocument $doc): bool
    {
        return $this->sameOrg($user, $doc)
            && ($doc->uploaded_by === $user->id || $user->hasAtLeastRole(UserRole::LegalCounsel));
    }

    public function delete(User $user, LegalDocument $doc): bool
    {
        return $this->sameOrg($user, $doc)
            && ($doc->uploaded_by === $user->id || $user->hasAtLeastRole(UserRole::OrgAdmin));
    }
}
