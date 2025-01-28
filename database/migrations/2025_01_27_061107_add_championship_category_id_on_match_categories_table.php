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
        Schema::table('match_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('championship_category_id')->nullable()->after('id');
            $table->foreign('championship_category_id')->references('id')->on('championship_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_categories', function (Blueprint $table) {
            $table->dropForeign('match_categories_championship_category_id_foreign');
            $table->dropColumn('championship_category_id');
        });
    }
};
