<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContingentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -----------------------------
        // 1. Matikan foreign key check
        // -----------------------------
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // Hapus dulu relasi ke turnamen (pivot)
        DB::table('tournament_contingents')->truncate();

        // Hapus dulu semua kontingen
        DB::table('contingents')->truncate();

        // Nyalain lagi FK
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // -----------------------------
        // 2. Tentukan tournament target (FIX: 5)
        // -----------------------------
        $tournamentId = 5;

        $exists = DB::table('tournaments')->where('id', $tournamentId)->exists();
        if (! $exists) {
            $this->command->error("❌ Tournament dengan ID {$tournamentId} tidak ditemukan. Seed tournament dulu bro.");
            return;
        }

        // -----------------------------
        // 3. Age categories fix: id 1 & 2
        // -----------------------------
        $ageCategoryId1 = 1;
        $ageCategoryId2 = 2;

        $age1Exists = DB::table('age_categories')->where('id', $ageCategoryId1)->exists();
        $age2Exists = DB::table('age_categories')->where('id', $ageCategoryId2)->exists();

        if (! $age1Exists || ! $age2Exists) {
            $this->command->error("❌ age_categories dengan ID 1 dan/atau 2 tidak ditemukan. Pastikan sudah ada datanya bro.");
            return;
        }

        // gender default (bisa diubah nanti)
        $defaultGender = 'male';

        // -----------------------------
        // 4. Setup data kontingen
        // -----------------------------
        $now        = now();
        $ownerId    = 2; // contoh: semua kontingen dimiliki owner_id = 2

        // lokasi default (dari sample lu)
        $countryId     = 103;
        $provinceId    = 32;
        $districtId    = 3202;
        $subdistrictId = 320232;
        $wardId        = 3202322006;

        /**
         * Total tim: 48
         * - 24 tim → age_category_id = 1
         * - 24 tim → age_category_id = 2
         */
        $totalTeams = 48;

        // Base list nama tim (kalau totalTeams > jumlah array, nanti digandakan dengan suffix angka)
        $baseTeams = [
            // 1 tim pertama sama persis kayak sample lu, pakai logo & jersey
            [
                'name'              => 'Sens Football',
                'logo'              => 'uploads/contingent_logos/logo-sens-football-1759725533.png',
                'jersey_home_hex'   => '#FFA500',
                'jersey_away_hex'   => '#042863',
                'jersey_home_image' => 'uploads/contingent_jerseys/jersey-home-sens-football-1759725533.png',
                'jersey_away_image' => 'uploads/contingent_jerseys/jersey-away-sens-football-1759725533.png',
                'pic_name'          => 'Sena Kendali',
                'pic_email'         => 'sena.kendali@gmail.com',
                'pic_phone'         => '081295828291',
                'address'           => 'Selabintana, KM 6',
            ],
            [
                'name'              => 'Pabuaran FC',
                'logo'              => null,
                'jersey_home_hex'   => '#1E3A8A',
                'jersey_away_hex'   => '#FBBF24',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Andi Pratama',
                'pic_email'         => 'andi.pabuaran@example.com',
                'pic_phone'         => '081200000001',
                'address'           => 'Pabuaran City',
            ],
            [
                'name'              => 'Sukabumi United',
                'logo'              => null,
                'jersey_home_hex'   => '#047857',
                'jersey_away_hex'   => '#F97316',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Bima Rahman',
                'pic_email'         => 'bima.sukabumi@example.com',
                'pic_phone'         => '081200000002',
                'address'           => 'Sukabumi',
            ],
            [
                'name'              => 'Cisaat Warriors',
                'logo'              => null,
                'jersey_home_hex'   => '#EF4444',
                'jersey_away_hex'   => '#111827',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Rizky Maulana',
                'pic_email'         => 'rizky.cisaat@example.com',
                'pic_phone'         => '081200000003',
                'address'           => 'Cisaat',
            ],
            [
                'name'              => 'Galaxy Futsal',
                'logo'              => null,
                'jersey_home_hex'   => '#0EA5E9',
                'jersey_away_hex'   => '#FACC15',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Dimas Firmansyah',
                'pic_email'         => 'dimas.galaxy@example.com',
                'pic_phone'         => '081200000004',
                'address'           => 'Jakarta',
            ],
            [
                'name'              => 'Southside Crew',
                'logo'              => null,
                'jersey_home_hex'   => '#7C3AED',
                'jersey_away_hex'   => '#F97316',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Agus Salim',
                'pic_email'         => 'agus.southside@example.com',
                'pic_phone'         => '081200000005',
                'address'           => 'Bandung',
            ],
            [
                'name'              => 'Cibadak Rangers',
                'logo'              => null,
                'jersey_home_hex'   => '#15803D',
                'jersey_away_hex'   => '#FDE047',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Asep Jaya',
                'pic_email'         => 'asep.cibadak@example.com',
                'pic_phone'         => '081200000006',
                'address'           => 'Cibadak',
            ],
            [
                'name'              => 'Northside Legends',
                'logo'              => null,
                'jersey_home_hex'   => '#1D4ED8',
                'jersey_away_hex'   => '#F97316',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Fajar Ramdhan',
                'pic_email'         => 'fajar.northside@example.com',
                'pic_phone'         => '081200000007',
                'address'           => 'Bogor',
            ],
            [
                'name'              => 'Downtown FC',
                'logo'              => null,
                'jersey_home_hex'   => '#DC2626',
                'jersey_away_hex'   => '#0F172A',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Yuda Prasetyo',
                'pic_email'         => 'yuda.downtown@example.com',
                'pic_phone'         => '081200000008',
                'address'           => 'Depok',
            ],
            [
                'name'              => 'Selabintana Youngsters',
                'logo'              => null,
                'jersey_home_hex'   => '#22C55E',
                'jersey_away_hex'   => '#1E293B',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Ilham Nur',
                'pic_email'         => 'ilham.selabintana@example.com',
                'pic_phone'         => '081200000009',
                'address'           => 'Selabintana',
            ],
            // tambahin beberapa default lain biar variasi nama banyak
            [
                'name'              => 'Gunung Gede FC',
                'logo'              => null,
                'jersey_home_hex'   => '#0F766E',
                'jersey_away_hex'   => '#F97316',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Rangga Putra',
                'pic_email'         => 'rangga.gununggede@example.com',
                'pic_phone'         => '081200000010',
                'address'           => 'Cipanas',
            ],
            [
                'name'              => 'City Lions',
                'logo'              => null,
                'jersey_home_hex'   => '#F59E0B',
                'jersey_away_hex'   => '#111827',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Doni Saputra',
                'pic_email'         => 'doni.citylions@example.com',
                'pic_phone'         => '081200000011',
                'address'           => 'Jakarta',
            ],
            [
                'name'              => 'Westblock FC',
                'logo'              => null,
                'jersey_home_hex'   => '#3B82F6',
                'jersey_away_hex'   => '#FACC15',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Robby Firmansyah',
                'pic_email'         => 'robby.westblock@example.com',
                'pic_phone'         => '081200000012',
                'address'           => 'Bogor',
            ],
            [
                'name'              => 'Eastside Juniors',
                'logo'              => null,
                'jersey_home_hex'   => '#A855F7',
                'jersey_away_hex'   => '#0EA5E9',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Nanda Julian',
                'pic_email'         => 'nanda.eastside@example.com',
                'pic_phone'         => '081200000013',
                'address'           => 'Bekasi',
            ],
            [
                'name'              => 'Mountain Kings',
                'logo'              => null,
                'jersey_home_hex'   => '#16A34A',
                'jersey_away_hex'   => '#1F2937',
                'jersey_home_image' => null,
                'jersey_away_image' => null,
                'pic_name'          => 'Yogi Firmansyah',
                'pic_email'         => 'yogi.mountain@example.com',
                'pic_phone'         => '081200000014',
                'address'           => 'Puncak',
            ],
        ];

        // Kalau totalTeams lebih besar dari baseTeams, kita clone dengan suffix angka
        $teams  = [];
        $index  = 0;

        for ($i = 0; $i < $totalTeams; $i++) {
            $template = $baseTeams[$index % count($baseTeams)];
            $index++;

            $name = $template['name'];
            if ($i >= count($baseTeams)) {
                // buat unik dikit kalau sudah lewat base pertama
                $name .= ' ' . ($i + 1);
            }

            $teams[] = [
                'owner_id'          => $ownerId,
                'name'              => $name,
                'logo'              => $template['logo'],
                'jersey_home_hex'   => $template['jersey_home_hex'],
                'jersey_away_hex'   => $template['jersey_away_hex'],
                'jersey_home_image' => $template['jersey_home_image'],
                'jersey_away_image' => $template['jersey_away_image'],
                'type'              => 'futsal',
                'pic_name'          => $template['pic_name'],
                'pic_email'         => $template['pic_email'],
                'pic_phone'         => $template['pic_phone'],
                'country_id'        => $countryId,
                'province_id'       => $provinceId,
                'district_id'       => $districtId,
                'subdistrict_id'    => $subdistrictId,
                'ward_id'           => $wardId,
                'address'           => $template['address'],
                'status'            => 'active',
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        // Insert ke tabel contingents
        DB::table('contingents')->insert($teams);

        // Ambil ID yang baru diinsert
        $contingentIds = DB::table('contingents')
            ->orderBy('id')
            ->pluck('id')
            ->toArray();

        // -----------------------------
        // 5. Seed pivot tournament_contingents
        //    - 24 pertama → age_category_id = 1
        //    - 24 berikutnya → age_category_id = 2
        // -----------------------------
        $pivotRows = [];
        $half      = 24; // 24 per age category

        foreach ($contingentIds as $i => $contingentId) {
            $ageCategoryId = ($i < $half) ? $ageCategoryId1 : $ageCategoryId2;

            $pivotRows[] = [
                'tournament_id'   => $tournamentId,
                'contingent_id'   => $contingentId,
                'age_category_id' => $ageCategoryId,
                'gender'          => $defaultGender,
                'created_at'      => $now,
                'updated_at'      => $now,
            ];
        }

        DB::table('tournament_contingents')->insert($pivotRows);

        $this->command->info("✅ Contingents seeded: " . count($teams));
        $this->command->info("✅ TournamentContingents seeded: " . count($pivotRows) . " (24 age_category_id=1, 24 age_category_id=2, tournament ID: {$tournamentId})");
    }
}
