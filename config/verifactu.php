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
        'test' => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        'test_query' => 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        'production' => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
        'production_query' => 'https://www1.agenciatributaria.gob.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP',
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
