<?php

return [

    'flash' => [
        'created'            => 'Tender generated successfully. Review and edit sections as needed.',
        'regenerated'        => 'Tender regenerated successfully.',
        'reviewed'           => 'Compliance review complete.',
        'submitted'          => 'Tender submitted for approval.',
        'approved'           => 'Tender approved.',
        'rejected'           => 'Tender rejected with a reason.',
        'deleted'            => 'Tender deleted.',
        'similarity_ignored' => 'Alert dismissed.',
        'sections_reused'    => ':count section(s) copied from the previous tender.',
    ],

    'errors' => [
        'cannot_submit'        => 'This tender cannot be submitted.',
        'not_pending_approval' => 'Tender is not in a pending-approval state.',
        'approval_denied'      => 'You are not allowed to approve tenders.',
    ],

    'notifications' => [
        'submitted_title' => 'Tender submitted for approval',
        'submitted_body'  => 'Tender ":title" was submitted for approval by :name.',
        'approved_title'  => 'Tender approved',
        'approved_body'   => 'Tender ":title" was approved by :name.',
        'rejected_title'  => 'Tender rejected',
        'rejected_body'   => 'Tender ":title" was rejected. Reason: :reason',
    ],

    'similarity' => [
        'exact_match'       => 'An identical or near-identical tender exists in your organization.',
        'high'              => 'A tender with :score% similarity was found. Review before proceeding.',
        'reuse_opportunity' => 'A previously approved tender can be reused as a reference or starting point.',
        'weak'              => 'Weakly similar tenders were found.',
        'scope_duplicate'   => 'This project may be a duplicate of a prior project within your organization.',
        'scope_high'        => 'A tender with :score% scope similarity was found. Review it.',
        'scope_medium'      => 'A prior tender can be used as a reference.',
    ],

];
