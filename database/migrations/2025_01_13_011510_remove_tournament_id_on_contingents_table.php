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
        Schema::table('contingents', function (Blueprint $table) {
            $table->dropForeign('contingents_tournament_id_foreign');
            $table->dropColumn('tournament_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contingents', function (Blueprint $table) {
            $table->unsignedBigInteger('tournament_id')->nullable()->after('id');
            $table->foreign('tournament_id')->references('id')->on('tournaments');
            
        });
    }
};
