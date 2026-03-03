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
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EditImportDraft extends Page
{
    protected static string $resource = ImportDraftResource::class;
    protected static string $view = 'filament.resources.import-draft-resource.pages.edit-import-draft';

    // ── Estado del componente ────────────────────────────────────────────────
    public ?ImportDraft $draft = null;
    public string  $status            = 'pending';
    public ?int    $matched_provider_id = null;
    public ?string $provider_name     = null;
    public ?string $provider_nif      = null;
    public ?string $document_number   = null;
    public ?string $document_date     = null;
    public float   $subtotal          = 0;
    public float   $total_discount    = 0;
    public float   $total_amount      = 0;
    public ?string $raw_text          = null;
    public array   $items             = [];
    public array   $suppliers         = [];

    // ── Mount ────────────────────────────────────────────────────────────────
    public function mount(int|string $record): void
    {
        $this->draft = ImportDraft::with(['expedicionCompra.expedicion', 'tercero'])->findOrFail($record);

        // Seguridad: Si el documento asociado ha sido eliminado, resetear el borrador automáticamente
        if (!$this->draft->verifyDocumentExistence()) {
            Notification::make()
                ->title('Aviso de Seguridad')
                ->body('El albarán asociado ya no existe. El borrador ha sido reseteado a pendiente para permitir su reprocesamiento.')
                ->warning()
                ->persistent()
                ->send();
            
            $this->redirect(ImportDraftResource::getUrl('edit', ['record' => $this->draft->id]));
            return;
        }

        $this->status              = $this->draft->status;
        $this->matched_provider_id = $this->draft->matched_provider_id;
        $this->provider_name       = $this->draft->provider_name;
        $this->provider_nif        = $this->draft->provider_nif;
        $this->document_number     = $this->draft->document_number;
        $this->document_date       = $this->draft->document_date?->format('Y-m-d');
        $this->subtotal            = (float) $this->draft->subtotal;
        $this->total_discount      = (float) $this->draft->total_discount;
        $this->total_amount        = (float) $this->draft->total_amount;
        $this->raw_text            = $this->draft->raw_text;
        $this->items               = $this->draft->items ?? [];

        // Enriquecer ítems con datos del producto existente
        foreach ($this->items as &$item) {
            $item['existing_product'] = null;
            if (!empty($item['matched_product_id'])) {
                $product = Product::find($item['matched_product_id']);
                if ($product) {
                    $item['existing_product'] = [
                        'name'           => $product->name,
                        'sku'            => $product->sku,
                        'purchase_price' => $product->purchase_price,
                        'price'          => $product->price,
                        'stock'          => $product->stock,
                    ];
                }
            }
        }
        unset($item);

        $this->suppliers = Tercero::whereHas('tipos', fn ($q) => $q->where('codigo', 'PRO'))
            ->orderBy('nombre_comercial')
            ->get()
            ->toArray();
    }

    public function getTitle(): string
    {
        $icon = match ($this->status) {
            'pending'   => '⏳',
            'confirmed' => '✅',
            'rejected'  => '❌',
            default     => '📄',
        };
        return "$icon Borrador #{$this->draft->id} — " . ($this->provider_name ?? 'Sin proveedor');
    }

    // ── Acciones de cabecera ─────────────────────────────────────────────────
    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('← Volver al listado')
                ->color('gray')
                ->url(ImportDraftResource::getUrl('index')),

            Action::make('volver_expedicion')
                ->label('← Volver a la Expedición')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->visible(fn () => $this->draft->expedicion_compra_id !== null)
                ->url(function () {
                    $compra = $this->draft->expedicionCompra;
                    if (!$compra) return null;
                    return route('filament.admin.resources.expedicions.procesar', [
                        'record' => $compra->expedicion_id,
                    ]);
                }),

            Action::make('rechazar')
                ->label('Rechazar')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('¿Rechazar este borrador?')
                ->modalDescription('El borrador quedará marcado como rechazado y no se creará ningún albarán.')
                ->visible(fn () => $this->draft->isPending())
                ->action(function () {
                    $this->draft->update(['status' => 'rejected']);
                    Notification::make()->title('Borrador rechazado')->warning()->send();
                    $this->redirect(ImportDraftResource::getUrl('index'));
                }),

            Action::make('confirmar_integrar')
                ->label('✅ Confirmar e Integrar')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->requiresConfirmation()
                ->modalHeading('Confirmar e Integrar Importación')
                ->modalDescription('Se creará el albarán, se actualizarán los productos y el stock. Esta acción no se puede deshacer.')
                ->visible(fn () => $this->draft->isPending())
                ->action(function () {
                    $this->save();
                    $this->integrateDraft();
                }),
        ];
    }

    // ── Métodos de líneas ────────────────────────────────────────────────────
    public function addItem(): void
    {
        $this->items[] = [
            'description'        => '',
            'reference'          => '',
            'product_code'       => '',
            'quantity'           => 1.0,
            'unit_price'         => 0.0,
            'discount'           => 0.0,
            'margin'             => (float) \App\Models\Setting::get('default_profit_percentage', 60),
            'benefit'            => 0.0,
            'vat_rate'           => (float) \App\Models\Setting::get('default_tax_rate', 21),
            'vat_amount'         => 0.0,
            'sale_price'         => 0.0,
            'matched_product_id' => null,
            'print_label'        => true,
            'existing_product'   => null,
        ];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    /**
     * Mostrar toast de comparación para productos existentes.
     */
    public function showProductComparison(int $index): void
    {
        $item    = $this->items[$index] ?? null;
        $productId = $item['matched_product_id'] ?? null;
        if (!$productId) return;

        $product = Product::find($productId);
        if (!$product) return;

        $body = "**SKU:** `{$product->sku}`\n\n";
        
        $pcActual = number_format((float) $product->purchase_price, 2, ',', '.') . ' €';
        $pcImport = number_format((float) ($item['unit_price'] ?? 0), 2, ',', '.') . ' €';
        $body .= "• **PC actual:** {$pcActual} ➔ **Importado:** {$pcImport}\n";

        $pvpActual = number_format((float) $product->price, 2, ',', '.') . ' €';
        $pvpImport = number_format((float) ($item['sale_price'] ?? 0), 2, ',', '.') . ' €';
        $body .= "• **PVP actual:** {$pvpActual} ➔ **Importado:** {$pvpImport}\n\n";
        
        $body .= "**Stock actual:** `{$product->stock} uds`";

        Notification::make()
            ->title('🔍 Comparando: ' . ($product->name))
            ->body($body)
            ->warning()
            ->persistent()
            ->send();
    }

    // ── Guardar ──────────────────────────────────────────────────────────────
    public function save(): void
    {
        // Strip transient front-end key before persisting
        $itemsToSave = array_map(function ($item) {
            unset($item['existing_product']);
            return $item;
        }, $this->items);

        $this->draft->update([
            'matched_provider_id' => $this->matched_provider_id,
            'document_number'     => $this->document_number,
            'document_date'       => $this->document_date,
            'subtotal'            => $this->subtotal,
            'total_discount'      => $this->total_discount,
            'total_amount'        => $this->total_amount,
            'items'               => $itemsToSave,
        ]);

        Notification::make()
            ->title('Borrador guardado')
            ->success()
            ->send();
    }

    // ── Integrar ─────────────────────────────────────────────────────────────
    protected function integrateDraft(): void
    {
        try {
            DB::transaction(function () {
                $draft    = $this->draft;
                $terceroId = $this->matched_provider_id;
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
                        $tipo = TipoTercero::where('codigo', 'PRO')->first();
                        if ($tipo) $dummy->tipos()->syncWithoutDetaching([$tipo->id]);
                    }
                    $terceroId = $dummy->id;
                    $observaciones[] = "AVISO: Proveedor no detectado. Asignado a 'PROVEEDOR PENDIENTE'.";
                }
                if ($this->provider_name && !$this->matched_provider_id) {
                    $observaciones[] = "Proveedor detectado por IA: " . $this->provider_name;
                }
                if ($this->raw_text) {
                    $observaciones[] = "--- OCR RAW TEXT ---\n" . $this->raw_text;
                }

                $documento = Documento::create([
                    'tipo'                 => 'albaran_compra',
                    'estado'               => 'borrador',
                    'user_id'              => auth()->id(),
                    'fecha'                => $this->document_date ?? now(),
                    'serie'                => BillingSerie::where('activo', true)->orderBy('codigo')->first()?->codigo ?? 'A',
                    'tercero_id'           => $terceroId,
                    'referencia_proveedor' => (string) ($this->document_number ?? 'REF-' . strtoupper(uniqid())),
                    'subtotal'             => (float) $this->subtotal,
                    'descuento'            => (float) $this->total_discount,
                    'total'                => (float) $this->total_amount,
                    'archivo'              => $draft->documento_path,
                    'observaciones'        => implode("\n\n", $observaciones),
                ]);

                foreach ($this->items as &$item) {
                    if (empty($item['matched_product_id'])) {
                        $ref = $item['reference'] ?? $item['product_code'] ?? null;
                        if (!empty($ref)) {
                            // Include inactive products: in purchase context we want to reactivate them
                            $ex = Product::withoutGlobalScopes()
                                ->where('sku', $ref)
                                ->orWhere('barcode', $ref)
                                ->first();
                            if ($ex) {
                                $item['matched_product_id'] = $ex->id;
                                // Reactivate if it was soft-deleted or inactive
                                if ($ex->deleted_at) {
                                    $ex->restore();
                                }
                                if (!$ex->active) {
                                    $ex->update(['active' => true]);
                                }
                            }
                        }
                    }
                    $gross  = (float) ($item['unit_price'] ?? 0);
                    $dto    = (float) ($item['discount'] ?? 0);
                    $net    = $gross * (1 - ($dto / 100));
                    $margin = (float) ($item['margin'] ?? 60);
                    $retail = (float) ($item['sale_price'] ?? 0);
                    $vat    = (float) ($item['vat_rate'] ?? 21);
                    $desc   = !empty($item['description']) ? $item['description'] : 'Producto sin descripción';
                    $ref    = $item['reference'] ?? $item['product_code'] ?? null;

                    if (!empty($item['matched_product_id'])) {
                        // Update existing product with new purchase data
                        $product = Product::withTrashed()->find($item['matched_product_id']);
                        if ($product) {
                            $product->name           = !empty($item['description']) ? $item['description'] : $product->name;
                            $product->price          = $retail;
                            $product->purchase_price = $net;
                            $product->tax_rate       = $vat;
                            $product->addPurchaseHistory($gross, $dto, $net, $margin, $this->document_number);
                            $product->save();
                        }
                    } else {
                        // Create new product, using firstOrCreate to avoid duplicate SKU errors
                        $sku = $ref ?: ('AUTO-' . strtoupper(uniqid()));
                        $newProduct = Product::firstOrCreate(
                            ['sku' => $sku],
                            [
                                'name'           => $desc,
                                'description'    => $desc,
                                'price'          => $retail,
                                'purchase_price' => $net,
                                'tax_rate'       => $vat,
                                'active'         => true,
                                'stock'          => 0,
                                'barcode'        => $sku,
                            ]
                        );
                        // If found existing (not created), update purchase prices
                        if (!$newProduct->wasRecentlyCreated) {
                            $newProduct->price          = $retail ?: $newProduct->price;
                            $newProduct->purchase_price = $net;
                            $newProduct->save();
                        }
                        // Remove redundant save() — addPurchaseHistory only modifies $this->metadata,
                        // the actual save is done above or below
                        $newProduct->addPurchaseHistory($gross, $dto, $net, $margin, $this->document_number);
                        $newProduct->save();
                        $item['matched_product_id'] = $newProduct->id;
                    }

                    $qty      = (float) ($item['quantity'] ?? 1);
                    $subtotal = $qty * $gross * (1 - ($dto / 100));

                    $documento->lineas()->create([
                        'product_id'      => $item['matched_product_id'],
                        'codigo'          => $ref ?? 'REF-TEMP',
                        'descripcion'     => $desc,
                        'cantidad'        => $qty,
                        'unidad'          => 'Ud',
                        'precio_unitario' => $gross,
                        'descuento'       => $dto,
                        'subtotal'        => $subtotal,
                        'iva'             => $vat,
                        'importe_iva'     => $subtotal * ($vat / 100),
                        'irpf'            => 0,
                        'importe_irpf'    => 0,
                        'total'           => $subtotal * (1 + ($vat / 100)),
                    ]);
                }
                unset($item);

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

                if ($draft->expedicion_compra_id) {
                    $draft->expedicionCompra?->update([
                        'documento_id' => $documento->id,
                        'recogido'     => true,
                    ]);
                }

                $draft->update([
                    'status'       => 'confirmed',
                    'confirmed_at' => now(),
                    'documento_id' => $documento->id,
                ]);

                Notification::make()
                    ->title('✅ Importación confirmada')
                    ->body('El albarán ha sido creado y el stock actualizado.')
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
