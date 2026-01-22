<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    // ── Roles disponibles ─────────────────────────────────────────────────
    const ROLE_SUPERADMIN = 'superadmin';
    const ROLE_MANAGER    = 'manager';
    const ROLE_VENDEDOR   = 'vendedor';

    const ROLES = [
        self::ROLE_SUPERADMIN => 'Superadministrador',
        self::ROLE_MANAGER    => 'Manager',
        self::ROLE_VENDEDOR   => 'Vendedor',
    ];

    // ── Permisos por rol ──────────────────────────────────────────────────
    const ROLE_PERMISSIONS = [
        self::ROLE_SUPERADMIN => [
            'ventas.*', 'compras.*', 'almacen.*', 'pos.*',
            'productos.*', 'terceros.*', 'configuracion.*',
            'usuarios.*', 'dashboard.stats',
        ],
        self::ROLE_MANAGER => [
            'ventas.view', 'ventas.create', 'ventas.edit', 'ventas.delete',
            'compras.view', 'compras.create', 'compras.edit', 'compras.delete',
            'almacen.view', 'almacen.create', 'almacen.edit', 'almacen.delete',
            'productos.view', 'productos.create', 'productos.edit', 'productos.delete',
            'terceros.view', 'terceros.create', 'terceros.edit', 'terceros.delete',
            'configuracion.view', 'configuracion.create', 'configuracion.edit', 'configuracion.delete',
            'pos.view',
            'dashboard.stats',
        ],
        self::ROLE_VENDEDOR => [
            'pos.view', 'pos.operate',
            'almacen.view',
            'productos.view',
            'terceros.view',
            'ventas.view',
        ],
    ];


    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ── Filament panel access ─────────────────────────────────────────────

    /**
     * Todos los usuarios con rol definido pueden acceder al panel Filament.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, [
            self::ROLE_SUPERADMIN,
            self::ROLE_MANAGER,
            self::ROLE_VENDEDOR,
        ]);
    }

    // ── Role helpers ──────────────────────────────────────────────────────

    public function isSuperAdmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isManager(): bool
    {
        return $this->role === self::ROLE_MANAGER;
    }

    public function isVendedor(): bool
    {
        return $this->role === self::ROLE_VENDEDOR;
    }

    /**
     * Comprueba si el usuario tiene un permiso concreto.
     * Soporta wildcards: 'ventas.*' cubre 'ventas.view', 'ventas.create', etc.
     */
    public function hasPermission(string $permission): bool
    {
        $rolePerms = self::ROLE_PERMISSIONS[$this->role] ?? [];

        foreach ($rolePerms as $perm) {
            // Coincidencia exacta
            if ($perm === $permission) {
                return true;
            }

            // Coincidencia con wildcard (e.g. 'ventas.*' => 'ventas.view')
            if (str_ends_with($perm, '.*')) {
                $prefix = rtrim($perm, '.*');
                if (str_starts_with($permission, $prefix . '.')) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Comprueba si el usuario tiene AL MENOS UNO de los permisos dados.
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    // ── Relations ─────────────────────────────────────────────────────────

    /**
     * Tickets creados por este operador.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
