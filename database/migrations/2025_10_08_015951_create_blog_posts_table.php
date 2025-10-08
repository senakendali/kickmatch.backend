<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();

            $table->string('title', 200);
            $table->string('slug', 220)->unique();

            $table->text('excerpt')->nullable();
            $table->longText('content'); // HTML/Markdown

            $table->string('cover_image')->nullable(); // path file

            // author, nullOnDelete biar post tetap ada kalau user dihapus
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // 0 = draft, 1 = published, 2 = archived
            $table->tinyInteger('status')->default(0)
                ->comment('0 draft / 1 published / 2 archived');

            $table->boolean('is_pinned')->default(false);
            $table->timestamp('published_at')->nullable()->index();

            // SEO meta
            $table->string('meta_title', 255)->nullable();
            $table->string('meta_description', 300)->nullable();
            $table->string('meta_keywords', 300)->nullable();

            $table->timestamps();
            $table->softDeletes();

            // (Optional) fulltext index kalau MySQL InnoDB mendukung
            // $table->fullText(['title', 'excerpt', 'content']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
