<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Filament\Support\HasRoleAccess;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class TicketResource extends Resource
{
    use HasRoleAccess;

    // TPV también accesible para vendedores
    protected static string $viewPermission   = 'pos.view';
    protected static string $createPermission = 'pos.operate';
    protected static string $editPermission   = 'pos.operate';
    protected static string $deletePermission = 'pos.operate';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $model = Ticket::class;

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    protected static ?string $navigationLabel = 'TPV';

    protected static ?string $modelLabel = 'TPV';

    protected static ?string $pluralModelLabel = 'TPV';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->label('Operador')
                    ->relationship('user', 'name')
                    ->required()
                    ->disabled(),
                
                Forms\Components\Select::make('tercero_id')
                    ->label('Cliente')
                    ->options(function () {
                        return \App\Models\Tercero::clientes()
                            ->activos()
                            ->orderBy('nombre_comercial')
                            ->limit(100)
                            ->pluck('nombre_comercial', 'id');
                    })
                    ->searchable(),
                
                Forms\Components\TextInput::make('session_id')
                    ->label('ID de Sesión')
                    ->disabled(),
                
                Forms\Components\Select::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierto',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                    ])
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Nº Ticket')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Operador')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tercero.nombre_comercial')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),
                
                Tables\Columns\BadgeColumn::make('status')
                    ->label('Estado')
                    ->colors([
                        'warning' => 'open',
                        'success' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'open' => 'Abierto',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                    }),
                
                Tables\Columns\TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state))
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Método de Pago')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'cash' => 'Efectivo',
                        'card' => 'Tarjeta',
                        'mixed' => 'Mixto',
                        default => '-',
                    })
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completado')
                    ->dateTime()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'open' => 'Abierto',
                        'completed' => 'Completado',
                        'cancelled' => 'Cancelado',
                    ]),
                
                Tables\Filters\SelectFilter::make('user')
                    ->label('Operador')
                    ->relationship('user', 'name'),
                
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('Desde'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('Hasta'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('generarAlbaran')
                    ->label('')
                    ->tooltip('Generar Albarán')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->requiresConfirmation()
                    ->visible(fn (Ticket $record) => $record->status === 'completed' && !$record->hasInvoice())
                    ->action(function (Ticket $record) {
                        $items = $record->items()->get();
                        
                        if ($items->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('El ticket no tiene líneas para generar el albarán')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        $terceroId = $record->tercero_id;
                        if (!$terceroId) {
                            $terceroId = \App\Models\Setting::get('pos_default_tercero_id');
                        }
                        
                        if (!$terceroId) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('No se puede generar albarán: el ticket no tiene cliente asignado')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Crear documento tipo albaran
                        $albaran = \App\Models\Documento::create([
                            'tipo' => 'albaran',
                            'tercero_id' => $terceroId,
                            'user_id' => auth()->id(),
                            'estado' => 'borrador',
                            'fecha' => now(),
                            'observaciones' => "Generada automáticamente desde Ticket #{$record->numero}\nFecha ticket: {$record->created_at->format('d/m/Y H:i')}",
                        ]);
                        
                        // Copiar líneas
                        $orden = 1;
                        foreach ($items as $item) {
                            $productoNombre = $item->product ? $item->product->name : 'Producto eliminado';
                            $productoCodigo = $item->product ? $item->product->sku : '';
                            
                            \App\Models\DocumentoLinea::create([
                                'documento_id' => $albaran->id,
                                'producto_id' => $item->product_id,
                                'orden' => $orden++,
                                'codigo' => $productoCodigo,
                                'descripcion' => $productoNombre,
                                'cantidad' => $item->quantity,
                                'precio_unitario' => $item->unit_price,
                                'descuento' => 0,
                                'iva' => 21,
                            ]);
                        }
                        
                        $albaran->recalcularTotales();
                        $albaran->confirmar();
                        
                        // Guardar referencia
                        $record->documento_id = $albaran->id;
                        $record->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Albarán creado y confirmado')
                            ->body("Albarán {$albaran->numero} creado. Total: " . number_format($albaran->total, 2, ',', '.') . " €.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('generarFactura')
                    ->label('')
                    ->tooltip('Generar Factura')
                    ->icon('heroicon-o-document-text')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (Ticket $record) => $record->status === 'completed' && !$record->hasInvoice())
                    ->action(function (Ticket $record) {
                        // Cargar items del ticket
                        $items = $record->items()->get();
                        
                        if ($items->isEmpty()) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('El ticket no tiene líneas para generar la factura')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        
                        // Determinar el tercero para la factura
                        $terceroId = $record->tercero_id;
                        if (!$terceroId) {
                            // Si el ticket no tiene cliente, usar el cliente POS por defecto
                            $terceroId = \App\Models\Setting::get('pos_default_tercero_id');
                        }
                        
                        if (!$terceroId) {
                            \Filament\Notifications\Notification::make()
                                ->title('Error')
                                ->body('No se puede generar factura: el ticket no tiene cliente asignado')
                                ->danger()
                                ->send();
                            return;
                        }
                        
                        // Crear documento tipo factura en borrador
                        $factura = \App\Models\Documento::create([
                            'tipo' => 'factura',
                            'tercero_id' => $terceroId,
                            'user_id' => auth()->id(),
                            'estado' => 'borrador',
                            'fecha' => now(),
                            'observaciones' => "Generada automáticamente desde Ticket #{$record->numero}\nFecha ticket: {$record->created_at->format('d/m/Y H:i')}",
                        ]);
                        
                        // Copiar líneas del ticket a la factura
                        $orden = 1;
                        foreach ($items as $item) {
                            // Obtener el nombre del producto, con fallback si no existe
                            $productoNombre = $item->product ? $item->product->name : 'Producto eliminado';
                            $productoCodigo = $item->product ? $item->product->sku : '';
                            
                            $linea = \App\Models\DocumentoLinea::create([
                                'documento_id' => $factura->id,
                                'producto_id' => $item->product_id,
                                'orden' => $orden++,
                                'codigo' => $productoCodigo,
                                'descripcion' => $productoNombre,
                                'cantidad' => $item->quantity,
                                'precio_unitario' => $item->unit_price,
                                'descuento' => 0,
                                'iva' => 21, // Valor por defecto
                            ]);
                        }
                        
                        // Recalcular totales
                        $factura->recalcularTotales();
                        
                        // CONFIRMAR FACTURA AUTOMÁTICAMENTE (asignar número)
                        $factura->confirmar();
                        
                        // Guardar referencia a factura en el ticket
                        $record->documento_id = $factura->id;
                        $record->save();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Factura creada y confirmada')
                            ->body("Factura {$factura->numero} creada. Total: " . number_format($factura->total, 2, ',', '.') . " €.")
                            ->success()
                            ->send();
                    }),
                Tables\Actions\Action::make('mostrarEnPOS')
                    ->label('')
                    ->tooltip('Mostrar en POS')
                    ->icon('heroicon-o-computer-desktop')
                    ->color('warning')
                    ->url(fn ($record) => TicketResource::getUrl('create', ['ticket_id' => $record->id])),
                Tables\Actions\ViewAction::make()->label('')->tooltip('Ver'),
                Tables\Actions\EditAction::make()->label('')->tooltip('Editar')->visible(fn (Ticket $record) => !$record->hasInvoice()),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Borrar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Información del Ticket')
                    ->schema([
                        Infolists\Components\TextEntry::make('numero')->label('Número'),
                        Infolists\Components\TextEntry::make('session_id')->label('ID de Sesión'),
                        Infolists\Components\TextEntry::make('user.name')->label('Operador'),
                        Infolists\Components\TextEntry::make('tercero.nombre_comercial')->label('Cliente'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'open' => 'Abierto',
                                'completed' => 'Completado',
                                'cancelled' => 'Cancelado',
                            }),
                    ])->columns(2),
                
                Infolists\Components\Section::make('Productos')
                    ->schema([
                        Infolists\Components\ViewEntry::make('items')
                            ->label('')
                            ->view('filament.infolists.ticket-items-table'),
                    ]),
                
                Infolists\Components\Section::make('Totales')
                    ->schema([
                        Infolists\Components\TextEntry::make('subtotal')->label('Subtotal')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state)),
                        Infolists\Components\TextEntry::make('tax')->label('IVA')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state)),
                        Infolists\Components\TextEntry::make('total')->label('Total')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state)),
                        Infolists\Components\TextEntry::make('payment_method')
                            ->label('Método de Pago')
                            ->formatStateUsing(fn (?string $state): string => match ($state) {
                                'cash' => 'Efectivo',
                                'card' => 'Tarjeta',
                                'mixed' => 'Mixto',
                                default => '-',
                            }),
                        Infolists\Components\TextEntry::make('amount_paid')->label('Pagado')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state)),
                        Infolists\Components\TextEntry::make('change_given')->label('Cambio')->formatStateUsing(fn ($state) => \App\Helpers\NumberFormatHelper::formatCurrency($state)),
                    ])->columns(3),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
