<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class PermissionSeeder extends Seeder
{
    public function run()
    {
        // Define pages and permissions
        $pages = [
            'dashboard' => ['view dashboard', 'register', 'download', 'view insight'],
            'contingent' => ['view contingent', 'create contingent', 'edit contingent', 'delete contingent'],
            'member'    => ['view member', 'create member', 'edit member', 'delete member'],
            'payment'   => ['view payment', 'create payment', 'edit payment', 'delete payment', 'upload payment struk', 'confirm payment'],
            'classes'   => ['view classes', 'create classes', 'edit classes', 'delete classes'],
            'match-clasification' => ['view match-clasification', 'create match-clasification', 'edit match-clasification', 'delete match-clasification'],
        ];

        // Create permissions for each page
        foreach ($pages as $page => $permissions) {
            foreach ($permissions as $permission) {
                Permission::firstOrCreate(
                    ['name' => $permission],  // Permission name (e.g., 'view dashboard')
                    ['name' => $permission]   // Create permission if it doesn't exist
                );
            }
        }

        // Define roles and their corresponding permissions
        $roles = [
            'owner' => [
                'view dashboard', 'register', 'download', 'view insight', 'view contingent', 'create contingent', 'edit contingent', 'delete contingent',
                'view member', 'create member', 'edit member', 'delete member',
                'view payment', 'create payment', 'edit payment', 'delete payment', 'upload payment struk',  
                'view classes', 'create classes', 'edit classes', 'delete classes',
                'view match-clasification', 'create match-clasification', 'edit match-clasification', 'delete match-clasification',
            ],
            'admin' => [
                'view dashboard', 'register', 'download', 'view insight', 'view contingent', 'create contingent', 'edit contingent', 'delete contingent',
                'view member', 'create member', 'edit member', 'delete member',
                'view payment', 'confirm payment', 'create payment', 'edit payment', 'delete payment', 'upload payment struk', 
                'view classes', 'create classes', 'edit classes', 'delete classes',
                'view match-clasification', 'create match-clasification', 'edit match-clasification', 'delete match-clasification',
            ],
            'user' => [
                'view dashboard', 'register', 'download', 
                'view contingent', 'create contingent', 'edit contingent', 
                'view member', 'create member', 'edit member', 
                'view payment', 'create payment', 'edit payment', 'delete payment', 'upload payment struk',                
            ]
        ];

        // Create roles and assign permissions
        foreach ($roles as $roleName => $rolePermissions) {
            $role = Role::firstOrCreate(
                ['name' => $roleName],
                ['name' => $roleName]
            );

            // Sync permissions for this role
            $role->syncPermissions($rolePermissions);
        }

        // Optional: Assign roles to users
        /*$admin = \App\Models\User::find(1);  
        if ($admin) {
            $admin->assignRole('owner');
        }

        $editor = \App\Models\User::find(2);  
        if ($editor) {
            $editor->assignRole('editor');
        }*/
    }
}
