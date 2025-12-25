<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TournamentFormatsSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            [
                'name' => 'League',
                'slug' => 'league',
                'code' => 'LEAGUE',
                'description' => 'Round-robin league. Standings determine the winner.',
                'has_groups' => false,
                'has_knockout' => false,
                'is_active' => true,
                'sort_order' => 10,
            ],
            [
                'name' => 'Group Stage',
                'slug' => 'group-stage',
                'code' => 'GROUP',
                'description' => 'Teams are split into groups. Top teams advance based on standings.',
                'has_groups' => true,
                'has_knockout' => false,
                'is_active' => true,
                'sort_order' => 20,
            ],
            [
                'name' => 'Knockout',
                'slug' => 'knockout',
                'code' => 'KNOCKOUT',
                'description' => 'Single-elimination bracket until a champion is determined.',
                'has_groups' => false,
                'has_knockout' => true,
                'is_active' => true,
                'sort_order' => 30,
            ],
            [
                'name' => 'Group + Knockout',
                'slug' => 'group-knockout',
                'code' => 'GROUP_KO',
                'description' => 'Group stage followed by a knockout bracket.',
                'has_groups' => true,
                'has_knockout' => true,
                'is_active' => true,
                'sort_order' => 40,
            ],
        ];

        foreach ($rows as $row) {
            DB::table('tournament_formats')->updateOrInsert(
                ['slug' => $row['slug']],
                array_merge($row, [
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );
        }
    }
}
