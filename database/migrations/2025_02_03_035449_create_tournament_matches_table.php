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
        Schema::create('tournament_matches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id');
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
            $table->unsignedBigInteger('match_category_id');
            $table->foreign('match_category_id')->references('id')->on('match_categories')->onDelete('cascade');
            $table->unsignedBigInteger('age_category_id');
            $table->foreign('age_category_id')->references('id')->on('age_categories')->onDelete('cascade');
            $table->unsignedBigInteger('match_clasification_id');
            $table->foreign('match_clasification_id')->references('id')->on('match_clasifications')->onDelete('cascade');
            $table->integer('round');
            $table->unsignedBigInteger('team_member_1_id');
            $table->foreign('team_member_1_id')->references('id')->on('team_members')->onDelete('cascade');
            $table->unsignedBigInteger('team_member_2_id');
            $table->foreign('team_member_2_id')->references('id')->on('team_members')->onDelete('cascade');
            $table->unsignedBigInteger('winner_id');
            $table->foreign('winner_id')->references('id')->on('team_members')->onDelete('cascade');
            $table->unsignedBigInteger('loser_id');
            $table->foreign('loser_id')->references('id')->on('team_members')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tournament_matches');
    }
};
