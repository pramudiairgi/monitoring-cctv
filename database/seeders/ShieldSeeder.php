<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ShieldSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            'viewAny camera', 'view camera', 'create camera', 'update camera', 'delete camera', 'deleteAny camera',
            'viewAny category', 'view category', 'create category', 'update category', 'delete category', 'deleteAny category',
            'viewAny user', 'view user', 'create user', 'update user', 'delete user', 'deleteAny user',
            'view playback-settings',
            'view camera-overview',
        ];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }

        $roles = [
            'super_admin' => $permissions,
            'operator' => [
                'viewAny camera', 'view camera', 'create camera', 'update camera', 'delete camera', 'deleteAny camera',
                'viewAny category', 'view category', 'create category', 'update category', 'delete category', 'deleteAny category',
                'view playback-settings',
                'view camera-overview',
            ],
        ];

        foreach ($roles as $name => $perms) {
            $role = Role::firstOrCreate(['name' => $name]);
            $role->givePermissionTo($perms);
        }
    }
}
