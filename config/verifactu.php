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
        'test' => 'https://prewww1.agenciatributaria.gob.es/ar_aret_litx_ws/VeriFactu',
        'test_query' => 'https://prewww1.agenciatributaria.gob.es/ar_aret_litx_ws/ConsultaVeriFactu',
        'production' => 'https://www1.agenciatributaria.gob.es/ar_aret_litx_ws/VeriFactu',
        'production_query' => 'https://www1.agenciatributaria.gob.es/ar_aret_litx_ws/ConsultaVeriFactu',
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
