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
            $table->unsignedBigInteger('next_match_id')->nullable()->after('loser_id');
            $table->foreign('next_match_id')->references('id')->on('tournament_matches')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropForeign(['next_match_id']);
            $table->dropColumn('next_match_id');
        });
    }
};
