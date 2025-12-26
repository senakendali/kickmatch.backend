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
        Schema::table('tournament_contingents', function (Blueprint $table) {
            // kategori umur (relasi ke age_categories), boleh nullable
            $table->unsignedBigInteger('age_category_id')->nullable()->after('contingent_id');

            // gender tim di turnamen ini (male / female / mixed), nullable dulu biar aman
            $table->enum('gender', ['male', 'female', 'mixed'])->nullable()->after('age_category_id');

            // foreign key ke age_categories
            $table->foreign('age_category_id', 'tc_age_category_fk')
                ->references('id')
                ->on('age_categories')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tournament_contingents', function (Blueprint $table) {
            // drop FK dulu baru kolomnya
            if (Schema::hasColumn('tournament_contingents', 'age_category_id')) {
                $table->dropForeign('tc_age_category_fk');
                $table->dropColumn('age_category_id');
            }

            if (Schema::hasColumn('tournament_contingents', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};
