<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class NavigationMenusSeeder extends Seeder
{
    public function run(): void
    {
        // Matikan FK dulu biar truncate gak error
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        DB::table('navigation_menus')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $now = Carbon::now();

        /**
         * STEP 1: PARENT MENU (parent_id = null)
         */
        $parents = [
            [
                'id'         => 1,
                'name'       => 'Dashboard',
                'role_name'  => 'owner, eo, manager',
                'url'        => '',
                'parent_id'  => null,
                'order'      => 1,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-01-04 10:51:45'),
                'updated_at' => Carbon::parse('2025-01-05 06:52:59'),
            ],
            [
                'id'         => 2,
                'name'       => 'Master',
                'role_name'  => 'owner, admin',
                'url'        => '#',
                'parent_id'  => null,
                'order'      => 2,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-01-04 10:53:48'),
                'updated_at' => Carbon::parse('2025-01-04 10:53:48'),
            ],
            [
                'id'         => 3,
                'name'       => 'Teams',
                'role_name'  => 'owner, manager',
                'url'        => 'teams',
                'parent_id'  => null,
                'order'      => 3,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-01-04 10:54:25'),
                'updated_at' => Carbon::parse('2025-01-04 10:54:25'),
            ],
            [
                'id'         => 5,
                'name'       => 'Match Settings',
                'role_name'  => 'owner',
                'url'        => '#',
                'parent_id'  => null,
                'order'      => 5,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-01-05 06:44:09'),
                'updated_at' => Carbon::parse('2025-01-05 06:44:09'),
            ],
            [
                'id'         => 6,
                'name'       => 'Team Members',
                'role_name'  => 'user, owner, manager',
                'url'        => 'team-members',
                'parent_id'  => null,
                'order'      => 6,
                'type'       => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 7,
                'name'       => 'Payment',
                'role_name'  => 'owner, manager, user',
                'url'        => 'payment',
                'parent_id'  => null,
                'order'      => 7,
                'type'       => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 12,
                'name'       => 'System Settings',
                'role_name'  => 'owner',
                'url'        => '#',
                'parent_id'  => null,
                'order'      => 6,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-03-28 13:43:53'),
                'updated_at' => Carbon::parse('2025-03-28 13:43:53'),
            ],
            [
                'id'         => 14,
                'name'       => 'Tournament Settings',
                'role_name'  => 'owner, eo',
                'url'        => '#',
                'parent_id'  => null,
                'order'      => 8,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-07 13:24:17'),
                'updated_at' => Carbon::parse('2025-04-07 13:24:17'),
            ],
        ];

        DB::table('navigation_menus')->insert($parents);

        /**
         * STEP 2: CHILD MENU (punya parent_id)
         */
        $children = [
            [
                'id'         => 8,
                'name'       => 'Classes',
                'role_name'  => 'owner, admin',
                'url'        => 'classes',
                'parent_id'  => 2,
                'order'      => 1,
                'type'       => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 9,
                'name'       => 'Match Clasification',
                'role_name'  => 'owner, admin',
                'url'        => 'match-clasification',
                'parent_id'  => 14,
                'order'      => 1,
                'type'       => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 10,
                'name'       => 'Tanding',
                'role_name'  => 'owner, admin',
                'url'        => 'tanding',
                'parent_id'  => 5,
                'order'      => 2,
                'type'       => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 13,
                'name'       => 'Navigation',
                'role_name'  => 'owner, admin',
                'url'        => 'navigation',
                'parent_id'  => 12,
                'order'      => 1,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-03-28 13:45:39'),
                'updated_at' => Carbon::parse('2025-03-28 13:45:39'),
            ],
            [
                'id'         => 15,
                'name'       => 'Tournament',
                'role_name'  => 'owner, admin, eo',
                'url'        => 'tournament',
                'parent_id'  => 14,
                'order'      => 1,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-07 13:29:01'),
                'updated_at' => Carbon::parse('2025-04-07 13:29:01'),
            ],
            [
                'id'         => 18,
                'name'       => 'Tournament Category',
                'role_name'  => 'owner, admin',
                'url'        => 'tournament-category',
                'parent_id'  => 14,
                'order'      => 2,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-08 07:18:49'),
                'updated_at' => Carbon::parse('2025-04-08 07:18:49'),
            ],
            [
                'id'         => 19,
                'name'       => 'Tournament Activity',
                'role_name'  => 'owner, admin, eo',
                'url'        => 'tournament-activity',
                'parent_id'  => 14,
                'order'      => 3,
                'type'       => 'public',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 20,
                'name'       => 'Contact Person',
                'role_name'  => 'owner, admin, eo',
                'url'        => 'contact-person',
                'parent_id'  => 14,
                'order'      => 4,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-13 08:53:20'),
                'updated_at' => Carbon::parse('2025-04-13 08:53:20'),
            ],
            [
                'id'         => 21,
                'name'       => 'Tournament Arena',
                'role_name'  => 'owner, admin, eo',
                'url'        => 'tournament-arena',
                'parent_id'  => 14,
                'order'      => 5,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-13 08:53:48'),
                'updated_at' => Carbon::parse('2025-04-13 08:53:48'),
            ],
            [
                'id'         => 22,
                'name'       => 'Tournament Schedule',
                'role_name'  => 'owner, admin, eo',
                'url'        => 'tournament-schedule',
                'parent_id'  => 14,
                'order'      => 7,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-04-13 21:23:23'),
                'updated_at' => Carbon::parse('2025-04-13 21:23:23'),
            ],
            [
                'id'         => 23,
                'name'       => 'Seni',
                'role_name'  => 'owner, admin',
                'url'        => 'seni',
                'parent_id'  => 5,
                'order'      => 2,
                'type'       => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id'         => 24,
                'name'       => 'Blogs',
                'role_name'  => '',
                'url'        => 'blogs',
                'parent_id'  => 2,
                'order'      => 2,
                'type'       => 'admin',
                'created_at' => Carbon::parse('2025-12-02 02:23:46'),
                'updated_at' => Carbon::parse('2025-12-02 02:24:20'),
            ],
        ];

        DB::table('navigation_menus')->insert($children);
    }
}
