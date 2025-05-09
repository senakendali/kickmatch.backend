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
        Schema::create('seni_pools', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id');
            $table->unsignedBigInteger('age_category_id');
            $table->unsignedBigInteger('match_category_id'); // tunggal, ganda, regu
            $table->enum('gender', ['male', 'female']);
            $table->string('name')->nullable(); // Pool A, Pool B, dst
            $table->timestamps();
        
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
            $table->foreign('age_category_id')->references('id')->on('age_categories')->onDelete('cascade');
            $table->foreign('match_category_id')->references('id')->on('match_categories')->onDelete('cascade');
        });
        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seni_pools');
    }
};
