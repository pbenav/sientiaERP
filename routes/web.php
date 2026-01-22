<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/documentos/{record}/pdf', [PdfController::class, 'downloadDocumento'])
    ->name('documentos.pdf')
    ->middleware(['auth']);

Route::get('/etiquetas/{record}/pdf', [\App\Http\Controllers\LabelController::class, 'download'])
    ->name('etiquetas.pdf')
    ->middleware(['auth']);

