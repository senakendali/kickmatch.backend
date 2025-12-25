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
        $adminRole = Role::updateOrCreate(['name' => 'eo']);
        $userRole = Role::updateOrCreate(['name' => 'manager']);
    
        // Create or update groups
        $ownerGroup = UserGroup::updateOrCreate(['name' => 'Owner']);
        $picGroup = UserGroup::updateOrCreate(['name' => 'Event Organizer']);
        $userGroup = UserGroup::updateOrCreate(['name' => 'Team Manager']);
    
        // Assign roles to groups
        $ownerGroup->roles()->attach($ownerRole);
        $picGroup->roles()->attach($adminRole);
        $userGroup->roles()->attach($userRole);
    }
    
}

