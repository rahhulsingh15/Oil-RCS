<?php

/*
| Document Repository settings. Change the list or the limits here.
*/

return [

    // key saved in the database => label shown in the portal
    'types' => [
        'WCR' => 'Well Completion Report (WCR)',
        'RSC' => 'RSC / Contract',
        'Study Report' => 'Study Report',
        'Letter' => 'Letters & Communications',
        'MC Minutes' => 'MC Minutes',
        'Other' => 'Other',
    ],

    // Allowed file extensions
    // Client (26 Oct): PDF, Word, Excel and images only
    'extensions' => ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png'],

    // Max size per file in KB (50 MB, client 26 Oct). PHP upload_max_filesize and post_max_size must be larger.
    'max_kb' => 51200,
];
