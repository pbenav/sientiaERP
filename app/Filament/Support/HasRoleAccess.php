<?php

namespace App\Filament\Support;

use App\Models\User;

/**
 * Trait reutilizable para controlar el acceso a los Resources de Filament
 * según los permisos del usuario autenticado.
 *
 * Cada Resource que lo use debe declarar:
 *   protected static string $viewPermission   = 'grupo.view';
 *   protected static string $createPermission = 'grupo.create';
 *   protected static string $editPermission   = 'grupo.edit';
 *   protected static string $deletePermission = 'grupo.delete';
 *
 * Si no se declara una propiedad concreta, el acceso se deniega.
 */
trait HasRoleAccess
{
    public static function canViewAny(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        // Superadmin siempre tiene acceso
        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::$viewPermission ?? null;
        return $permission ? $user->hasPermission($permission) : false;
    }

    public static function canCreate(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::$createPermission ?? null;
        return $permission ? $user->hasPermission($permission) : false;
    }

    public static function canEdit($record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::$editPermission ?? null;
        return $permission ? $user->hasPermission($permission) : false;
    }

    public static function canDelete($record): bool
    {
        /** @var User|null $user */
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = static::$deletePermission ?? null;
        return $permission ? $user->hasPermission($permission) : false;
    }

    public static function canDeleteAny(): bool
    {
        return static::canDelete(null);
    }
}
