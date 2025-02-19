<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TeamMember;
use App\Models\Contingent;
use App\Models\AgeCategory;
use App\Models\CategoryClass;
use Illuminate\Support\Facades\DB;
use Faker\Factory as Faker;

class ParticipantSeeder extends Seeder
{
    public function run()
    {
        $faker = Faker::create();

        // Ambil data dari tabel yang sudah ada
        $teamIds = Contingent::pluck('id')->toArray();
        $ageCategoryIds = AgeCategory::pluck('id')->toArray();
        $classIds = CategoryClass::pluck('id')->toArray();

        // Pastikan tabel peserta dikosongkan terlebih dahulu
        // DB::table('team_members')->truncate();

        // Buat 70 peserta
        for ($i = 0; $i < 10; $i++) {
            TeamMember::create([
                'contingent_id' => $faker->randomElement($teamIds),
                'name' => $faker->name,
                'birth_place' => $faker->city, 
                'birth_date' => $faker->date(),
                'gender' => $faker->randomElement(['male', 'female']),
                'body_weight' => 77,
                'body_height' => 175,
                'blood_type' => $faker->randomElement(['A', 'B', 'AB', 'O']),
                'nik' => $faker->ean13,
                'family_card_number' => $faker->ean13,
                'country_id' => 103,
                'province_id' => 32,
                'district_id' => 3217,
                'subdistrict_id' => 321714,
                'ward_id' => 3217142009,
                'address' => $faker->address,
                'championship_category_id' => 1,
                'match_category_id' => 1,
                'age_category_id' => 4, // Ambil dari AgeCategory
                'category_class_id' => 131,
                'registration_status' => 'approved'
            ]);
        }
    }
}

