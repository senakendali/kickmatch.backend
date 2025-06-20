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
            $table->unsignedBigInteger('parent_match_blue_id')->nullable()->after('next_match_id');
            $table->unsignedBigInteger('parent_match_red_id')->nullable()->after('parent_match_blue_id');
           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_matches', function (Blueprint $table) {
            $table->dropColumn('parent_match_blue_id');
            $table->dropColumn('parent_match_red_id');
        });
    }
};
