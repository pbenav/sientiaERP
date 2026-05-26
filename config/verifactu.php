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
        'test' => env('AEAT_URL_ALTA_PRUEBAS', 'https://prewww10.aeat.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAltaSOAP'),
        'test_query' => env('AEAT_URL_ANULACION_PRUEBAS', 'https://prewww10.aeat.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAnulSOAP'),
        'production' => env('AEAT_URL_ALTA_PRODUCCION', 'https://www10.agenciatributaria.gob.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAltaSOAP'),
        'production_query' => env('AEAT_URL_ANULACION_PRODUCCION', 'https://www10.agenciatributaria.gob.es/wlpl/SSII-FACT/webservice/v1/VeriFactuAnulSOAP'),
    ],

    // URL para el código QR (Consulta Pública / Validación de Factura)
    'qr_url' => [
        'test' => env('AEAT_URL_QR_PRUEBAS', 'https://prewww10.aeat.es/wlpl/TIKE-CONT/ValidarQR'),
        'production' => env('AEAT_URL_QR_PRODUCCION', 'https://www2.agenciatributaria.gob.es/wlpl/TIKE-CONT/v1/f'),
    ],

    'mode' => env('VERIFACTU_MODE', 'test'), // test | production
];
