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
        Schema::table('seni_matches', function (Blueprint $table) {
            // Hapus kolom tournament_id karena kita ambil dari relasi pool
            $table->dropForeign(['tournament_id']);
            $table->dropColumn('tournament_id');

            // Tambah kolom pool_id yang relasi ke tabel pools
            $table->foreignId('pool_id')->after('id')->constrained()->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seni_matches', function (Blueprint $table) {
             // Tambahkan kembali tournament_id
             $table->foreignId('tournament_id')->after('id')->constrained()->onDelete('cascade');

             // Hapus kolom pool_id
             $table->dropForeign(['pool_id']);
             $table->dropColumn('pool_id');
        });
    }
};
