<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        DB::statement("ALTER TABLE seni_matches MODIFY match_type ENUM('seni_tunggal', 'seni_ganda', 'seni_regu', 'solo_kreatif') NOT NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        DB::statement("ALTER TABLE seni_matches MODIFY match_type ENUM('seni_tunggal', 'seni_ganda', 'seni_regu') NOT NULL");
    }
};
