<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeamMember;
use App\Models\Contingent;
use App\Models\TournamentParticipant;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ParticipantSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();
        $tournamentId = 2;

        // Ambil contingent ID yang join di tournament_id = 2
        $teamIds = DB::table('tournament_contingents')
            ->where('tournament_id', $tournamentId)
            ->pluck('contingent_id')
            ->toArray();

        if (empty($teamIds)) {
            $this->command->warn('Tidak ada kontingen yang join di tournament_id 2.');
            return;
        }

        // Buat 32 peserta dan daftarkan ke tournament_participants
        for ($i = 0; $i < 11; $i++) {
            $member = TeamMember::create([
                'contingent_id' => $faker->randomElement($teamIds),
                'name' => $faker->name,
                'birth_place' => $faker->city,
                'birth_date' => $faker->date(),
                'gender' => $faker->randomElement(['male', 'female']),
                'body_weight' => $faker->numberBetween(45, 90),
                'body_height' => $faker->numberBetween(150, 190),
                'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'nik' => $faker->numerify('##########'),
                'family_card_number' => $faker->numerify('##########'),
                'country_id' => 103,
                'province_id' => 32,
                'district_id' => 3217,
                'subdistrict_id' => 321714,
                'ward_id' => 3217142009,
                'address' => $faker->address,
                'championship_category_id' => 1,
                'match_category_id' => 1,
                'age_category_id' => 4,
                'category_class_id' => 131,
                'registration_status' => 'approved',
            ]);

            // Daftarkan ke tournament
            TournamentParticipant::create([
                'tournament_id' => $tournamentId,
                'team_member_id' => $member->id,
            ]);
        }

        $this->command->info('✅ 32 peserta berhasil dibuat untuk tournament_id 2, kelas 131 dan terdaftar di tournament_participants.');
    }
}
