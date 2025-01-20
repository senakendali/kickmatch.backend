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
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contingent_id');
            $table->foreign('contingent_id')->references('id')->on('contingents')->onDelete('cascade');
            $table->string('name');
            $table->string('birth_place');
            $table->date('birth_date');
            $table->string('gender');
            $table->decimal('body_weight', 8, 2)->nullable();
            $table->decimal('body_height', 8, 2)->nullable();
            $table->string('blood_type')->nullable();
            $table->string('nik');
            $table->string('family_card_number');
            $table->unsignedBigInteger('province_id');
            $table->foreign('province_id')->references('id')->on('provinces')->onDelete('cascade');
            $table->unsignedBigInteger('district_id');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('cascade');
            $table->unsignedBigInteger('subdistrict_id');
            $table->foreign('subdistrict_id')->references('id')->on('subdistricts')->onDelete('cascade');
            $table->unsignedBigInteger('ward_id');
            $table->foreign('ward_id')->references('id')->on('wards')->onDelete('cascade');
            $table->string('address');
            $table->string('family_card_document')->nullable();
            $table->string('id_card_document')->nullable();
            $table->string('certificate_of_health')->nullable();
            $table->string('recomendation_letter')->nullable();
            $table->string('parental_permission_letter')->nullable();
            $table->enum('category', ['Tanding', 'Seni', 'Olahraga']);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
