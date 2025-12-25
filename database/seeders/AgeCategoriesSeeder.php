<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AgeCategoriesSeeder extends Seeder
{
    public function run(): void
    {
        // ✅ HAPUS semua data dulu (biar bersih)
       // DB::table('age_categories')->truncate();

        // ✅ Seed default Kickmatch
        $rows = [
            ['name' => 'U10',  'min_age' => 8,  'max_age' => 10],
            ['name' => 'U12',  'min_age' => 11, 'max_age' => 12],
            ['name' => 'U14',  'min_age' => 13, 'max_age' => 14],
            ['name' => 'U16',  'min_age' => 15, 'max_age' => 16],
            ['name' => 'U18',  'min_age' => 17, 'max_age' => 18],
            ['name' => 'Open', 'min_age' => 0,  'max_age' => 99],
        ];

        $now = now();

        DB::table('age_categories')->insert(
            array_map(fn ($r) => array_merge($r, [
                'created_at' => $now,
                'updated_at' => $now,
            ]), $rows)
        );
    }
}
