<?php

use App\Http\Controllers\Api\PosController;
use App\Http\Controllers\Api\ErpController;
use Illuminate\Support\Facades\Route;

// Rutas públicas
Route::prefix('pos')->group(function () {
    Route::post('/login', [PosController::class, 'login']);
});

// Rutas protegidas con Sanctum - POS
Route::middleware('auth:sanctum')->prefix('pos')->group(function () {
    Route::get('/product/{code}', [PosController::class, 'getProduct']);
    Route::post('/ticket/create', [PosController::class, 'createTicket']);
    Route::post('/ticket/add-item', [PosController::class, 'addItem']);
    Route::delete('/ticket/remove-item/{id}', [PosController::class, 'removeItem']);
    Route::get('/ticket/current', [PosController::class, 'getCurrentTicket']);
    Route::post('/ticket/checkout', [PosController::class, 'checkout']);
    Route::get('/totals', [PosController::class, 'getTotals']);
});

// Rutas protegidas con Sanctum - ERP
Route::middleware('auth:sanctum')->prefix('erp')->group(function () {
    // Terceros
    Route::get('/terceros', [ErpController::class, 'getTerceros']);
    Route::get('/terceros/{id}', [ErpController::class, 'getTercero']);
    Route::post('/terceros', [ErpController::class, 'createTercero']);
    Route::put('/terceros/{id}', [ErpController::class, 'updateTercero']);
    Route::delete('/terceros/{id}', [ErpController::class, 'deleteTercero']);
    
    // Documentos
    Route::get('/documentos', [ErpController::class, 'getDocumentos']);
    Route::get('/documentos/{id}', [ErpController::class, 'getDocumento']);
    Route::post('/documentos', [ErpController::class, 'createDocumento']);
    Route::put('/documentos/{id}', [ErpController::class, 'updateDocumento']);
    Route::delete('/documentos/{id}', [ErpController::class, 'deleteDocumento']);
    Route::post('/documentos/{id}/convertir', [ErpController::class, 'convertirDocumento']);
    
    // Productos
    // Productos
    Route::get('/productos', [ErpController::class, 'getProductos']);
    Route::get('/productos/search', [ErpController::class, 'searchProducto']); // Más específica antes de /{id}
    Route::get('/productos/{id}', [ErpController::class, 'getProducto']);
    Route::post('/productos', [ErpController::class, 'createProducto']);
    Route::put('/productos/{id}', [ErpController::class, 'updateProducto']);
    Route::delete('/productos/{id}', [ErpController::class, 'deleteProducto']);
});
