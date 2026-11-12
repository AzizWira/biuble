<?php

return [
    'client_id' => env('DOKU_CLIENT_ID'),
    'secret' => env('DOKU_SECRET'),
    'production' => env('DOKU_PRODUCTION', false),
    // Explicit opt-in after channel and merchant activation.
    'enabled' => env('DOKU_ENABLED', false),
    'channels' => ['QRIS' => 'QRIS', 'VIRTUAL_ACCOUNT_BCA' => 'Virtual Account BCA',
        'VIRTUAL_ACCOUNT_BANK_MANDIRI' => 'Virtual Account Mandiri',
        'VIRTUAL_ACCOUNT_BNI' => 'Virtual Account BNI', 'EMONEY_DANA' => 'DANA'],
];

