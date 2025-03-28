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
            $table->unsignedBigInteger('category_class_id')->nullable()->after('age_category_id');
            $table->foreign('category_class_id')->references('id')->on('category_classes');
            $table->integer('match_chart')->nullable()->after('match_clasification_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pools', function (Blueprint $table) {
            $table->dropForeign(['category_class_id']);
            $table->dropColumn('category_class_id');
            $table->dropColumn('match_chart');
        });
    }
};
