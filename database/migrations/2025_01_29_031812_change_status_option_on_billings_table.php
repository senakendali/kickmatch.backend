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
        Schema::table('billings', function (Blueprint $table) {
            $table->enum('status', ['waiting for payment', 'waiting for confirmation', 'paid', 'failed', 'cancelled'])->default('waiting for payment')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('billings', function (Blueprint $table) {
            $table->enum('status', ['waiting for payment', 'waiting for confirmation', 'paid', 'failed', 'cancelled'])->change();
        });
    }
};
