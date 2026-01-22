<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Support\HasRoleAccess;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserResource extends Resource
{
    use HasRoleAccess;

    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'Usuario';

    protected static ?string $pluralModelLabel = 'Usuarios';

    protected static ?string $navigationGroup = 'Administración';

    protected static ?int $navigationSort = 1;

    // Solo superadmin puede ver y gestionar usuarios
    protected static string $viewPermission   = 'usuarios.view';
    protected static string $createPermission = 'usuarios.create';
    protected static string $editPermission   = 'usuarios.edit';
    protected static string $deletePermission = 'usuarios.delete';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Datos personales')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\TextInput::make('email')
                        ->label('Correo electrónico')
                        ->email()
                        ->required()
                        ->unique(User::class, 'email', ignoreRecord: true)
                        ->maxLength(255),
                ])
                ->columns(2),

            Forms\Components\Section::make('Acceso')
                ->schema([
                    Forms\Components\Select::make('role')
                        ->label('Rol')
                        ->options(User::ROLES)
                        ->required()
                        ->native(false)
                        ->helperText(fn (?string $state): string => match ($state) {
                            User::ROLE_SUPERADMIN => '⚠️ Acceso total al sistema, incluyendo gestión de usuarios y configuración.',
                            User::ROLE_MANAGER    => 'Acceso a ventas, compras, almacén, terceros y estadísticas.',
                            User::ROLE_VENDEDOR   => 'Acceso únicamente al TPV y consulta de productos.',
                            default               => '',
                        }),

                    Forms\Components\TextInput::make('password')
                        ->label('Contraseña')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $operation) => $operation === 'create')
                        ->minLength(8)
                        ->helperText('Mínimo 8 caracteres. Deja en blanco para no cambiar la contraseña.'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('role')
                    ->label('Rol')
                    ->formatStateUsing(fn (string $state): string => User::ROLES[$state] ?? $state)
                    ->colors([
                        'danger'  => User::ROLE_SUPERADMIN,
                        'warning' => User::ROLE_MANAGER,
                        'success' => User::ROLE_VENDEDOR,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Alta')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Rol')
                    ->options(User::ROLES),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('')->tooltip('Editar'),
                Tables\Actions\DeleteAction::make()->label('')->tooltip('Eliminar'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
