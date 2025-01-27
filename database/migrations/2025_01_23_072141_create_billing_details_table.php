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
        Schema::create('billing_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('billing_id');
            $table->foreign('billing_id')->references('id')->on('billings')->onDelete('cascade');
            $table->unsignedBigInteger('team_member_id');
            $table->foreign('team_member_id')->references('id')->on('team_members')->onDelete('cascade');
            $table->decimal('amount', 8, 2);
            $table->unsignedBigInteger('tournament_category_id');
            $table->foreign('tournament_category_id')->references('id')->on('tournament_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('billing_details');
    }
};
