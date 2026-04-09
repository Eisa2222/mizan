<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\ArticleUpdate;
use App\Models\LegalDocument;
use App\Models\User;

class ArticleUpdatePolicy
{
    /** Same-org check via the parent document. */
    private function sameOrg(User $user, ArticleUpdate $update): bool
    {
        $doc = $update->document ?? LegalDocument::find($update->document_id);
        return $doc !== null
            && $user->org_id !== null
            && $user->org_id === $doc->org_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAtLeastRole(UserRole::ReadOnly);
    }

    public function view(User $user, ArticleUpdate $update): bool
    {
        return $this->sameOrg($user, $update) && $user->hasAtLeastRole(UserRole::ReadOnly);
    }

    /** Researcher+ may add manual updates to any document in their org. */
    public function createForDocument(User $user, LegalDocument $document): bool
    {
        return $user->org_id === $document->org_id
            && $user->hasAtLeastRole(UserRole::Researcher);
    }

    /** Only the creator or org-admin+ may delete. Auto-generated updates can only be removed by org-admin+. */
    public function delete(User $user, ArticleUpdate $update): bool
    {
        if (! $this->sameOrg($user, $update)) {
            return false;
        }
        if ($update->auto_generated) {
            return $user->hasAtLeastRole(UserRole::OrgAdmin);
        }
        return $update->created_by === $user->id
            || $user->hasAtLeastRole(UserRole::OrgAdmin);
    }
}
