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
        'test' => 'https://se-face.redsara.es/facturasspp/sspp',
        'production' => 'https://face.gob.es/facturasspp/sspp',
    ],

    'mode' => env('FACTURAE_MODE', 'test'), // test | production
];
