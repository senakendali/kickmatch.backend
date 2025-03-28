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
        Schema::table('pools', function (Blueprint $table) {
            $table->dropForeign(['match_clasification_id']);
            $table->dropColumn('match_clasification_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->unsignedBigInteger('match_clasification_id')->after('tournament_id');
            $table->foreign('match_clasification_id')->references('id')->on('match_clasifications')->onDelete('cascade');
        });
    }
};
