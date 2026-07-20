<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePersiapanSampelHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('persiapan_sampel_header', function (Blueprint $table) {
            $table->increments('id');
            
            $table->string('no_document', 70)->nullable();
            $table->string('no_order', 70)->nullable();
            $table->string('no_quotation', 70)->nullable();
            $table->string('periode', 10)->nullable();
            $table->string('tanggal_sampling', 255)->nullable();
            $table->text('sampler_jadwal')->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            
            // Tipe JSON
            $table->json('no_sampel')->nullable();
            $table->json('tambahan')->nullable();
            $table->json('plastik_benthos')->nullable();
            $table->json('media_petri_dish')->nullable();
            $table->json('media_tabung')->nullable();
            $table->json('masker')->nullable();
            $table->json('sarung_tangan_karet')->nullable();
            $table->json('sarung_tangan_bintik')->nullable();
            
            $table->string('analis_berangkat', 70)->nullable();
            $table->string('sampler_berangkat', 70)->nullable();
            $table->string('analis_pulang', 70)->nullable();
            $table->string('sampler_pulang', 70)->nullable();
            
            $table->string('nama_sampler_cs', 100)->nullable();
            $table->string('file_ttd_sampler_cs', 150)->nullable();
            $table->string('filename_cs', 150)->nullable();
            
            $table->string('nama_pic_sampling_cs', 100)->nullable();
            $table->string('file_ttd_pic_sampling_cs', 150)->nullable();
            
            $table->json('tanda_tangan_bas')->nullable()->comment('tanda tangan dokumen bas');
            $table->text('catatan')->nullable();
            
            $table->json('detail_bas_documents')->nullable();
            $table->json('detail_cs_documents')->nullable();
            
            $table->text('informasi_teknis')->nullable();
            $table->string('filename_bas', 150)->nullable();
            $table->string('filename', 100)->nullable();
            
            // Boolean dari tinyint(1)
            $table->boolean('is_active')->default(1)->nullable();
            $table->boolean('is_downloaded')->default(0)->nullable();
            $table->boolean('is_printed')->default(0)->nullable();
            $table->boolean('is_printed_stps')->default(0)->nullable();
            $table->boolean('is_printed_cs')->default(0)->nullable();
            $table->boolean('is_printed_label')->default(0)->nullable();
            $table->boolean('is_printed_qr')->default(0)->nullable();
            $table->boolean('is_downloaded_stps')->default(0)->nullable();
            $table->boolean('is_downloaded_cs')->default(0)->nullable();
            $table->boolean('is_downloaded_label')->default(0)->nullable();
            $table->boolean('is_downloaded_qr')->default(0)->nullable();
            
            // Timestamps dan custom log user
            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('deleted_by', 70)->nullable();
            
            // Integers tambahan
            $table->integer('is_emailed_cs')->default(0);
            $table->integer('is_emailed_bas')->default(0);
            $table->timestamp('emailed_bas_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('persiapan_sampel_header');
    }
}
