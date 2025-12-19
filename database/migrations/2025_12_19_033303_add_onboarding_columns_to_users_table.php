<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'onboarding_completed')) {
                $table->boolean('onboarding_completed')->default(false)->index()->after('remember_token');
            }

            if (!Schema::hasColumn('users', 'organizer_id')) {
                $table->unsignedBigInteger('organizer_id')->nullable()->index()->after('onboarding_completed');
            }

            if (!Schema::hasColumn('users', 'tournament_id')) {
                $table->unsignedBigInteger('tournament_id')->nullable()->index()->after('organizer_id');
            }

            // FK organizer (nullable)
            $table->foreign('organizer_id')->references('id')->on('event_organizers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // drop FK dulu biar aman
            try { $table->dropForeign(['organizer_id']); } catch (\Throwable $e) {}

            if (Schema::hasColumn('users', 'tournament_id')) $table->dropColumn('tournament_id');
            if (Schema::hasColumn('users', 'organizer_id')) $table->dropColumn('organizer_id');
            if (Schema::hasColumn('users', 'onboarding_completed')) $table->dropColumn('onboarding_completed');
        });
    }
};
