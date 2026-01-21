<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SettingResource\Pages;
use App\Filament\Resources\SettingResource\RelationManagers;
use App\Models\Setting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    
    protected static ?string $navigationLabel = 'Ajustes Avanzados';
    
    protected static ?string $modelLabel = 'Ajuste';
    
    protected static ?string $pluralModelLabel = 'Ajustes Avanzados';
    
    protected static ?string $navigationGroup = 'Sistema';
    
    protected static ?int $navigationSort = 100;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalles de Configuración')
                    ->schema([
                        Forms\Components\TextInput::make('label')
                            ->label('Configuración')
                            ->disabled()
                            ->columnSpan(1),
                        Forms\Components\TextInput::make('key')
                            ->label('Clave Interna')
                            ->disabled()
                            ->columnSpan(1),
                        Forms\Components\Textarea::make('value')
                            ->label('Valor')
                            ->required()
                            ->visible(fn ($get) => !in_array($get('key'), ['currency_position']))
                            ->columnSpanFull(),
                        
                        Forms\Components\Select::make('value')
                            ->label('Valor')
                            ->options([
                                'prefix' => 'Prefijo (Ej: € 100,00)',
                                'suffix' => 'Sufijo (Ej: 100,00 €)',
                            ])
                            ->required()
                            ->visible(fn ($get) => $get('key') === 'currency_position')
                            ->columnSpanFull(),
                    ])->columns(2)->compact(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('Configuración')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('value')
                    ->label('Valor')
                    ->limit(50),
                Tables\Columns\TextColumn::make('group')
                    ->label('Grupo')
                    ->badge()
                    ->color('info'),
            ])
            ->groups([
                'group',
            ])
            ->defaultGroup('group')
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make()->tooltip('Editar')->label(''),
            ])
            ->bulkActions([
                // Bloqueado
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageSettings::route('/'),
        ];
    }
}
