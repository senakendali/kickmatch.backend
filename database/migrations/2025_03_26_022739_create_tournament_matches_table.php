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
            $table->unsignedBigInteger('pool_id');
            $table->integer('round'); // Babak (1, 2, dst)
            $table->integer('match_number'); // Nomor pertandingan dalam babak
            $table->unsignedBigInteger('participant_1')->nullable();
            $table->unsignedBigInteger('participant_2')->nullable();
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->unsignedBigInteger('next_match_id')->nullable(); // Match selanjutnya
            $table->timestamps();
        
            $table->foreign('pool_id')->references('id')->on('pools')->onDelete('cascade');
            $table->foreign('participant_1')->references('id')->on('team_members')->onDelete('set null');
            $table->foreign('participant_2')->references('id')->on('team_members')->onDelete('set null');
            $table->foreign('winner_id')->references('id')->on('team_members')->onDelete('set null');
            $table->foreign('next_match_id')->references('id')->on('tournament_matches')->onDelete('set null');
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
