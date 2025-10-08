<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('slug', 140)->unique();
            $table->text('description')->nullable();

            // support subcategory (opsional)
            $table->foreignId('parent_id')->nullable()
                ->constrained('blog_categories')->nullOnDelete();

            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('ordering')->default(0);

            $table->timestamps();

            $table->index(['is_active', 'ordering']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_categories');
    }
};
