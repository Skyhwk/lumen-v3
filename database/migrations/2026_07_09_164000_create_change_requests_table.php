<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateChangeRequestsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('change_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nomor_dokumen', 50)->unique();
            $table->date('tanggal_permintaan');
            $table->string('pemohon', 255);
            $table->string('divisi', 100);
            $table->string('aplikasi', 100);
            $table->string('jenis_permintaan', 50); // PERUBAHAN, FITUR_BARU, PERBAIKAN_BUG
            $table->string('judul', 255);
            $table->text('latar_belakang')->nullable();
            $table->text('kondisi_saat_ini')->nullable();
            $table->text('kondisi_yang_diinginkan')->nullable();
            $table->text('dampak')->nullable(); // stored as JSON array (e.g. database, frontend, backend)
            $table->string('prioritas', 20); // CRITICAL, HIGH, MEDIUM, LOW
            $table->string('lampiran', 255)->nullable();
            
            // IT Analysis (nullable)
            $table->text('analisa_it')->nullable();
            $table->string('tingkat_kesulitan', 20)->nullable(); // MUDAH, SEDANG, SULIT
            $table->string('estimasi_pengerjaan', 50)->nullable();
            $table->string('risiko', 20)->nullable(); // RENDAH, SEDANG, TINGGI
            $table->string('developer_pic', 255)->nullable();
            
            // SDLC & Approvals (nullable)
            $table->string('disetujui_user_by', 255)->nullable();
            $table->dateTime('disetujui_user_at')->nullable();
            $table->string('disetujui_it_by', 255)->nullable();
            $table->dateTime('disetujui_it_at')->nullable();
            $table->date('tanggal_development')->nullable();
            $table->date('tanggal_testing')->nullable();
            $table->date('tanggal_release')->nullable();
            $table->string('pic_release', 255)->nullable();
            
            // Metadata
            $table->string('status', 50)->default('OPEN'); // OPEN, DEVELOPMENT, TESTING, DONE, REJECT
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 255)->nullable();
            $table->string('updated_by', 255)->nullable();
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
        Schema::dropIfExists('change_requests');
    }
}
