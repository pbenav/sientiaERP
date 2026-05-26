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
        'test' => env('FACE_URL_PRUEBAS', 'https://webservice.ssff.face.gob.es/facturasspp?wsdl'),
        'production' => env('FACE_URL_PRODUCCION', 'https://webservice.face.gob.es/facturasspp?wsdl'),
    ],

    'mode' => env('FACTURAE_MODE', 'test'), // test | production
];
