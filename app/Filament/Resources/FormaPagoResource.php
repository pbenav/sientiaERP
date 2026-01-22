<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FormaPagoResource\Pages;
use App\Models\FormaPago;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class FormaPagoResource extends Resource
{
    protected static ?string $model = FormaPago::class;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Formas de Pago';

    protected static ?string $modelLabel = 'Forma de Pago';

    protected static ?string $pluralModelLabel = 'Formas de Pago';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 32;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Información General')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50)
                            ->placeholder('ej: transferencia_30_60'),

                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('ej: Transferencia 30-60 días'),

                        Forms\Components\Select::make('tipo')
                            ->label('Tipo')
                            ->options([
                                'contado' => 'Contado',
                                'efectivo' => 'Efectivo',
                                'transferencia' => 'Transferencia Bancaria',
                                'tarjeta' => 'Tarjeta',
                                'pagare' => 'Pagaré',
                                'recibo_bancario' => 'Recibo Bancario',
                            ])
                            ->required()
                            ->default('transferencia'),

                        Forms\Components\Toggle::make('activo')
                            ->label('Activa')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),

                Forms\Components\Section::make('Tramos de Pago')
                    ->description('Define los plazos y porcentajes de pago. La suma de porcentajes debe ser 100%.')
                    ->schema([
                        Forms\Components\Repeater::make('tramos')
                            ->label('Tramos')
                            ->schema([
                                Forms\Components\TextInput::make('dias')
                                    ->label('Días')
                                    ->numeric()
                                    ->required()
                                    ->default(0)
                                    ->minValue(0)
                                    ->suffix('días')
                                    ->helperText('Días desde la fecha del documento'),

                               Forms\Components\TextInput::make('porcentaje')
                                    ->label('Porcentaje')
                                    ->numeric()
                                    ->required()
                                    ->default(100)
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->suffix('%')
                                    ->step(0.01),
                            ])
                            ->columns(2)
                            ->defaultItems(1)
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => 
                                isset($state['dias'], $state['porcentaje']) 
                                    ? "{$state['porcentaje']}% a {$state['dias']} días" 
                                    : null
                            )
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Descripción')
                    ->schema([
                        Forms\Components\Textarea::make('descripcion')
                            ->label('Descripción')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder('Descripción adicional de la forma de pago'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                Tables\Columns\BadgeColumn::make('tipo')
                    ->label('Tipo')
                    ->colors([
                        'success' => 'contado',
                        'success' => 'efectivo',
                        'primary' => 'transferencia',
                        'info' => 'tarjeta',
                        'warning' => 'pagare',
                        'secondary' => 'recibo_bancario',
                    ]),

                Tables\Columns\TextColumn::make('numero_tramos')
                    ->label('Tramos')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('plazo_maximo')
                    ->label('Plazo Máx.')
                    ->suffix(' días')
                    ->sortable(),

                Tables\Columns\IconColumn::make('activo')
                    ->label('Activa')
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tipo')
                    ->options([
                        'contado' => 'Contado',
                        'efectivo' => 'Efectivo',
                        'transferencia' => 'Transferencia',
                        'tarjeta' => 'Tarjeta',
                        'pagare' => 'Pagaré',
                        'recibo_bancario' => 'Recibo Bancario',
                    ]),

                Tables\Filters\TernaryFilter::make('activo')
                    ->label('Activa')
                    ->placeholder('Todas')
                    ->trueLabel('Solo activas')
                    ->falseLabel('Solo inactivas'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
                Tables\Actions\DeleteAction::make()->tooltip('Borrar')->label(''),
                Tables\Actions\RestoreAction::make()->tooltip('Restaurar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('nombre');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFormaPagos::route('/'),
            'create' => Pages\CreateFormaPago::route('/create'),
            'edit' => Pages\EditFormaPago::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
