<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Facturae Configuration
    |--------------------------------------------------------------------------
    |
    | Official endpoints for FACe (General Entry Point of Electronic Invoices)
    |
    */

    'endpoints' => [
        'test' => 'https://se-face-webservice.redsara.es/facturasspp',
        'production' => 'https://face-webservice.redsara.es/facturasspp',
    ],

    'mode' => env('FACTURAE_MODE', 'test'), // test | production
];
