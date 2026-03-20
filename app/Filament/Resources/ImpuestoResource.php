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

    protected static ?int $navigationSort = 30;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles del Impuesto')
                    ->schema([
                        Forms\Components\TextInput::make('nombre')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('tipo')
                            ->options([
                                'iva' => 'IVA',
                                'irpf' => 'IRPF',
                                'otros' => 'Otros',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('valor')
                            ->type('text')
                            ->inputMode('decimal')
                            ->numeric()
                            ->maxValue(100)
                            ->required()
                            ->prefix('%')
                            ->extraInputAttributes(['style' => 'width: 120px']),
                        Forms\Components\TextInput::make('recargo')
                            ->label('Reg. Equivalencia')
                            ->numeric()
                            ->postfix('%')
                            ->visible(fn ($get) => $get('tipo') === 'iva')
                            ->helperText('Usado si el cliente tiene recargo.'),
                        Forms\Components\Toggle::make('es_predeterminado')
                            ->label('Predeterminado')
                            ->required()
                            ->default(false),
                        Forms\Components\Toggle::make('activo')
                            ->label('Activo')
                            ->required()
                            ->default(true),
                    ])->columns(5)->compact(),
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
