<?php

namespace Modules\Tenders\Actions;

use App\Models\AppNotification;
use App\Models\Tender;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class RejectTenderAction
{
    public function execute(Tender $tender, User $rejector, string $reason): Tender
    {
        if ($tender->workflow_status !== 'submitted') {
            throw new UnprocessableEntityHttpException(__('tenders.errors.not_pending_approval'));
        }

        $tender->update([
            'workflow_status'  => 'rejected',
            'rejection_reason' => $reason,
        ]);

        if ($tender->created_by) {
            AppNotification::notify(
                userId: $tender->created_by,
                type:   'tender_rejected',
                title:  __('tenders.notifications.rejected_title'),
                body:   __('tenders.notifications.rejected_body', [
                    'title'  => $tender->title,
                    'reason' => $reason,
                ]),
                data:   ['tender_id' => $tender->id],
            );
        }

        return $tender;
    }
}
