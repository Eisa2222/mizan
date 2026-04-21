<?php

return [

    'flash' => [
        'created' => 'Documents linked successfully.',
        'deleted' => 'Link removed.',
    ],

    'errors' => [
        'self_link'           => 'A document cannot link to itself.',
        'cross_org'           => 'Documents from different organizations cannot be linked.',
        'target_inaccessible' => 'The target document does not exist or you do not have access to it.',
        'delete_denied'       => 'You are not allowed to delete this relation.',
    ],

];
