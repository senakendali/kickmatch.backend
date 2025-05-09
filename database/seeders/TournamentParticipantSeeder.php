<?php

namespace Database\Seeders;

use App\Models\TeamMember;
use App\Models\TournamentParticipant;
use App\Models\Contingent;
use App\Models\AgeCategory;
use App\Models\CategoryClass;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class TournamentParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('id_ID');

        $contingents = Contingent::pluck('id')->toArray();
        $ageCategories = AgeCategory::pluck('id')->toArray();
        $classes = CategoryClass::pluck('id')->toArray();
        $tournamentId = 2;

        DB::beginTransaction();
        try {
            foreach (['male', 'female'] as $gender) {
                foreach ($ageCategories as $ageCategoryId) {
                    foreach ($classes as $classId) {
                        // Buat 4 peserta per kombinasi (boleh disesuaikan)
                        foreach (range(1, 4) as $i) {
                            $teamMember = TeamMember::create([
                                'contingent_id' => $faker->randomElement($contingents),
                                'name' => $faker->name($gender === 'male' ? 'male' : 'female'),
                                'birth_place' => $faker->city,
                                'birth_date' => $faker->date(),
                                'gender' => $gender,
                                'body_weight' => $faker->numberBetween(50, 80),
                                'body_height' => $faker->numberBetween(160, 185),
                                'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                                'nik' => $faker->numerify('###############'),
                                'family_card_number' => $faker->numerify('###############'),
                                'country_id' => 103,
                                'province_id' => 32,
                                'district_id' => 3217,
                                'subdistrict_id' => 321714,
                                'ward_id' => 3217142009,
                                'address' => $faker->address,
                                'championship_category_id' => 1, // Tanding
                                'match_category_id' => 1,        // Tanding
                                'age_category_id' => $ageCategoryId,
                                'category_class_id' => $classId,
                                'registration_status' => 'approved',
                            ]);

                            TournamentParticipant::create([
                                'tournament_id' => $tournamentId,
                                'team_member_id' => $teamMember->id,
                            ]);
                        }
                    }
                }
            }

            DB::commit();
            echo "✅ Tournament participants for tanding generated successfully.\n";
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
