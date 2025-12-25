<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('tournament_formats', function (Blueprint $table) {
            $table->id();

            $table->string('name');           // League, Group Stage, Knockout, Group + Knockout
            $table->string('slug')->unique(); // league, group-stage, knockout, group-knockout
            $table->string('code', 30)->unique(); // LEAGUE, GROUP, KNOCKOUT, GROUP_KO

            // optional: deskripsi singkat buat UI
            $table->text('description')->nullable();

            // flags biar gampang logic di code
            $table->boolean('has_groups')->default(false);
            $table->boolean('has_knockout')->default(false);

            $table->boolean('is_active')->default(true);

            // urutan tampilan
            $table->unsignedSmallInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_formats');
    }
};
