<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubah struktur table team_members: futsal players only.
     */
    public function up(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            /* ====== HAPUS KOLOM WARISAN / TIDAK DIPAKAI ====== */
            // Dokumen link lama (diganti upload file path)
            if (Schema::hasColumn('team_members', 'documents')) {
                $table->dropColumn('documents');
            }
            // Kolom kategori silat (tidak dipakai di player form)
            foreach ([
                'championship_category_id',
                'match_category_id',
                'age_category_id',
                'category_class_id',
                'blood_type',
                'family_card_number',
            ] as $col) {
                if (Schema::hasColumn('team_members', $col)) {
                    $table->dropColumn($col);
                }
            }

            /* ====== TAMBAHAN KOLOM FUTSAL ====== */
            // Identitas jersey & posisi
            $table->string('jersey_name')->nullable()->after('name');
            $table->unsignedTinyInteger('jersey_number')->nullable()->after('jersey_name');
            $table->enum('position', ['gk','fixo','ala','pivot','utility'])->nullable()->after('jersey_number');
            $table->enum('dominant_foot', ['left','right','both'])->default('right')->after('position');
            $table->enum('jersey_size', ['XS','S','M','L','XL','2XL'])->default('L')->after('dominant_foot');

            // Kontak
            $table->string('phone', 50)->nullable()->after('address');
            $table->string('email', 191)->nullable()->after('phone');

            // Alamat detail opsional (kita pakai hirarki region sudah ada)
            $table->string('city', 191)->nullable()->after('country_id');

            // Emergency contact
            $table->string('emergency_contact_name', 191)->nullable()->after('email');
            $table->string('emergency_contact_phone', 50)->nullable()->after('emergency_contact_name');

            // Status pemain
            $table->enum('status', ['active','injured','suspended','inactive'])->default('active')->after('registration_status');

            // Upload path file (bukan link GDrive)
            $table->string('photo_path', 255)->nullable()->after('status');
            $table->string('id_document_path', 255)->nullable()->after('photo_path');
            $table->string('medical_certificate_path', 255)->nullable()->after('id_document_path');
            $table->string('consent_form_path', 255)->nullable()->after('medical_certificate_path');

            /* ====== PENYESUAIAN KEBIJAKAN KOLOM ====== */
            // KTP jadi opsional (form: optional)
            if (Schema::hasColumn('team_members', 'nik')) {
                $table->string('nik', 255)->nullable()->change();
            }

            // Unik nomor punggung per team (NULL dibolehkan; uniq berlaku kalau tidak null)
            $table->unique(['contingent_id', 'jersey_number'], 'tm_contingent_jersey_unique');
        });
    }

    /**
     * Rollback ke struktur lama (sebisa mungkin).
     */
    public function down(): void
    {
        Schema::table('team_members', function (Blueprint $table) {
            /* ====== BALIKAN KOLOM LAMA ====== */
            // Link dokumen lama
            $table->string('documents', 255)->nullable()->after('category_class_id');

            // Kolom kategori (silat)
            $table->unsignedBigInteger('championship_category_id')->nullable()->after('address');
            $table->unsignedBigInteger('match_category_id')->nullable()->after('championship_category_id');
            $table->unsignedBigInteger('age_category_id')->nullable()->after('match_category_id');
            $table->unsignedBigInteger('category_class_id')->nullable()->after('age_category_id');

            // Darah & KK
            $table->string('blood_type', 255)->nullable()->after('body_height');
            $table->string('family_card_number', 255)->nullable()->after('nik');

            /* ====== HAPUS KOLOM TAMBAHAN FUTSAL ====== */
            // Unique composite
            $table->dropUnique('tm_contingent_jersey_unique');

            foreach ([
                'jersey_name',
                'jersey_number',
                'position',
                'dominant_foot',
                'jersey_size',
                'phone',
                'email',
                'city',
                'emergency_contact_name',
                'emergency_contact_phone',
                'status',
                'photo_path',
                'id_document_path',
                'medical_certificate_path',
                'consent_form_path',
            ] as $col) {
                if (Schema::hasColumn('team_members', $col)) {
                    $table->dropColumn($col);
                }
            }

            // KTP kembali wajib
            if (Schema::hasColumn('team_members', 'nik')) {
                $table->string('nik', 255)->nullable(false)->change();
            }
        });
    }
};
