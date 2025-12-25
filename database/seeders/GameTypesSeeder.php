<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GameTypesSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'Futsal',
                'slug' => 'futsal',
                'players_per_team' => 5,
                'period_count' => 2,
                'period_minutes' => 20,
                'break_minutes' => 10,
                'extra_time_minutes' => null,
                'penalty_minutes' => null,
                'field_kind' => 'indoor',
                'ball_size' => '4',
                'is_active' => true,
            ],
            [
                'name' => 'Mini Soccer',
                'slug' => 'mini-soccer',
                'players_per_team' => 7,
                'period_count' => 2,
                'period_minutes' => 25,
                'break_minutes' => 10,
                'extra_time_minutes' => null,
                'penalty_minutes' => null,
                'field_kind' => 'outdoor',
                'ball_size' => '5',
                'is_active' => true,
            ],
            [
                'name' => 'Soccer',
                'slug' => 'soccer',
                'players_per_team' => 11,
                'period_count' => 2,
                'period_minutes' => 45,
                'break_minutes' => 15,
                'extra_time_minutes' => 15, // biasanya 2x15 kalau dipakai
                'penalty_minutes' => null,
                'field_kind' => 'outdoor',
                'ball_size' => '5',
                'is_active' => true,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('game_types')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
