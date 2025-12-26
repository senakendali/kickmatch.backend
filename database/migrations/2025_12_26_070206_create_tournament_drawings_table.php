<?php

// database/migrations/2025_01_01_000000_create_tournament_drawings_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournament_drawings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tournament_id');

            $table->string('name');
            $table->text('description')->nullable();

            // league / group / knockout / group_knockout
            $table->string('format', 50);

            // optional, hanya dipakai kalau format pakai grup
            $table->unsignedInteger('group_size')->nullable();

            // flag aktif
            $table->boolean('is_active')->default(true);

            // ruang buat config lanjutan (aturan bye, dll)
            $table->json('config')->nullable();

            $table->timestamps();

            $table->foreign('tournament_id')
                ->references('id')
                ->on('tournaments')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_drawings');
    }
};
