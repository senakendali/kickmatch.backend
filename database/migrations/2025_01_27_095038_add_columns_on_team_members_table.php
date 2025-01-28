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
        Schema::table('team_members', function (Blueprint $table) {
            $table->unsignedBigInteger('championship_category_id')->nullable()->after('address');
            $table->foreign('championship_category_id')->references('id')->on('championship_categories');
            $table->unsignedBigInteger('match_category_id')->nullable()->after('championship_category_id');
            $table->foreign('match_category_id')->references('id')->on('match_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropForeign('team_members_championship_category_id_foreign');
            $table->dropColumn('championship_category_id');
            $table->dropForeign('team_members_match_category_id_foreign');
            $table->dropColumn('match_category_id');
        });
    }
};
