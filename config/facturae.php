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
        'test' => env('FACTURAE_ENDPOINT_TEST', 'https://webservice.ssff.face.gob.es/facturasspp?wsdl'),
        'production' => env('FACTURAE_ENDPOINT_PRODUCTION', 'https://webservice.face.gob.es/facturasspp?wsdl'),
    ],

    'mode' => env('FACTURAE_MODE', 'test'), // test | production
];
