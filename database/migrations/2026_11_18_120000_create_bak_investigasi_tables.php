<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Form Investigasi Awal (F1) — Kode Dokumen: ISL-FIAW-10-T1.Rev0
 *
 * Mapping header:
 * - Case Code (form) → no_document
 * - Status Case → status_case (DSC-TS | KM | DSC-DS)
 *
 * Belum dijalankan — untuk review skema saja.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            Schema::create('bak_investigasi', function (Blueprint $table) {
                $table->bigIncrements('id');

                // Header form
                $table->string('no_document', 100)->unique()->comment('Case Code pada form F1');
                $table->enum('status_case', ['DSC-TS', 'KM', 'DSC-DS'])->nullable();
                $table->string('kode_dokumen', 50)->nullable()->default('ISL-FIAW-10-T1.Rev0');

                // A. DATA KEJADIAN
                $table->string('judul_kejadian', 500)->nullable();
                $table->date('tanggal_kejadian')->nullable()->comment('Hari/Tgl Kejadian');
                $table->string('pukul_kejadian', 10)->nullable()->comment('Format HH:mm');
                $table->string('lokasi_kejadian', 255)->nullable();
                $table->date('tgl_bak_pertama')->nullable();
                $table->date('tgl_bak_terakhir')->nullable();
                $table->string('no_bak_pertama', 100)->nullable()->comment('No BAK (dari)');
                $table->string('no_bak_terakhir', 100)->nullable()->comment('No BAK (s/d)');
                $table->unsignedSmallInteger('jumlah_bukti')->nullable();
                $table->text('jenis_bukti')->nullable();

                // C. RINGKASAN KRONOLOGI (5W+1H)
                $table->longText('kronologi_what')->nullable()->comment('What (Apa)');
                $table->longText('kronologi_who')->nullable()->comment('Who (Siapa)');
                $table->longText('kronologi_when')->nullable()->comment('When (Kapan)');
                $table->longText('kronologi_where')->nullable()->comment('Where (Di Mana)');
                $table->longText('kronologi_why')->nullable()->comment('Why (Kenapa)');
                $table->longText('kronologi_how')->nullable()->comment('How (Bagaimana)');
                $table->longText('kronologi_catatan_khusus')->nullable();

                // D. IDENTIFIKASI PENYEBAB AWAL (checklist + keterangan)
                $table->boolean('penyebab_hmf')->default(false)->comment('Human Factor');
                $table->text('penyebab_hmf_keterangan')->nullable();
                $table->boolean('penyebab_sys')->default(false)->comment('System Failure');
                $table->text('penyebab_sys_keterangan')->nullable();
                $table->boolean('penyebab_tec')->default(false)->comment('Technical / Equipment');
                $table->text('penyebab_tec_keterangan')->nullable();
                $table->boolean('penyebab_ext')->default(false)->comment('External');
                $table->text('penyebab_ext_keterangan')->nullable();
                $table->boolean('penyebab_lainnya')->default(false);
                $table->text('penyebab_lainnya_keterangan')->nullable();

                // E. DAMPAK KEJADIAN (kategori + rincian checklist)
                $table->boolean('dampak_fin')->default(false)->comment('Kerugian Langsung');
                $table->boolean('dampak_fin_aset')->default(false);
                $table->boolean('dampak_ops')->default(false)->comment('Gangguan Proses Kerja');
                $table->boolean('dampak_ops_delay_layanan')->default(false);
                $table->boolean('dampak_hse')->default(false);
                $table->boolean('dampak_hse_near_miss')->default(false);
                $table->boolean('dampak_hse_injury')->default(false);
                $table->boolean('dampak_hse_fatality')->default(false);
                $table->boolean('dampak_leg')->default(false)->comment('Pelanggaran Peraturan');
                $table->boolean('dampak_leg_risiko_sanksi')->default(false);
                $table->boolean('dampak_lainnya')->default(false);
                $table->text('dampak_lainnya_keterangan')->nullable();
                $table->longText('dampak_catatan_khusus')->nullable();

                // G.1 Keputusan Tindak Lanjut
                $table->enum('tindak_lanjut_awal', [
                    'internal_hr_direv',
                    'komite_manajemen',
                    'tanpa_sanksi',
                ])->nullable()->comment('Internal HR Direv | Komite Manajemen | Tanpa Sanksi');
                $table->boolean('sanksi_st')->default(false)->comment('Surat Teguran');
                $table->string('sanksi_st_detail', 255)->nullable();
                $table->boolean('sanksi_sp')->default(false)->comment('Surat Peringatan');
                $table->string('sanksi_sp_detail', 255)->nullable()->comment('mis. SP-1 / SP-2');
                $table->boolean('sanksi_ganti_rugi')->default(false);
                $table->string('sanksi_ganti_rugi_detail', 255)->nullable();
                $table->boolean('sanksi_lainnya')->default(false);
                $table->string('sanksi_lainnya_detail', 255)->nullable();
                $table->longText('catatan_tambahan')->nullable()->comment('Catatan Tambahan / Pertimbangan');

                // Meta dokumen (pola modul disipliner)
                $table->string('status', 20)->default('draft');
                $table->string('nama_berkas', 255)->nullable();
                $table->text('file_path')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('file_qr', 255)->nullable();
                $table->string('created_by', 150)->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->string('published_by', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['status_case', 'status'], 'bak_inv_status_case_status_idx');
                $table->index(['tanggal_kejadian', 'status'], 'bak_inv_tgl_kejadian_status_idx');
                $table->index('no_bak_pertama', 'bak_inv_no_bak_pertama_idx');
            });
        }

        // B. PIHAK TERLIBAT (Pelapor / Terlapor / Saksi / Lainnya)
        if (!Schema::hasTable('bak_investigasi_pihak')) {
            Schema::create('bak_investigasi_pihak', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('bak_investigasi_id');
                $table->unsignedTinyInteger('urutan')->default(1);
                $table->string('nama', 150)->nullable();
                $table->string('divisi', 150)->nullable();
                $table->enum('peran', ['pelapor', 'terlapor', 'saksi', 'lainnya'])
                    ->nullable()
                    ->comment('Kolom Keterangan pada form B');
                $table->string('keterangan', 255)->nullable()->comment('Detail tambahan jika peran=lainnya');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['bak_investigasi_id', 'urutan'], 'bak_inv_pihak_parent_urutan_idx');
                $table->foreign('bak_investigasi_id', 'bak_inv_pihak_fk')
                    ->references('id')
                    ->on('bak_investigasi')
                    ->onDelete('cascade');
            });
        }

        // F. TINDAK LANJUT USER
        if (!Schema::hasTable('bak_investigasi_tindak_lanjut')) {
            Schema::create('bak_investigasi_tindak_lanjut', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('bak_investigasi_id');
                $table->unsignedTinyInteger('urutan')->default(1);
                $table->text('corrective_action')->nullable()->comment('Perbaikan');
                $table->text('preventive_action')->nullable()->comment('Pencegahan');
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['bak_investigasi_id', 'urutan'], 'bak_inv_tl_parent_urutan_idx');
                $table->foreign('bak_investigasi_id', 'bak_inv_tl_fk')
                    ->references('id')
                    ->on('bak_investigasi')
                    ->onDelete('cascade');
            });
        }

        // G.2 Lembar Pengesahan
        if (!Schema::hasTable('bak_investigasi_pengesahan')) {
            Schema::create('bak_investigasi_pengesahan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('bak_investigasi_id');
                $table->unsignedTinyInteger('urutan')->default(1);
                $table->enum('keterangan', [
                    'disusun_oleh',
                    'disetujui_oleh',
                    'diketahui_oleh',
                ])->nullable();
                $table->string('nama', 150)->nullable();
                $table->string('jabatan', 150)->nullable();
                $table->date('tanggal')->nullable();
                $table->text('tanda_tangan')->nullable()->comment('Path file / base64 signature');
                $table->unsignedInteger('karyawan_id')->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();

                $table->index(['bak_investigasi_id', 'urutan'], 'bak_inv_pengesahan_parent_urutan_idx');
                $table->foreign('bak_investigasi_id', 'bak_inv_pengesahan_fk')
                    ->references('id')
                    ->on('bak_investigasi')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bak_investigasi_pengesahan');
        Schema::dropIfExists('bak_investigasi_tindak_lanjut');
        Schema::dropIfExists('bak_investigasi_pihak');
        Schema::dropIfExists('bak_investigasi');
    }
};
