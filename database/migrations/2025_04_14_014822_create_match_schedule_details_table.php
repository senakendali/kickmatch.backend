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
        Schema::create('match_schedule_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('match_schedule_id')->constrained('match_schedules')->onDelete('cascade');
            $table->foreignId('tournament_match_id')->constrained('tournament_matches')->onDelete('cascade');
            $table->integer('order')->nullable(); // urutan pertandingan
            $table->time('start_time')->nullable(); // bisa diatur per match kalau butuh
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_schedule_details');
    }
};
