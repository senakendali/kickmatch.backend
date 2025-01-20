<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UserGroupsTableSeeder extends Seeder
{
    public function run()
    {
        DB::table('user_groups')->insert([
            [
                'name' => 'Admin',
                'description' => 'Administrative users with full access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Moderator',
                'description' => 'Moderators with limited access to manage content',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'User',
                'description' => 'Regular users with standard access',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }
}
