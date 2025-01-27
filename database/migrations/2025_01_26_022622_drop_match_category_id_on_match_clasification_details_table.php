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
        Schema::table('match_clasification_details', function (Blueprint $table) {
            $table->dropForeign(['match_category_id']);
            $table->dropColumn('match_category_id');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_clasification_details', function (Blueprint $table) {
            $table->unsignedBigInteger('match_category_id')->after('match_clasification_id');
            $table->foreign('match_category_id')->references('id')->on('match_categories')->onDelete('cascade');
        });
    }
};
