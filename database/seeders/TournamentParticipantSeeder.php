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
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = Faker::create();

        // Ambil data dari tabel yang sudah ada
        $teamIds = Contingent::pluck('id')->toArray();
        $ageCategoryIds = AgeCategory::pluck('id')->toArray();
        $classIds = CategoryClass::pluck('id')->toArray();

        // Gunakan transaksi untuk memastikan data konsisten
        DB::beginTransaction();
        try {
            // Kosongkan tabel peserta sebelum mengisi data baru (opsional)
            // DB::table('team_members')->truncate();
            // DB::table('tournament_participants')->truncate();

            // Buat 32 TeamMember
            $teamMemberIds = [];
            for ($i = 0; $i < 32; $i++) {
                $teamMember = TeamMember::create([
                    'contingent_id' => $faker->randomElement($teamIds),
                    'name' => $faker->name,
                    'birth_place' => $faker->city, 
                    'birth_date' => $faker->date(),
                    'gender' => $faker->randomElement(['male']),
                    'body_weight' => 77,
                    'body_height' => 175,
                    'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                    'nik' => $faker->numerify('###############'),
                    'family_card_number' => $faker->numerify('###############'),
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
                    'registration_status' => 'approved'
                ]);

                // Simpan ID untuk digunakan di TournamentParticipant
                $teamMemberIds[] = $teamMember->id;
            }

            // Buat 32 TournamentParticipant berdasarkan TeamMember yang baru dibuat
            foreach ($teamMemberIds as $teamMemberId) {
                TournamentParticipant::create([
                    'tournament_id' => 2,
                    'team_member_id' => $teamMemberId,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
