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
        Schema::table('contingents', function (Blueprint $table) {
            $table->string('logo')->nullable()->after('name');
            $table->enum('type', ['futsal', 'minisoccer'])->default('futsal')->after('logo');
            $table->char('jersey_home_hex', 7)->nullable()->after('logo'); 
            $table->char('jersey_away_hex', 7)->nullable()->after('jersey_home_hex');
            $table->string('jersey_home_image')->nullable()->after('jersey_away_hex');
            $table->string('jersey_away_image')->nullable()->after('jersey_home_image');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('contingents', function (Blueprint $table) {
            $table->dropColumn('logo');
            $table->dropColumn('type');
            $table->dropColumn('jersey_home_hex');
            $table->dropColumn('jersey_away_hex');
            $table->dropColumn('jersey_home_image');
            $table->dropColumn('jersey_away_image');
        });
    }
};
