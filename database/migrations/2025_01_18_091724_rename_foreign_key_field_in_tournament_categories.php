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
        Schema::table('tournament_categories', function (Blueprint $table) {
            // Drop foreign key constraint 
            $table->dropForeign(['category_id']); // Rename the field 
            $table->renameColumn('category_id', 'match_category_id'); // Add the foreign key constraint back 
            $table->foreign('match_category_id')->references('id')->on('match_categories')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_categories', function (Blueprint $table) {
            // Drop foreign key constraint 
            $table->dropForeign(['match_category_id']); // Rename the field back to its original name 
            $table->renameColumn('match_category_id', 'category_id'); // Add the foreign key constraint back 
            $table->foreign('category_id')->references('id')->on('match_categories')->onDelete('cascade');
        });
    }
};
