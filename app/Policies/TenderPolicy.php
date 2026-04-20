<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\TenderWorkflowStatus;
use App\Models\Tender;
use App\Models\User;

/**
 * Per-row authorization for tenders. Coarse-grained permissions like
 * `tenders.view`/`tenders.create` are granted by the role permission map
 * (resolved by Gate::before); this policy layers the per-row rules:
 * same-org, creator-only delete, and workflow-state guards.
 */
class TenderPolicy
{
    private function sameOrg(User $user, Tender $tender): bool
    {
        return $user->org_id !== null && $user->org_id === $tender->org_id;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::TendersView);
    }

    public function view(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersView);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::TendersCreate);
    }

    public function update(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersUpdate);
    }

    public function delete(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersDelete)
            && ($tender->created_by === $user->id || $user->hasPermission(Permission::AdminAccess));
    }

    public function submit(User $user, Tender $tender): bool
    {
        if (! $this->sameOrg($user, $tender) || ! $user->hasPermission(Permission::TendersSubmit)) {
            return false;
        }

        $status = TenderWorkflowStatus::tryFrom((string) $tender->workflow_status);

        return $status?->canSubmit() ?? false;
    }

    public function approve(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersApprove)
            && $tender->workflow_status === TenderWorkflowStatus::Submitted->value;
    }

    public function reject(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersReject)
            && $tender->workflow_status === TenderWorkflowStatus::Submitted->value;
    }

    public function export(User $user, Tender $tender): bool
    {
        return $this->sameOrg($user, $tender)
            && $user->hasPermission(Permission::TendersExport);
    }
}
