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
        Schema::create('team_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingent_id')->constrained('contingents')->cascadeOnDelete();
            $table->foreignId('staff_role_id')->constrained('staff_roles');
            $table->string('full_name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('ordering')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('staff_roles');
    }
};
