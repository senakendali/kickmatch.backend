<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forget_passwords', function (Blueprint $table) {
            

            // columns
            $table->increments('id');                         // int AUTO_INCREMENT PRIMARY KEY
            $table->string('email', 50);                      // varchar(50) NOT NULL
            $table->string('url', 50);                        // varchar(50) NOT NULL
            $table->integer('status')                         // int NOT NULL DEFAULT 0
                  ->default(0)
                  ->comment('0 open / 1 done / 2 expired');

            // timestamps sesuai schema asli
            $table->timestamp('created_at')->nullable()->default(null);  // timestamp NULL DEFAULT NULL
            $table->dateTime('updated_at')->nullable()->default(null);   // datetime  NULL DEFAULT NULL
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forget_passwords');
    }
};
