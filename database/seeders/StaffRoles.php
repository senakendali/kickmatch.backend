<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Seeder;

class StaffRoles extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('staff_roles')->upsert([
        ['name'=>'Head Coach','slug'=>'head_coach'],
        ['name'=>'Assistant Coach','slug'=>'assistant_coach'],
        ['name'=>'Goalkeeper Coach','slug'=>'goalkeeper_coach'],
        ['name'=>'Physiotherapist','slug'=>'physiotherapist'],
        ['name'=>'Kitman','slug'=>'kitman'],
        ], ['slug'], ['name']);
    }
}
