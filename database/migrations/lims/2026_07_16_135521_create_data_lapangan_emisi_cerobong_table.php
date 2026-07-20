<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDataLapanganEmisiCerobongTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('data_lapangan_emisi_cerobong', function (Blueprint $table) {
            $table->id();
            $table->string('no_sampel', 20)->nullable();
            $table->text('no_sampel_lama')->nullable();
            $table->integer('kategori_3')->nullable();
            $table->text('keterangan')->nullable();
            $table->text('keterangan_2')->nullable();
            
            // Titik Koordinat & Spesifikasi Cerobong
            $table->string('titik_koordinat', 50)->nullable();
            $table->string('latitude', 30)->nullable();
            $table->string('longitude', 30)->nullable();
            $table->string('sumber_emisi', 30)->nullable();
            $table->string('merk', 41)->nullable();
            $table->string('bahan_bakar', 41)->nullable();
            $table->string('cuaca', 11)->nullable();
            $table->text('kecepatan_angin')->nullable();
            $table->string('arah_pengamat', 100)->nullable();
            $table->string('diameter_cerobong', 11)->nullable();
            $table->string('durasi_operasi', 50)->nullable();
            $table->string('proses_filtrasi', 21)->nullable();
            $table->string('metode', 21)->nullable();
            $table->string('faktor_koreksi', 11)->nullable();
            $table->string('velocity', 100)->nullable();
            $table->string('data_t_flue', 11)->nullable();
            $table->text('nilai_opasitas')->nullable();
            
            // Pengukuran Lingkungan Cerobong
            $table->string('suhu', 10)->nullable();
            $table->string('kelembapan', 10)->nullable();
            $table->string('tekanan_udara', 10)->nullable();
            $table->string('waktu_pengukuran', 20)->nullable();
            
            // Parameter Gas / Hasil Uji
            $table->text('partikulat')->nullable();
            $table->string('HF', 100)->nullable();
            $table->string('HC', 15)->nullable();
            $table->string('HCI', 100)->nullable();
            $table->string('H2S', 100)->nullable();
            $table->string('NH3', 100)->nullable();
            $table->string('CI2', 100)->nullable();
            $table->text('O2')->nullable();
            $table->text('CO')->nullable();
            $table->string('CO2', 15)->nullable();
            $table->string('NO', 15)->nullable();
            $table->text('NO2')->nullable();
            $table->text('SO2')->nullable();
            $table->string('NOx', 15)->nullable();
            $table->string('T_Flue', 15)->nullable();
            
            // JSON Populasi
            $table->json('o2_populasi')->nullable();
            $table->json('co_populasi')->nullable();
            $table->json('co2_populasi')->nullable();
            $table->json('no_populasi')->nullable();
            $table->json('nox_populasi')->nullable();
            $table->json('no2_populasi')->nullable();
            $table->json('so2_populasi')->nullable();
            $table->json('t_flue_populasi')->nullable();
            $table->json('velocity_populasi')->nullable();
            
            // Titik & Deskripsi
            $table->string('suhu_ambien', 11)->nullable();
            $table->text('titik_pengamatan')->nullable();
            $table->text('titik_penentuan')->nullable();
            $table->string('tinggi_tanah', 20)->nullable();
            $table->string('tinggi_relatif', 20)->nullable();
            $table->text('deskripsi_emisi')->nullable();
            $table->text('deskripsi_latar')->nullable();
            $table->string('tipe', 5)->nullable();
            
            // Waktu & Opasitas
            $table->string('waktu_pengambilan', 20)->nullable();
            $table->string('waktu_selesai', 20)->nullable();
            $table->integer('suhu_bola')->nullable();
            $table->integer('kelembapan_opasitas')->nullable();
            $table->integer('tekanan_udara_opasitas')->nullable();
            $table->string('kapasitas', 50)->nullable();
            $table->string('waktu_opasitas', 100)->nullable();
            $table->string('ketinggian_terhadap_pengamat', 15)->nullable();
            $table->string('jarak_pengamat', 100)->nullable();
            $table->string('arah_pengamat_opasitas', 100)->nullable();
            $table->text('warna_emisi')->nullable();
            $table->text('warna_latar')->nullable();
            $table->string('status_uap', 20)->nullable();
            $table->string('arah_utara', 20)->nullable();
            $table->string('status_konstan', 20)->nullable();
            $table->text('info_tambahan')->nullable();
            
            // Dokumentasi
            $table->string('foto_struk', 50)->nullable();
            $table->string('foto_lain2', 50)->nullable();
            $table->string('foto_asap', 50)->nullable();
            $table->string('foto_lain3', 50)->nullable();
            $table->string('foto_lokasi_sampel', 50)->nullable();
            $table->string('foto_kondisi_sampel', 50)->nullable();
            $table->string('foto_lain', 50)->nullable();
            
            // Workflow Status & Audit Trails
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->integer('permission_1')->default(0);
            $table->integer('permission_2')->default(0);
            $table->integer('permission_3')->default(0);
            $table->integer('permission')->default(0);
            $table->integer('is_blocked')->default(0);
            $table->string('blocked_by', 70)->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->string('approved_by', 70)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->tinyInteger('is_rejected')->default(0);
            $table->string('rejected_by', 70)->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('tipe_delete')->nullable();
            $table->string('delete_by', 70)->nullable();
            $table->timestamp('delete_at')->nullable();

            // Indexing
            $table->index('no_sampel', 'idx_dle_no_sampel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_lapangan_emisi_cerobong');
    }
}
