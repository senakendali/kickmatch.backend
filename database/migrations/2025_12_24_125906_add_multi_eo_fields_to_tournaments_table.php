<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {

            /* =====================
             * MULTI EO
             * ===================== */
            $table->unsignedBigInteger('organizer_id')->nullable()->after('id');
            $table->unsignedBigInteger('created_by')->nullable()->after('organizer_id');

            /* =====================
             * FORMAT TURNAMEN
             * ===================== */
            $table->unsignedBigInteger('tournament_format_id')->nullable()->after('created_by');

            /* =====================
             * APPROVAL FLOW
             * ===================== */
            $table->enum('approval_status', [
                'draft', 'submitted', 'approved', 'rejected'
            ])->default('draft')->after('status');

            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('rejected_by')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();

            /* =====================
             * PUBLISHING
             * ===================== */
            $table->timestamp('published_at')->nullable();
            $table->enum('visibility', ['public', 'unlisted', 'private'])
                ->default('public');

            /* =====================
             * REGISTRATION
             * ===================== */
            $table->timestamp('registration_open_at')->nullable();
            $table->timestamp('registration_close_at')->nullable();
            $table->unsignedInteger('max_teams')->nullable();

            /* =====================
             * DOCUMENT
             * ===================== */
            $table->string('rules_document')->nullable()->after('document');

            /* =====================
             * EVENT MODE + PERMIT
             * ===================== */
            $table->enum('event_mode', ['offline', 'online', 'hybrid'])
                ->default('offline')->after('location');

            $table->boolean('requires_permit')->default(false);

            /* =====================
             * INDEXES
             * ===================== */
            $table->index(['organizer_id', 'approval_status']);
            $table->index(['approval_status', 'published_at']);
            $table->index(['tournament_format_id']);
            $table->index(['event_mode', 'requires_permit']);
        });

        Schema::table('tournaments', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('rejected_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('tournament_format_id')->references('id')->on('tournament_formats')->nullOnDelete();

            // organizer_id FK nanti setelah table EO fix
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            try { $table->dropForeign(['created_by']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['approved_by']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['rejected_by']); } catch (\Throwable $e) {}
            try { $table->dropForeign(['tournament_format_id']); } catch (\Throwable $e) {}

            $table->dropColumn([
                'organizer_id',
                'created_by',
                'tournament_format_id',
                'approval_status',
                'submitted_at',
                'approved_by',
                'approved_at',
                'rejected_by',
                'rejected_at',
                'rejection_reason',
                'published_at',
                'visibility',
                'registration_open_at',
                'registration_close_at',
                'max_teams',
                'rules_document',
                'event_mode',
                'requires_permit',
            ]);
        });
    }
};
