<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Désactiver les contraintes de clé étrangère temporairement
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Vider les tables avec truncate pour réinitialiser les auto-increments
        Permission::truncate();
        Role::truncate();
        
        // Ne supprimez pas tous les utilisateurs par défaut (risque en production)
        // User::truncate(); // À utiliser avec précaution
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Réinitialiser le cache des permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Permissions groupées par module
        $permissions = [
            'roles' => ['view', 'create', 'update', 'delete', 'assign'],
            'users' => ['view', 'create', 'update', 'delete', 'activate'],
            'permissions' => ['view', 'create', 'update', 'delete', 'assign'],
            // Ajoutez d'autres modules au besoin
        ];

        $createdPermissions = [];
        
        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                $name = "{$module}.{$action}";
                $createdPermissions[] = Permission::firstOrCreate([
                    'name' => $name,
                    'guard_name' => 'web',
                  
                ]);
            }
        }

        // Création des rôles avec des permissions appropriées
        $roles = [
            'Super Admin' => ['*'], // Toutes les permissions
            'Admin' => [
                'users.view', 'users.create', 'users.update',
                'roles.view', 'roles.assign',
                'permissions.view',
            ],
            'Manager' => [
                'users.view', 'users.update',
            ],
            'User' => [
                'users.view',
            ],
        ];

        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate([
                'name' => $roleName,
                'guard_name' => 'web',
            ]);

            if ($rolePermissions === ['*']) {
                $role->givePermissionTo(Permission::all());
            } else {
                $role->givePermissionTo($rolePermissions);
            }
        }

        // Création d'un utilisateur admin par défaut (optionnel)
        $this->createDefaultAdmin();
    }

    /**
     * Crée un utilisateur admin par défaut
     */
    protected function createDefaultAdmin(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('SecurePassword123!'), // Utilisez un mot de passe fort
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole('Admin');
        
        // Création d'un utilisateur test (optionnel)
        $this->createTestUser();
    }

    /**
     * Crée un utilisateur test avec le rôle User
     */
    protected function createTestUser(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('UserPassword123!'),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole('User');
    }
}