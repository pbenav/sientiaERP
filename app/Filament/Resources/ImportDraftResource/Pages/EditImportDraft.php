<?php

namespace App\Filament\Resources\ImportDraftResource\Pages;

use App\Filament\Resources\ImportDraftResource;
use App\Models\BillingSerie;
use App\Models\Documento;
use App\Models\ImportDraft;
use App\Models\Product;
use App\Models\Tercero;
use App\Models\TipoTercero;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditImportDraft extends EditRecord
{
    protected static string $resource = ImportDraftResource::class;

    public function getTitle(): string
    {
        $status = $this->record->status;
        $icon   = $status === 'pending' ? '⏳' : ($status === 'confirmed' ? '✅' : '❌');
        return $icon . ' Borrador #' . $this->record->id . ' — ' . ($this->record->provider_name ?? 'Sin proveedor');
    }

    protected function getHeaderActions(): array
    {
        return [
            // ── Volver a la expedición ───────────────────────────────────
            Action::make('volver_expedicion')
                ->label('← Volver a la Expedición')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->visible(fn () => $this->record->expedicion_compra_id !== null)
                ->url(function () {
                    $compra = $this->record->expedicionCompra;
                    if (!$compra) return null;
                    return route(
                        'filament.admin.resources.expedicions.procesar',
                        ['record' => $compra->expedicion_id]
                    );
                }),

            // ── Ver producto existente (notificación) ────────────────────
            Action::make('ver_productos_existentes')
                ->label('Ver productos duplicados')
                ->color('warning')
                ->icon('heroicon-o-exclamation-triangle')
                ->visible(function () {
                    $items = $this->record->items ?? [];
                    return collect($items)->contains(fn ($i) => !empty($i['matched_product_id']));
                })
                ->action(function () {
                    $items = $this->record->items ?? [];
                    $matched = collect($items)->filter(fn ($i) => !empty($i['matched_product_id']));

                    foreach ($matched as $item) {
                        $product = Product::find($item['matched_product_id']);
                        if (!$product) continue;

                        Notification::make()
                            ->title('⚠️ Producto ya existe: ' . ($item['description'] ?? ''))
                            ->body(
                                "**SKU actual:** {$product->sku}\n" .
                                "**Nombre actual:** {$product->name}\n" .
                                "**PC actual:** " . number_format((float)$product->purchase_price, 2, ',', '.') . " € → **Nuevo:** " . number_format((float)($item['unit_price'] ?? 0), 2, ',', '.') . " €\n" .
                                "**PVP actual:** " . number_format((float)$product->price, 2, ',', '.') . " € → **Nuevo:** " . number_format((float)($item['sale_price'] ?? 0), 2, ',', '.') . " €\n" .
                                "**Stock actual:** {$product->stock} uds"
                            )
                            ->warning()
                            ->persistent()
                            ->send();
                    }
                }),

            // ── Rechazar ─────────────────────────────────────────────────
            Action::make('rechazar')
                ->label('Rechazar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('¿Rechazar este borrador?')
                ->modalDescription('El borrador quedará marcado como rechazado y no se creará ningún albarán.')
                ->visible(fn () => $this->record->isPending())
                ->action(function () {
                    $this->record->update(['status' => 'rejected']);
                    Notification::make()
                        ->title('Borrador rechazado')
                        ->warning()
                        ->send();
                    $this->redirect(ImportDraftResource::getUrl('index'));
                }),

            // ── Confirmar e Integrar ──────────────────────────────────────
            Action::make('confirmar_integrar')
                ->label('✅ Confirmar e Integrar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirmar e Integrar Importación')
                ->modalDescription('Se creará el albarán de compra, se actualizarán los productos existentes y se ajustará el stock. Esta acción no se puede deshacer.')
                ->visible(fn () => $this->record->isPending())
                ->action(function () {
                    $this->save(); // Guardar ediciones antes de confirmar
                    $this->integrateDraft($this->record);
                }),
        ];
    }

    /**
     * The main integration logic: creates the Documento (Albaran) from the draft.
     */
    protected function integrateDraft(ImportDraft $draft): void
    {
        try {
            DB::transaction(function () use ($draft) {

                // 1. Resolver proveedor
                $terceroId = $draft->matched_provider_id;
                $observaciones = [];

                if (!$terceroId) {
                    $dummy = Tercero::firstOrCreate(
                        ['nif_cif' => '00000000T'],
                        [
                            'nombre_comercial' => 'PROVEEDOR PENDIENTE DE ASIGNAR',
                            'razon_social'     => 'PROVEEDOR GENERADO AUTOMATICAMENTE',
                            'activo'           => true,
                        ]
                    );
                    if (!$dummy->esProveedor()) {
                        $tipoProv = TipoTercero::where('codigo', 'PRO')->first();
                        if ($tipoProv) $dummy->tipos()->syncWithoutDetaching([$tipoProv->id]);
                    }
                    $terceroId = $dummy->id;
                    $observaciones[] = "AVISO: Proveedor no detectado. Asignado a 'PROVEEDOR PENDIENTE'.";
                }

                if ($draft->provider_name && !$draft->matched_provider_id) {
                    $observaciones[] = "Proveedor detectado por IA (no macheado): " . $draft->provider_name;
                }
                if ($draft->raw_text) {
                    $observaciones[] = "--- OCR RAW TEXT ---\n" . $draft->raw_text;
                }

                // 2. Crear el documento (albarán de compra)
                $documento = Documento::create([
                    'tipo'                  => 'albaran_compra',
                    'estado'                => 'borrador',
                    'user_id'               => auth()->id(),
                    'fecha'                 => $draft->document_date ?? now(),
                    'serie'                 => BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
                    'tercero_id'            => $terceroId,
                    'referencia_proveedor'  => (string) ($draft->document_number ?? 'REF-' . strtoupper(uniqid())),
                    'subtotal'              => (float) ($draft->subtotal ?? 0),
                    'descuento'             => (float) ($draft->total_discount ?? 0),
                    'total'                 => (float) ($draft->total_amount ?? 0),
                    'archivo'               => $draft->documento_path,
                    'observaciones'         => implode("\n\n", $observaciones),
                ]);

                // 3. Procesar líneas (crear/actualizar productos)
                $items = $draft->items ?? [];
                foreach ($items as &$item) {
                    // Re-intentar match si no tiene
                    if (empty($item['matched_product_id'])) {
                        $ref = $item['reference'] ?? $item['product_code'] ?? null;
                        if (!empty($ref)) {
                            $existing = Product::where('sku', $ref)->orWhere('barcode', $ref)->first();
                            if ($existing) $item['matched_product_id'] = $existing->id;
                        }
                    }

                    $purchaseGross = (float)($item['unit_price'] ?? 0);
                    $discount      = (float)($item['discount'] ?? 0);
                    $purchaseNet   = $purchaseGross * (1 - ($discount / 100));
                    $margin        = (float)($item['margin'] ?? 60);
                    $retailPrice   = (float)($item['sale_price'] ?? 0);
                    $vatRate       = (float)($item['vat_rate'] ?? 21);
                    $desc          = !empty($item['description']) ? $item['description'] : 'Producto sin descripción';
                    $ref           = $item['reference'] ?? $item['product_code'] ?? null;

                    if (!empty($item['matched_product_id'])) {
                        $product = Product::find($item['matched_product_id']);
                        if ($product) {
                            $product->name           = !empty($item['description']) ? $item['description'] : $product->name;
                            $product->price          = $retailPrice;
                            $product->purchase_price = $purchaseNet;
                            $product->tax_rate       = $vatRate;
                            $product->addPurchaseHistory($purchaseGross, $discount, $purchaseNet, $margin, $draft->document_number);
                            $product->save();
                            $item['matched_product_id'] = $product->id;
                        }
                    } else {
                        $sku = $ref ?: ('AUTO-' . strtoupper(uniqid()));
                        $newProduct = Product::create([
                            'name'           => $desc,
                            'description'    => $desc,
                            'price'          => $retailPrice,
                            'purchase_price' => $purchaseNet,
                            'tax_rate'       => $vatRate,
                            'active'         => true,
                            'stock'          => 0,
                            'sku'            => $sku,
                            'barcode'        => $sku,
                        ]);
                        $newProduct->addPurchaseHistory($purchaseGross, $discount, $purchaseNet, $margin, $draft->document_number);
                        $newProduct->save();
                        $item['matched_product_id'] = $newProduct->id;
                    }

                    // 4. Crear línea del documento
                    $qty      = (float)($item['quantity'] ?? 1);
                    $subtotal = $qty * $purchaseGross * (1 - ($discount / 100));

                    $documento->lineas()->create([
                        'product_id'      => $item['matched_product_id'],
                        'codigo'          => $ref ?? 'REF-TEMP',
                        'descripcion'     => $desc,
                        'cantidad'        => $qty,
                        'unidad'          => 'Ud',
                        'precio_unitario' => $purchaseGross,
                        'descuento'       => $discount,
                        'subtotal'        => $subtotal,
                        'iva'             => $vatRate,
                        'importe_iva'     => $subtotal * ($vatRate / 100),
                        'irpf'            => 0,
                        'importe_irpf'    => 0,
                        'total'           => $subtotal * (1 + ($vatRate / 100)),
                    ]);
                }
                unset($item);

                // 5. Recalcular totales y confirmar (actualiza stock)
                if (method_exists($documento, 'recalcularTotales')) {
                    $documento->recalcularTotales();
                    $documento->save();
                }
                try {
                    if (method_exists($documento, 'confirmar')) {
                        $documento->confirmar();
                    }
                } catch (\Exception $e) {
                    Log::error('Error confirming document from draft: ' . $e->getMessage());
                }

                // 6. Vincular con ExpedicionCompra
                if ($draft->expedicion_compra_id) {
                    $draft->expedicionCompra?->update([
                        'documento_id' => $documento->id,
                        'recogido'     => true,
                    ]);
                }

                // 7. Marcar draft como confirmado
                $draft->update([
                    'status'       => 'confirmed',
                    'confirmed_at' => now(),
                    'documento_id' => $documento->id,
                ]);

                Notification::make()
                    ->title('✅ Importación confirmada')
                    ->body('El albarán ha sido creado y el stock actualizado correctamente.')
                    ->success()
                    ->send();

                $this->redirect(
                    route('filament.admin.resources.albaran-compras.edit', ['record' => $documento])
                );
            });
        } catch (\Exception $e) {
            Log::error('Error integrating draft: ' . $e->getMessage());
            Notification::make()
                ->title('Error al integrar el borrador')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();
        }
    }
}
