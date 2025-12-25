<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('game_types', function (Blueprint $table) {
            $table->id();

            $table->string('name');                 // Futsal, Mini Soccer, Soccer
            $table->string('slug')->unique();       // futsal, mini-soccer, soccer

            // Core rules (biar tournament bisa auto isi default)
            $table->unsignedTinyInteger('players_per_team')->default(5);  // 5, 7, 11
            $table->unsignedTinyInteger('period_count')->default(2);      // biasanya 2 babak
            $table->unsignedSmallInteger('period_minutes')->default(20);  // 20 / 25 / 45
            $table->unsignedSmallInteger('break_minutes')->default(10);   // 10 / 15

            // Optional extras
            $table->unsignedSmallInteger('extra_time_minutes')->nullable(); // misal 10
            $table->unsignedSmallInteger('penalty_minutes')->nullable();    // kalau mau
            $table->enum('field_kind', ['indoor', 'outdoor'])->nullable();  // optional
            $table->string('ball_size', 10)->nullable();                    // optional: 4 / 5

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Kalau lu nggak butuh soft delete, hapus line ini
            // $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_types');
    }
};
