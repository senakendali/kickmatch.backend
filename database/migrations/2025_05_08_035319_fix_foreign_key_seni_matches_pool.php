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
        Schema::table('seni_matches', function (Blueprint $table) {
            $table->dropForeign(['pool_id']); // drop FK lama
            $table->foreign('pool_id')->references('id')->on('seni_pools')->onDelete('cascade'); // FK baru
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seni_matches', function (Blueprint $table) {
            $table->dropForeign(['pool_id']);
            $table->foreign('pool_id')->references('id')->on('pools')->onDelete('cascade'); // rollback ke awal
        });
    }
};
