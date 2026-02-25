<?php

namespace App\Filament\Resources;

use App\Filament\Resources\LabelFormatResource\Pages;
use App\Filament\Resources\LabelFormatResource\RelationManagers;
use App\Filament\Support\HasRoleAccess;
use App\Models\LabelFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class LabelFormatResource extends Resource
{
    use HasRoleAccess;

    protected static string $viewPermission   = 'almacen.view';
    protected static string $createPermission = 'almacen.create';
    protected static string $editPermission   = 'almacen.edit';
    protected static string $deletePermission = 'almacen.delete';

    protected static ?string $model = LabelFormat::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';
    
    protected static ?string $navigationGroup = 'Almacén';

    protected static ?string $navigationLabel = 'Formatos de Etiquetas';

    protected static ?string $modelLabel = 'Formato de Etiqueta';

    protected static ?string $pluralModelLabel = 'Formatos de Etiquetas';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('tipo')
                    ->required(),
                Forms\Components\TextInput::make('document_width')
                    ->required()
                    ->numeric()
                    ->default(210.00),
                Forms\Components\TextInput::make('document_height')
                    ->required()
                    ->numeric()
                    ->default(297.00),
                Forms\Components\TextInput::make('label_width')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('label_height')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('labels_per_row')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('labels_per_column')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('labels_per_sheet')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('margin_top')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('margin_bottom')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('margin_left')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('margin_right')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('horizontal_spacing')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('vertical_spacing')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('manufacturer')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('model_number')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\Toggle::make('activo')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('tipo'),
                Tables\Columns\TextColumn::make('document_width')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('document_height')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label_width')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label_height')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('labels_per_row')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('labels_per_column')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('labels_per_sheet')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_top')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_bottom')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_left')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('margin_right')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('horizontal_spacing')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vertical_spacing')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->searchable(),
                Tables\Columns\TextColumn::make('model_number')
                    ->searchable(),
                Tables\Columns\IconColumn::make('activo')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
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
            'index' => Pages\ListLabelFormats::route('/'),
            'create' => Pages\CreateLabelFormat::route('/create'),
            'edit' => Pages\EditLabelFormat::route('/{record}/edit'),
        ];
    }
}
