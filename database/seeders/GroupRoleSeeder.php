<?php

// database/seeders/GroupRoleSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserGroup;
use Spatie\Permission\Models\Role;

class GroupRoleSeeder extends Seeder
{
    public function run()
    {
        // Create or update roles
        $ownerRole = Role::updateOrCreate(['name' => 'owner']);
        $adminRole = Role::updateOrCreate(['name' => 'admin']);
        $userRole = Role::updateOrCreate(['name' => 'user']);
    
        // Create or update groups
        $ownerGroup = UserGroup::updateOrCreate(['name' => 'Owner']);
        $picGroup = UserGroup::updateOrCreate(['name' => 'Event PIC']);
        $userGroup = UserGroup::updateOrCreate(['name' => 'User']);
    
        // Assign roles to groups
        $ownerGroup->roles()->attach($ownerRole);
        $picGroup->roles()->attach($adminRole);
        $userGroup->roles()->attach($userRole);
    }
    
}

