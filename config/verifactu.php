<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Veri*Factu Configuration (Standard AEAT)
    |--------------------------------------------------------------------------
    */

    'nif_emisor' => env('VERIFACTU_NIF_EMISOR', 'B00000000'),
    'nombre_emisor' => env('VERIFACTU_NOMBRE_EMISOR', 'SienteERP Demo User'),
    
    // AEAT Endpoints (Pruebas / Producción)
    'endpoints' => [
        'test' => env('VERIFACTU_ENDPOINT_TEST', 'https://prewww10.aeat.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAltaSOAP'),
        'test_query' => env('VERIFACTU_ENDPOINT_TEST_ANUL', 'https://prewww10.aeat.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAnulSOAP'),
        'production' => env('VERIFACTU_ENDPOINT_PRODUCTION', 'https://www10.agenciatributaria.gob.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAltaSOAP'),
        'production_query' => env('VERIFACTU_ENDPOINT_PRODUCTION_ANUL', 'https://www10.agenciatributaria.gob.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAnulSOAP'),
    ],

    // URL para el código QR (Consulta Pública)
    'qr_url' => [
        'test' => 'https://prewww2.aeat.es/wlpl/VERI-FACTU/ConsultaPublica',
        'production' => 'https://www2.agenciatributaria.gob.es/wlpl/VERI-FACTU/ConsultaPublica',
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
