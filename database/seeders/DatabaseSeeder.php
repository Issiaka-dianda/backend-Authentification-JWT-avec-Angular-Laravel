<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Delete all existing users
        User::query()->delete();
        
        // Créer des utilisateurs
        User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('12345678'),
        ]);

        // Créer des permissions
        $permission1 = Permission::create(['name' => 'edit articles']);
        $permission2 = Permission::create(['name' => 'delete articles']);

        // Créer un rôle et lui attribuer des permissions
        $role = Role::create(['name' => 'admin']);
        $role->givePermissionTo($permission1);
        $role->givePermissionTo($permission2);

        // Attribuer un rôle à un utilisateur
        $user->assignRole('admin');
    }
}
