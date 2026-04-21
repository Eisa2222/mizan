<?php

namespace Modules\Tenders\Actions;

use App\Models\AppNotification;
use App\Models\Tender;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class ApproveTenderAction
{
    public function execute(Tender $tender, User $approver): Tender
    {
        if ($tender->workflow_status !== 'submitted') {
            throw new UnprocessableEntityHttpException(__('tenders.errors.not_pending_approval'));
        }

        $tender->update([
            'workflow_status' => 'approved',
            'status'          => 'finalized',
            'approved_by'     => $approver->id,
            'approved_at'     => now(),
        ]);

        if ($tender->created_by) {
            AppNotification::notify(
                userId: $tender->created_by,
                type:   'tender_approved',
                title:  __('tenders.notifications.approved_title'),
                body:   __('tenders.notifications.approved_body', [
                    'title' => $tender->title,
                    'name'  => $approver->name,
                ]),
                data:   ['tender_id' => $tender->id],
            );
        }

        return $tender;
    }
}
