<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateNewRecruitmentTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('new_recruitment', function (Blueprint $table) {
            $table->id();
            $table->string('nama_lengkap', 255);
            $table->string('tempat_lahir', 255)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->enum('jenis_kelamin', ['Male', 'Female'])->nullable();
            $table->text('alamat_ktp')->nullable();
            $table->text('alamat_domisili')->nullable();
            $table->string('no_telepon', 50)->nullable();
            $table->string('email', 150)->nullable();
            $table->json('pendidikan')->nullable();
            $table->json('pengalaman_kerja')->nullable();
            $table->string('shio', 100)->nullable();
            $table->string('elemen', 100)->nullable();
            
            // Foreign key ke tabel personel_request
            $table->unsignedBigInteger('personal_request_id')->nullable();
            $table->foreign('personal_request_id')
                ->references('id')
                ->on('personel_request')
                ->nullOnDelete();

            // Snapshot nama posisi yang dilamar
            $table->string('posisi_dilamar', 255)->nullable();
            $table->double('nilai_kecocokan')->default(0);

            $table->decimal('gaji_terakhir', 15, 2)->nullable();
            $table->decimal('ekspetasi_gaji', 15, 2)->default(0.00);
            $table->date('tanggal_join_tercepat')->nullable();
            
            // Status & Persetujuan
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->string('approved_by', 255)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('rejected_by', 255)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('alasan_reject')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('new_recruitment');
    }
}
