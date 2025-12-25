<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournament_age_categories', function (Blueprint $table) {
            $table->unsignedBigInteger('created_by')->nullable()->after('age_category_id');
            $table->boolean('is_active')->default(true)->after('created_by');

            $table->unique(
                ['tournament_id', 'age_category_id'],
                'uniq_tournament_age_category'
            );
        });

        Schema::table('tournament_age_categories', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tournament_age_categories', function (Blueprint $table) {
            try { $table->dropForeign(['created_by']); } catch (\Throwable $e) {}
            try { $table->dropUnique('uniq_tournament_age_category'); } catch (\Throwable $e) {}

            $table->dropColumn(['created_by', 'is_active']);
        });
    }
};
