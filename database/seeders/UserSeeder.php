<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Seed de usuarios iniciales con sus roles.
     *
     * Credenciales por defecto:
     *   Superadmin  → admin@sientia.com     / password123
     *   Manager     → manager@sientia.com   / password123
     *   Vendedor    → vendedor@sientia.com  / password123
     */
    public function run(): void
    {
        // ── Superadministrador ──────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'admin@sientia.com'],
            [
                'name'     => 'Administrador',
                'password' => Hash::make('password123'),
                'role'     => User::ROLE_SUPERADMIN,
            ]
        );

        // ── Manager ─────────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'manager@sientia.com'],
            [
                'name'     => 'Manager',
                'password' => Hash::make('password123'),
                'role'     => User::ROLE_MANAGER,
            ]
        );

        // ── Vendedor ─────────────────────────────────────────────────────────
        User::updateOrCreate(
            ['email' => 'vendedor@sientia.com'],
            [
                'name'     => 'Vendedor Demo',
                'password' => Hash::make('password123'),
                'role'     => User::ROLE_VENDEDOR,
            ]
        );
    }
}
