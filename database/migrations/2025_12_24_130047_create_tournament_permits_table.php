<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tournament_permits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id');

            $table->enum('permit_type', [
                'venue', 'dispora', 'school', 'federation', 'police', 'other'
            ])->nullable();

            $table->string('permit_number')->nullable();
            $table->string('issuer')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('expired_at')->nullable();
            $table->string('document_path')->nullable();

            $table->enum('status', ['draft', 'submitted', 'accepted', 'rejected'])
                ->default('draft');

            $table->text('rejection_reason')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();

            $table->index(['tournament_id', 'status']);

            $table->foreign('tournament_id')
                ->references('id')->on('tournaments')
                ->cascadeOnDelete();

            $table->foreign('reviewed_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_permits');
    }
};
