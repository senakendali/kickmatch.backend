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
        Schema::create('match_clasification_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('match_clasification_id');
            $table->foreign('match_clasification_id')->references('id')->on('match_clasifications')->onDelete('cascade');
            $table->unsignedBigInteger('match_category_id');
            $table->foreign('match_category_id')->references('id')->on('match_categories')->onDelete('cascade');
            $table->unsignedBigInteger('category_class_id');
            $table->foreign('category_class_id')->references('id')->on('category_classes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('match_clasification_details');
    }
};
