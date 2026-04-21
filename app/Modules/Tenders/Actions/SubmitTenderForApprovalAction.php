<?php

namespace Modules\Tenders\Actions;

use App\Models\AppNotification;
use App\Models\Tender;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Modules\Tenders\Queries\TenderApproversQuery;

class SubmitTenderForApprovalAction
{
    public function __construct(private readonly TenderApproversQuery $approvers)
    {
    }

    public function execute(Tender $tender, User $submitter): Tender
    {
        if (! in_array($tender->workflow_status, ['draft', 'rejected'], true)) {
            throw new UnprocessableEntityHttpException(__('tenders.errors.cannot_submit'));
        }

        $tender->update([
            'workflow_status'  => 'submitted',
            'submitted_by'     => $submitter->id,
            'submitted_at'     => now(),
            'rejection_reason' => null,
        ]);

        foreach ($this->approvers->run($tender, $submitter) as $approverId) {
            AppNotification::notify(
                userId: $approverId,
                type:   'tender_submitted',
                title:  __('tenders.notifications.submitted_title'),
                body:   __('tenders.notifications.submitted_body', [
                    'title' => $tender->title,
                    'name'  => $submitter->name,
                ]),
                data:   ['tender_id' => $tender->id],
            );
        }

        return $tender;
    }
}
