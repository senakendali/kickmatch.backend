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
        Schema::table('match_schedules', function (Blueprint $table) {
            $table->unsignedBigInteger('age_category_id')->nullable()->after('scheduled_date');
            $table->string('round_label')->nullable()->after('age_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('match_schedules', function (Blueprint $table) {
            $table->dropColumn('age_category_id');
            $table->dropColumn('round_label');
        });
    }
};
