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
        Schema::create('seni_matches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('tournament_id')->constrained()->onDelete('cascade');
            $table->foreignId('match_category_id')->constrained()->onDelete('cascade'); // Tunggal/Ganda/Regu
            $table->enum('match_type', ['seni_tunggal', 'seni_ganda', 'seni_regu']);

            $table->date('match_date')->nullable();
            $table->time('match_time')->nullable();
            $table->string('arena_name')->nullable();

            $table->foreignId('contingent_id')->constrained()->onDelete('cascade');

            $table->foreignId('team_member_1')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignId('team_member_2')->nullable()->constrained('team_members')->nullOnDelete();
            $table->foreignId('team_member_3')->nullable()->constrained('team_members')->nullOnDelete();

            $table->decimal('final_score', 5, 2)->nullable(); // total skor final

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seni_matches');
    }
};
