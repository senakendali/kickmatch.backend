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
        Schema::create('contingents', function (Blueprint $table) {
            $table->id();
            $table->string('name'); 
            $table->string('email');
            $table->string('phone');
            $table->string('password');
            $table->string('pic_name');
            $table->string('pic_email');
            $table->string('pic_phone');
            $table->unsignedBigInteger('province_id');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
            $table->unsignedBigInteger('district_id');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade');
            $table->unsignedBigInteger('subdistrict_id');
            $table->foreign('subdistrict_id')->references('id')->on('subdistricts')->onDelete('cascade');
            $table->unsignedBigInteger('ward_id');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade');
            $table->string('address');
            $table->enum('status', ['active', 'inactive', 'pending', 'disqualified']);
            $table->unsignedBigInteger('tournament_id'); 
            $table->foreign('tournament_id')->references('id')->on('tournaments')->onDelete('cascade');
            $table->timestamps(); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contingents');
    }
};
