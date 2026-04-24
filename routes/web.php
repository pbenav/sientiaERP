<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\PdfController;

Route::get('/', function () {
    return view('welcome');
});

// Legal pages
Route::get('/privacy-policy', [App\Http\Controllers\LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms-of-service', [App\Http\Controllers\LegalController::class, 'terms'])->name('terms');
Route::get('/cookie-policy', [App\Http\Controllers\LegalController::class, 'cookies'])->name('cookies');

Route::get('/documentos/{record}/pdf', [PdfController::class, 'downloadDocumento'])
    ->name('documentos.pdf')
    ->middleware(['auth']);

Route::get('/documentos/{record}/ticket', [PdfController::class, 'ticketDocumento'])
    ->name('documentos.ticket')
    ->middleware(['auth']);

Route::get('/pos/ticket/{record}', [PdfController::class, 'ticketPos'])
    ->name('pos.ticket')
    ->middleware(['auth']);

Route::get('/pos/ticket-raw/{record}', [PdfController::class, 'ticketPosRaw'])
    ->name('pos.ticket.raw')
    ->middleware(['auth']);

Route::get('/pos/ticket-regalo/{record}', [PdfController::class, 'ticketRegalo'])
    ->name('pos.ticket.regalo')
    ->middleware(['auth']);

Route::get('/etiquetas/{record}/pdf', [\App\Http\Controllers\LabelController::class, 'download'])
    ->name('etiquetas.pdf')
    ->middleware(['auth']);

Route::get('/facturae/{record}/download', [\App\Http\Controllers\FacturaeController::class, 'download'])
    ->name('facturae.download')
    ->middleware(['auth']);

