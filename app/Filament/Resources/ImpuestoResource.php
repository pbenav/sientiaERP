<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ImpuestoResource\Pages;
use App\Filament\Resources\ImpuestoResource\RelationManagers;
use App\Filament\Support\HasRoleAccess;
use App\Models\Impuesto;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ImpuestoResource extends Resource
{
    use HasRoleAccess;

    protected static string $viewPermission   = 'configuracion.view';
    protected static string $createPermission = 'configuracion.create';
    protected static string $editPermission   = 'configuracion.edit';
    protected static string $deletePermission = 'configuracion.delete';

    protected static ?string $model = Impuesto::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Impuestos';

    protected static ?string $modelLabel = 'Impuesto';

    protected static ?string $pluralModelLabel = 'Impuestos';

    protected static ?string $navigationGroup = 'Gestión';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Impuesto')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('nombre')
                                    ->label('Nombre del Impuesto')
                                    ->placeholder('Ej: IVA 21%, IRPF 15%, ...')
                                    ->required()
                                    ->maxLength(255),
                                
                                Forms\Components\Select::make('tipo')
                                    ->label('Tipo de Impuesto')
                                    ->options([
                                        'iva' => 'IVA',
                                        'irpf' => 'IRPF',
                                        'otros' => 'Otros',
                                    ])
                                    ->required()
                                    ->live(),

                                Forms\Components\TextInput::make('valor')
                                    ->label('Valor Porcentual')
                                    ->numeric()
                                    ->prefix('%')
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(100),
                            ]),

                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\TextInput::make('recargo')
                                    ->label('Recargo de Equivalencia')
                                    ->numeric()
                                    ->prefix('%')
                                    ->default(0)
                                    ->dehydrateStateUsing(fn ($state) => $state ?? 0)
                                    ->visible(fn ($get) => $get('tipo') === 'iva')
                                    ->helperText('Solo para clientes en régimen de RE. Deje en 0 si no aplica.'),
                                
                                Forms\Components\Toggle::make('es_predeterminado')
                                    ->label('Marcar como Predeterminado')
                                    ->helperText('Se usará por defecto en nuevos productos.')
                                    ->inline(false)
                                    ->default(false),

                                Forms\Components\Toggle::make('activo')
                                    ->label('Estado Activo')
                                    ->helperText('Si se desactiva, no aparecerá en el TPV.')
                                    ->inline(false)
                                    ->default(true),
                            ]),
                    ])
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo')
                    ->label('Tipo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('valor')
                    ->label('Valor')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('recargo')
                    ->label('Recargo RE')
                    ->numeric()
                    ->suffix('%')
                    ->toggleable(),
                Tables\Columns\IconColumn::make('es_predeterminado')
                    ->label('Predeterminado')
                    ->boolean(),
                Tables\Columns\IconColumn::make('activo')
                    ->label('Activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListImpuestos::route('/'),
            'create' => Pages\CreateImpuesto::route('/create'),
            'edit' => Pages\EditImpuesto::route('/{record}/edit'),
        ];
    }
}
