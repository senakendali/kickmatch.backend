<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('event_organizers', function (Blueprint $table) {
            $table->id();

            // owner EO (login user)
            $table->unsignedBigInteger('user_id')->unique()->index();

            // BASIC
            $table->string('organizer_name');
            $table->string('brand_name')->nullable();
            $table->enum('organizer_type', ['individual', 'community', 'cv', 'pt']);
            $table->string('phone_whatsapp', 30);
            $table->string('email');

            // lokasi (kirim id + string biar aman)
            $table->unsignedBigInteger('province_id')->nullable()->index();
            $table->unsignedBigInteger('district_id')->nullable()->index();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Indonesia');
            $table->text('address');

            $table->string('website')->nullable();
            $table->string('instagram')->nullable();

            $table->string('logo')->nullable(); // path (storage/...)

            // PIC
            $table->string('pic_name');
            $table->string('pic_position');
            $table->string('pic_phone', 30);
            $table->string('pic_email');

            // VERIFIKASI / LEGAL
            $table->string('legal_name')->nullable();
            $table->enum('legal_document_type', ['ktp', 'npwp', 'nib', 'akta'])->nullable();
            $table->string('id_number')->nullable();        // NIK/NPWP/NIB
            $table->string('npwp_number')->nullable();
            $table->string('nib_number')->nullable();
            $table->text('business_address')->nullable();

            // PAYOUT
            $table->string('bank_name')->nullable();
            $table->string('bank_account_name')->nullable();
            $table->string('bank_account_number')->nullable();
            $table->string('billing_email')->nullable();

            // status onboarding & verifikasi (optional tapi kepake banget)
            $table->boolean('onboarding_completed')->default(false)->index();
            $table->enum('verification_status', ['draft', 'submitted', 'verified', 'rejected'])->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();

            // FK optional (kalau tabel users ada)
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_organizers');
    }
};
