<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Attachments (Core)
    |--------------------------------------------------------------------------
    |
    | Files attached to records (IDs, invoices, receipts…). Stored on the disk
    | from media-library.disk_name (ATTACHMENTS_DISK) and served only through
    | authorized routes.
    |
    */

    'max_kilobytes' => (int) env('ATTACHMENTS_MAX_KB', 10240),

    'extensions' => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'],

    'mime_types' => [
        'application/pdf',
        'image/jpeg', 'image/png', 'image/webp', 'image/heic',
        'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain',
    ],

];
