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
        'test' => 'https://www2.agenciatributaria.gob.es/ar_aret_litx_ws/VeriFactu',
        'production' => 'https://www2.agenciatributaria.gob.es/ar_aret_litx_ws/VeriFactu',
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
