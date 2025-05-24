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
        Schema::table('tournament_matches', function (Blueprint $table) {

            $table->time('match_duration')->nullable()->after('participant_2');
            $table->integer('participant_1_score')->nullable()->after('match_duration');
            $table->integer('participant_2_score')->nullable()->after('participant_1_score');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('match_duration');
            $table->dropColumn('participant_1_score');
            $table->dropColumn('participant_2_score');
        });
    }
};
