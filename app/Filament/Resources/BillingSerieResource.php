<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BillingSerieResource\Pages;
use App\Filament\Resources\BillingSerieResource\RelationManagers;
use App\Filament\Support\HasRoleAccess;
use App\Models\BillingSerie;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class BillingSerieResource extends Resource
{
    use HasRoleAccess;

    protected static string $viewPermission   = 'configuracion.view';
    protected static string $createPermission = 'configuracion.create';
    protected static string $editPermission   = 'configuracion.edit';
    protected static string $deletePermission = 'configuracion.delete';

    protected static ?string $model = BillingSerie::class;

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?string $navigationLabel = 'Series de Facturación';

    protected static ?string $modelLabel = 'Serie de Facturación';

    protected static ?string $pluralModelLabel = 'Series de Facturación';

    protected static ?string $navigationGroup = 'Gestión';

    protected static ?int $navigationSort = 31;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Identificación de la Serie')
                    ->schema([
                        Forms\Components\TextInput::make('codigo')
                            ->label('Código de Serie')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(10)
                            ->placeholder('ej: A, B, R'),
                        Forms\Components\TextInput::make('nombre')
                            ->label('Nombre Descriptivo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('ej: Serie Ordinaria A'),
                    ])->columns(2),

                Forms\Components\Section::make('Configuración Fiscal')
                    ->description('Define el comportamiento fiscal por defecto para esta serie.')
                    ->schema([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\Toggle::make('devenga_iva')
                                    ->label('Devenga IVA')
                                    ->inline(false)
                                    ->default(true),
                                Forms\Components\Toggle::make('sujeta_irpf')
                                    ->label('Sujeta a IRPF')
                                    ->inline(false)
                                    ->reactive(),
                            ]),
                        Forms\Components\Select::make('iva_defecto_id')
                            ->label('IVA por Defecto')
                            ->relationship('ivaDefecto', 'nombre', fn($query) => $query->where('tipo', 'iva')->where('activo', true))
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('irpf_defecto_id')
                            ->label('IRPF por Defecto')
                            ->relationship('irpfDefecto', 'nombre', fn($query) => $query->where('tipo', 'irpf')->where('activo', true))
                            ->searchable()
                            ->preload()
                            ->visible(fn(\Filament\Forms\Get $get) => $get('sujeta_irpf')),
                    ])->columns(2),

                Forms\Components\Section::make('Estado')
                    ->schema([
                        Forms\Components\Toggle::make('activo')
                            ->label('Serie Activa')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('codigo')
                    ->label('Serie')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nombre')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\IconColumn::make('sujeta_irpf')
                    ->label('IRPF')
                    ->boolean(),
                Tables\Columns\IconColumn::make('devenga_iva')
                    ->label('IVA')
                    ->boolean(),
                Tables\Columns\TextColumn::make('ivaDefecto.nombre')
                    ->label('IVA Defecto'),
                Tables\Columns\TextColumn::make('irpfDefecto.nombre')
                    ->label('IRPF Defecto')
                    ->state(fn ($record) => $record->sujeta_irpf ? $record->irpfDefecto?->nombre : ''),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
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
            'index' => Pages\ListBillingSeries::route('/'),
            'create' => Pages\CreateBillingSerie::route('/create'),
            'edit' => Pages\EditBillingSerie::route('/{record}/edit'),
        ];
    }
}
