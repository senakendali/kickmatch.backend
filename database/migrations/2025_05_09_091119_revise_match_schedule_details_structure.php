<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('match_schedule_details', function (Blueprint $table) {
            $table->dropColumn('match_mode');

            // Tambah field match_category_id
            $table->foreignId('match_category_id')
                ->after('match_schedule_id')
                ->nullable()
                ->constrained('match_categories')
                ->onDelete('cascade');
        });

       
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_schedule_details', function (Blueprint $table) {
            $table->dropForeign(['match_category_id']);
            $table->dropColumn('match_category_id');

            $table->enum('match_mode', ['tanding', 'seni_tunggal', 'seni_ganda', 'seni_regu'])->after('seni_match_id')->default('tanding');
        });
    }
};
