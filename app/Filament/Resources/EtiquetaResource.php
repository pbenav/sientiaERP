<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EtiquetaResource\Pages;
use App\Models\Documento;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EtiquetaResource extends Resource
{
    protected static ?string $model = Documento::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Almacén';

    protected static ?string $navigationLabel = 'Documentos de Etiquetas';

    protected static ?string $modelLabel = 'Documento de Etiquetas';

    protected static ?string $pluralModelLabel = 'Documentos de Etiquetas';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('tipo', 'etiqueta');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('General')
                    ->schema([
                        Forms\Components\TextInput::make('numero')
                            ->label('Número')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('Auto-generado'),
                        Forms\Components\DatePicker::make('fecha')
                            ->label('Fecha')
                            ->default(now())
                            ->required(),
                        Forms\Components\Select::make('label_format_id')
                            ->label('Formato de Etiqueta')
                            ->options(\App\Models\LabelFormat::where('activo', true)->pluck('nombre', 'id'))
                            ->required()
                            ->searchable()
                            ->preload()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nombre')->required(),
                                Forms\Components\TextInput::make('ancho_etiqueta')->numeric()->required(),
                                Forms\Components\TextInput::make('alto_etiqueta')->numeric()->required(),
                                Forms\Components\TextInput::make('filas')->numeric()->required(),
                                Forms\Components\TextInput::make('columnas')->numeric()->required(),
                            ])
                            ->createOptionUsing(fn (array $data) => \App\Models\LabelFormat::create([...$data, 'activo' => true])->id)
                            ->live(),
                        Forms\Components\TextInput::make('fila_inicio')
                            ->label('Fila de Inicio')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Forms\Components\TextInput::make('columna_inicio')
                            ->label('Columna de Inicio')
                            ->numeric()
                            ->default(1)
                            ->minValue(1)
                            ->required(),
                        Forms\Components\Hidden::make('tipo')
                            ->default('etiqueta'),
                    ])->columns(3),

                Forms\Components\View::make('filament.components.document-lines-header')
                    ->columnSpanFull()
                    ->viewData(['isLabel' => true]),

                Forms\Components\Repeater::make('lineas')
                    ->relationship()
                    ->schema(\App\Filament\RelationManagers\LineasRelationManager::getLineFormSchema(isLabel: true))
                    ->columns(1)
                    ->defaultItems(0)
                    ->live()
                    ->hiddenLabel()
                    ->extraAttributes(['class' => 'document-lines-repeater'])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Observaciones')
                    ->schema([
                        Forms\Components\Textarea::make('observaciones')
                            ->label('Observaciones')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('numero')
                    ->label('Número')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fecha')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(50),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\Action::make('pdf')
                    ->label('Descargar PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('success')
                    ->url(fn (Documento $record): string => route('etiquetas.pdf', ['record' => $record->id]))
                    ->openUrlInNewTab(),
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListEtiquetas::route('/'),
            'create' => Pages\CreateEtiqueta::route('/create'),
            'edit' => Pages\EditEtiqueta::route('/{record}/edit'),
        ];
    }
}
