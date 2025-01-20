<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /*public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->unsignedBigInteger('owner_id')->nullable()->after('id');
            $table->foreign('owner_id')->references('id')->on('users');
        });
    }*/

    /**
     * Reverse the migrations.
     */
    /*public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropForeign('tournaments_owner_id_foreign');
            $table->dropColumn('owner_id');
        });
    }*/
};
