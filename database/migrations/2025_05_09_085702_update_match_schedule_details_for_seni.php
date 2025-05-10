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
            // Jadikan nullable
            $table->foreignId('tournament_match_id')->nullable()->change();

            // Tambahkan untuk seni
            $table->foreignId('seni_match_id')->after('tournament_match_id')->nullable()->constrained('seni_matches')->onDelete('cascade');
            $table->enum('match_mode', ['tanding', 'seni_tunggal', 'seni_ganda', 'seni_regu'])->after('seni_match_id')->default('tanding');

            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_schedule_details', function (Blueprint $table) {
            $table->dropColumn(['seni_match_id', 'match_type']);
            $table->foreignId('tournament_match_id')->nullable(false)->change(); // Balikin ke not-null kalau sebelumnya not null
        });
    }
};
