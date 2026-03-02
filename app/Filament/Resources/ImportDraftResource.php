<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImportDraftResource\Pages;
use App\Models\ImportDraft;
use App\Models\Product;
use App\Models\Tercero;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ImportDraftResource extends Resource
{
    protected static ?string $model = ImportDraft::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-arrow-down';
    protected static ?string $navigationLabel = 'Importaciones Pendientes';
    protected static ?string $navigationGroup = 'Compras';
    protected static ?int $navigationSort = 99;
    protected static ?string $modelLabel = 'Borrador de Importación';
    protected static ?string $pluralModelLabel = 'Borradores de Importación';

    public static function getNavigationBadge(): ?string
    {
        $count = ImportDraft::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            // ── Cabecera ────────────────────────────────────────────────────
            Forms\Components\Section::make('Datos del Albarán')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('matched_provider_id')
                        ->label('Proveedor')
                        ->relationship('tercero', 'nombre_comercial')
                        ->searchable()
                        ->preload()
                        ->nullable()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('document_number')
                        ->label('Nº Documento')
                        ->nullable()
                        ->columnSpan(1),

                    Forms\Components\DatePicker::make('document_date')
                        ->label('Fecha Documento')
                        ->nullable()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('provider_name')
                        ->label('Nombre detectado por IA')
                        ->disabled()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('provider_nif')
                        ->label('NIF detectado por IA')
                        ->disabled()
                        ->columnSpan(1),

                    Forms\Components\TextInput::make('total_amount')
                        ->label('Total documento')
                        ->numeric()
                        ->suffix('€')
                        ->columnSpan(1),
                ]),

            // ── Líneas ──────────────────────────────────────────────────────
            Forms\Components\Section::make('Líneas de Producto')
                ->schema([
                    Forms\Components\Repeater::make('items')
                        ->label('')
                        ->schema(static::getLineItemSchema())
                        ->columns(12)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(function (array $state): string {
                            $desc = $state['description'] ?? 'Nueva línea';
                            $qty  = $state['quantity'] ?? 1;
                            $price = number_format((float)($state['sale_price'] ?? 0), 2, ',', '.');
                            $existing = !empty($state['matched_product_id']) ? ' ⚠️' : '';
                            return "{$existing} [{$qty} ud] {$desc} — PVP: {$price} €";
                        })
                        ->addActionLabel('+ Añadir línea'),
                ]),

            // ── OCR Raw ─────────────────────────────────────────────────────
            Forms\Components\Section::make('Texto OCR original')
                ->collapsed()
                ->schema([
                    Forms\Components\Textarea::make('raw_text')
                        ->label('')
                        ->disabled()
                        ->rows(6)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function getLineItemSchema(): array
    {
        return [
            Forms\Components\TextInput::make('description')
                ->label('Descripción')
                ->required()
                ->columnSpan(3),

            Forms\Components\TextInput::make('reference')
                ->label('Referencia/SKU')
                ->columnSpan(2),

            Forms\Components\TextInput::make('quantity')
                ->label('Cant.')
                ->numeric()
                ->default(1)
                ->columnSpan(1),

            Forms\Components\TextInput::make('unit_price')
                ->label('P. Compra')
                ->numeric()
                ->suffix('€')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => static::recalculateLine($state, $set, $get))
                ->columnSpan(1),

            Forms\Components\TextInput::make('margin')
                ->label('Margen %')
                ->numeric()
                ->suffix('%')
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => static::recalculateLine(null, $set, $get))
                ->columnSpan(1),

            Forms\Components\TextInput::make('sale_price')
                ->label('PVP psicológico')
                ->numeric()
                ->suffix('€')
                ->columnSpan(1),

            Forms\Components\TextInput::make('benefit')
                ->label('Beneficio')
                ->numeric()
                ->suffix('€')
                ->disabled()
                ->columnSpan(1),

            Forms\Components\TextInput::make('vat_rate')
                ->label('IVA %')
                ->numeric()
                ->suffix('%')
                ->columnSpan(1),

            // Campo oculto con el ID del producto existente
            Forms\Components\Hidden::make('matched_product_id'),
            Forms\Components\Hidden::make('product_code'),
            Forms\Components\Hidden::make('print_label'),

            // Aviso de producto existente
            Forms\Components\Placeholder::make('existing_product_notice')
                ->label('')
                ->content(function (Forms\Get $get): \Illuminate\Support\HtmlString {
                    $productId = $get('matched_product_id');
                    if (empty($productId)) {
                        return new \Illuminate\Support\HtmlString('');
                    }
                    $product = Product::find($productId);
                    if (!$product) {
                        return new \Illuminate\Support\HtmlString('');
                    }
                    $html = sprintf(
                        '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 border border-amber-300" title="Ya existe: %s | SKU: %s | PC actual: %.2f€ | PVP actual: %.2f€ | Stock: %d">⚠️ Producto ya existe en BD</span>',
                        e($product->name),
                        e($product->sku ?? '—'),
                        (float) $product->purchase_price,
                        (float) $product->price,
                        (int) $product->stock,
                    );
                    return new \Illuminate\Support\HtmlString($html);
                })
                ->columnSpanFull()
                ->visible(fn (Forms\Get $get): bool => !empty($get('matched_product_id'))),
        ];
    }

    protected static function recalculateLine($purchasePrice, $set, $get): void
    {
        $cost   = (float)($get('unit_price') ?? 0);
        $margin = (float)($get('margin') ?? 60);
        if ($cost <= 0) return;

        // Recalculate sale price from margin
        $saleBeforeVat = $cost / (1 - ($margin / 100));
        $vatRate = (float)($get('vat_rate') ?? 21);
        $saleWithVat = $saleBeforeVat * (1 + ($vatRate / 100));
        $benefit = $saleBeforeVat - $cost;

        $set('sale_price', round($saleWithVat, 2));
        $set('benefit', round($benefit, 2));
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'   => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'rejected'  => 'Rechazado',
                        default     => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger'  => 'rejected',
                    ]),

                Tables\Columns\TextColumn::make('provider_name')
                    ->label('Proveedor (IA)')
                    ->searchable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('document_number')
                    ->label('Nº Documento')
                    ->searchable(),

                Tables\Columns\TextColumn::make('document_date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->money('EUR')
                    ->sortable(),

                Tables\Columns\TextColumn::make('items')
                    ->label('Líneas')
                    ->formatStateUsing(function ($state): string {
                        $arr = is_array($state) ? $state : (json_decode($state ?? '[]', true) ?? []);
                        return count($arr) . ' líneas';
                    }),

                Tables\Columns\TextColumn::make('expedicionCompra.expedicion.nombre')
                    ->label('Expedición')
                    ->limit(25),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending'   => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'rejected'  => 'Rechazado',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Revisar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListImportDrafts::route('/'),
            'edit'   => Pages\EditImportDraft::route('/{record}/edit'),
        ];
    }
}
