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
        Schema::table('team_members', function (Blueprint $table) {
            $table->dropColumn('family_card_document');
            $table->dropColumn('id_card_document');
            $table->dropColumn('certificate_of_health');
            $table->dropColumn('recomendation_letter');
            $table->dropColumn('parental_permission_letter');
            $table->string('documents')->nullable()->after('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            $table->string('family_card_document');
            $table->string('id_card_document');
            $table->string('certificate_of_health');
            $table->string('recomendation_letter');
            $table->string('parental_permission_letter');
            $table->dropColumn('documents');
        });
    }
};
