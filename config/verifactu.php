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
        'production' => 'https://www1.agenciatributaria.gob.es/wlpl/SSII-FACT/ws/VeriFactuSuministroSOAP',
        'production_query' => 'https://www1.agenciatributaria.gob.es/wlpl/SSII-FACT/ws/VeriFactuConsultaSOAP',
    ],

    // URL para el código QR (Consulta Pública)
    'qr_url' => [
        'test' => 'https://prewww2.aeat.es/wlpl/VERI-FACTU/ConsultaPublica',
        'production' => 'https://www2.agenciatributaria.gob.es/wlpl/VERI-FACTU/ConsultaPublica',
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
