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
        Schema::create('navigation_menus', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // name of the menu item
            $table->string('url')->nullable(); // link for the menu item
            $table->foreignId('parent_id')->nullable()->constrained('navigation_menus')->onDelete('cascade'); // foreign key to create the parent-child relationship
            $table->integer('order')->default(0); // order for sorting menu items
            $table->enum('type', ['public', 'admin'])->default('public'); // add type column (public or admin)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_menus');
    }
};
