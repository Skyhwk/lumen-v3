<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCandidateProfileTables extends Migration
{
    /**
     * Run the migrations for candidate profile family of tables.
     * Includes candidate_profiles, candidate_educations, candidate_work_experiences, candidate_documents.
     *
     * @return void
     */
    public function up()
    {
        // 1. Table: candidate_profiles
        if (!Schema::hasTable('candidate_profiles')) {
            Schema::create('candidate_profiles', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->nullOnDelete();

                // Identitas
                $table->string('nama_panggilan', 100)->nullable();
                $table->string('nik_ktp', 30)->nullable();
                $table->string('no_kk', 30)->nullable();
                $table->string('no_npwp', 30)->nullable();
                $table->string('no_bpjs_ks', 30)->nullable();
                $table->string('no_bpjs_tk', 30)->nullable();

                // Agama & Status
                $table->string('agama', 50)->nullable();
                $table->string('status_pernikahan', 50)->nullable();

                // Alamat KTP
                $table->text('alamat_ktp')->nullable();
                $table->string('kota_ktp', 100)->nullable();
                $table->string('provinsi_ktp', 100)->nullable();
                $table->string('kode_pos_ktp', 20)->nullable();

                // Alamat Domisili
                $table->text('alamat_domisili')->nullable();
                $table->string('kota_domisili', 100)->nullable();
                $table->string('provinsi_domisili', 100)->nullable();
                $table->string('kode_pos_domisili', 20)->nullable();
                $table->string('status_tempat_tinggal', 100)->nullable();

                // Kontak Darurat
                $table->string('nama_kontak_darurat', 150)->nullable();
                $table->string('hubungan_kontak_darurat', 100)->nullable();
                $table->string('no_telepon_darurat', 50)->nullable();

                // Audit
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->timestamps();
            });
        }

        // 2. Table: candidate_educations
        if (!Schema::hasTable('candidate_educations')) {
            Schema::create('candidate_educations', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('candidate_profile_id')->nullable();
                $table->foreign('candidate_profile_id')
                    ->references('id')
                    ->on('candidate_profiles')
                    ->nullOnDelete();

                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->nullOnDelete();

                $table->string('jenjang_pendidikan', 50);
                $table->string('nama_institusi', 255);
                $table->string('jurusan', 255)->nullable();
                $table->decimal('nilai_ipk', 4, 2)->nullable();
                $table->integer('tahun_masuk')->nullable();
                $table->integer('tahun_lulus')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 3. Table: candidate_work_experiences
        if (!Schema::hasTable('candidate_work_experiences')) {
            Schema::create('candidate_work_experiences', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('candidate_profile_id')->nullable();
                $table->foreign('candidate_profile_id')
                    ->references('id')
                    ->on('candidate_profiles')
                    ->nullOnDelete();

                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->nullOnDelete();

                $table->string('nama_perusahaan', 255);
                $table->string('posisi_terakhir', 255);
                $table->date('tanggal_mulai')->nullable();
                $table->date('tanggal_selesai')->nullable();
                $table->text('alasan_resign')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // 4. Table: candidate_documents
        if (!Schema::hasTable('candidate_documents')) {
            Schema::create('candidate_documents', function (Blueprint $table) {
                $table->id();

                $table->unsignedBigInteger('candidate_profile_id')->nullable();
                $table->foreign('candidate_profile_id')
                    ->references('id')
                    ->on('candidate_profiles')
                    ->nullOnDelete();

                $table->unsignedBigInteger('new_recruitment_id')->nullable();
                $table->foreign('new_recruitment_id')
                    ->references('id')
                    ->on('new_recruitment')
                    ->nullOnDelete();

                $table->string('jenis_dokumen', 100);
                $table->string('nama_file', 255);
                $table->text('path_file');
                $table->string('mime_type', 100)->nullable();
                $table->unsignedBigInteger('ukuran_file')->nullable();
                $table->text('catatan')->nullable();

                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidate_work_experiences');
        Schema::dropIfExists('candidate_educations');
        Schema::dropIfExists('candidate_profiles');
    }
}
