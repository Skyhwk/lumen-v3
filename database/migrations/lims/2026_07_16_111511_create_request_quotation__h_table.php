<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestQuotationHTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('request_quotation_kontrak_H', function (Blueprint $table) {
            $table->id();
            $table->string('no_quotation', 50)->nullable();
            $table->string('no_document', 50)->nullable();
            $table->json('data_lama')->nullable();
            $table->string('pelanggan_ID', 30)->nullable();
            $table->enum('flag_status', ['draft', 'emailed', 'sp', 'ordered', 'void', 'rejected', 'reject_sp'])->nullable();
            $table->boolean('is_generate_data_lab')->default(1);
            $table->enum('status_sampling', ['SD', 'S', 'S24', 'RS', 'SP', 'SAR'])->nullable();
            $table->integer('id_cabang')->nullable();
            $table->string('nama_perusahaan', 200)->nullable();
            $table->string('konsultan', 100)->nullable();
            $table->text('alamat_kantor')->nullable();
            $table->string('no_tlp_perusahaan', 20)->nullable();
            $table->string('nama_pic_order', 100)->nullable();
            $table->string('jabatan_pic_order', 150)->nullable();
            $table->string('no_pic_order', 20)->nullable();
            $table->longText('keterangan')->nullable();
            $table->string('email_pic_order', 100)->nullable();
            $table->json('email_cc')->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->string('no_tlp_sampling', 20)->nullable();
            $table->string('nama_pic_sampling', 100)->nullable();
            $table->string('jabatan_pic_sampling', 150)->nullable();
            $table->string('no_tlp_pic_sampling', 20)->nullable();
            $table->string('email_pic_sampling', 100)->nullable();
            $table->string('periode_kontrak_awal', 10)->nullable();
            $table->string('periode_kontrak_akhir', 10)->nullable();
            $table->text('data_pendukung_sampling')->nullable();
            $table->json('data_pendukung_lain')->nullable();
            $table->json('data_pendukung_diskon')->nullable();
            
            // Total Harga
            $table->double('total_harga_air', 13, 2)->default(0.00);
            $table->double('total_harga_udara', 11, 2)->default(0.00);
            $table->double('total_harga_emisi', 11, 2)->default(0.00);
            $table->double('total_harga_padatan', 11, 2)->default(0.00);
            $table->double('total_harga_swab_test', 11, 2)->default(0.00);
            $table->double('total_harga_tanah', 11, 2)->default(0.00);
            
            // Akomodasi Kontrak
            $table->string('status_wilayah', 30)->nullable();
            $table->string('wilayah', 70)->nullable();
            $table->string('transportasi', 30)->nullable();
            $table->string('perdiem_jumlah_orang', 10)->nullable();
            $table->string('perdiem_jumlah_hari', 5)->nullable();
            $table->string('jumlah_orang_24jam', 10)->nullable();
            $table->string('jumlah_hari_24jam', 5)->nullable();
            $table->double('harga_transportasi', 11, 2)->default(0.00);
            $table->double('harga_transportasi_total', 11, 2)->default(0.00);
            $table->double('harga_personil', 11, 2)->default(0.00);
            $table->double('harga_perdiem_personil_total', 11, 2)->default(0.00);
            $table->double('harga_24jam_personil', 11, 2)->nullable();
            $table->double('harga_24jam_personil_total', 11, 2)->default(0.00);
            
            // Diskon Kontrak
            $table->string('discount_air', 10)->nullable();
            $table->double('total_discount_air', 11, 2)->default(0.00);
            $table->string('discount_non_air', 10)->nullable();
            $table->double('total_discount_non_air', 11, 2)->default(0.00);
            $table->string('discount_udara', 10)->nullable();
            $table->double('total_discount_udara', 11, 2)->default(0.00);
            $table->string('discount_emisi', 10)->nullable();
            $table->double('total_discount_emisi', 11, 2)->default(0.00);
            $table->string('discount_gabungan', 10)->nullable();
            $table->double('total_discount_gabungan', 11, 2)->default(0.00);
            $table->string('cash_discount_persen', 10)->nullable();
            $table->double('total_cash_discount_persen', 11, 2)->default(0.00);
            $table->string('discount_consultant', 10)->nullable();
            $table->string('discount_group', 10)->nullable();
            $table->double('total_discount_group', 11, 2)->default(0.00);
            $table->double('total_discount_consultant', 11, 2)->default(0.00);
            $table->double('cash_discount', 11, 2)->default(0.00);
            $table->double('total_cash_discount', 11, 2)->nullable();
            $table->text('custom_discount')->nullable();
            $table->double('total_custom_discount', 11, 2)->nullable();
            $table->string('discount_transport', 10)->default('0.00');
            $table->double('total_discount_transport', 11, 2)->default(0.00);
            $table->string('discount_perdiem', 10)->nullable();
            $table->double('total_discount_perdiem', 11, 2)->default(0.00);
            $table->string('discount_perdiem_24jam', 10)->nullable();
            $table->double('total_discount_perdiem_24jam', 11, 2)->default(0.00);
            $table->string('kode_promo', 20)->nullable();
            $table->json('discount_promo')->nullable();
            $table->double('total_discount_promo', 11, 2)->nullable();
            
            // Akumulasi Biaya Akhir
            $table->string('ppn', 5)->nullable();
            $table->double('total_ppn', 11, 2)->default(0.00);
            $table->double('total_pph', 11, 2)->default(0.00);
            $table->string('pph', 5)->nullable();
            $table->json('biaya_lain')->nullable();
            $table->double('total_biaya_lain', 11, 2)->default(0.00);
            $table->double('total_biaya_preparasi', 11, 2)->nullable();
            $table->text('biaya_diluar_pajak')->nullable();
            $table->double('total_biaya_di_luar_pajak', 11, 2)->default(0.00);
            $table->json('diluar_pajak')->nullable();
            $table->double('grand_total', 13, 2)->default(0.00);
            $table->double('total_discount', 11, 2)->default(0.00);
            $table->double('total_dpp', 13, 2)->default(0.00);
            $table->double('piutang', 14, 2)->default(0.00);
            $table->double('biaya_akhir', 14, 2)->default(0.00);
            $table->text('syarat_ketentuan')->nullable();
            $table->text('keterangan_tambahan')->nullable();
            
            // Metadata & Control Status
            $table->enum('document_status', ['Aktif', 'Non Aktif'])->default('Aktif');
            $table->string('filename', 100)->nullable();
            $table->string('jadwalfile', 70)->nullable();
            $table->date('tanggal_penawaran')->nullable();
            $table->integer('is_ready_order')->default(0);
            $table->bigInteger('sales_id')->nullable();
            
            $table->string('created_by', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 100)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_by', 100)->nullable();
            $table->timestamp('deleted_at')->nullable();
            
            $table->integer('is_active')->default(1);
            $table->integer('is_approved')->default(0);
            $table->string('approved_by', 100)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->boolean('is_rejected')->default(0);
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejected_by', 100)->nullable();
            $table->text('keterangan_reject')->nullable();
            $table->integer('is_emailed')->default(0);
            $table->timestamp('emailed_at')->nullable();
            $table->string('emailed_by', 40)->nullable();
            $table->integer('is_generated')->default(0);
            $table->string('generated_by', 100)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->integer('sp_by')->nullable();
            $table->text('keterangan_reject_sp')->nullable();
            $table->integer('id_token')->nullable();
            $table->date('expired')->nullable();
            $table->integer('konfirmasi_order')->default(0);
            $table->boolean('use_kuota')->default(0);

            // Indexing
            $table->index(['no_document', 'is_active'], 'idx_doc_active_H');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_quotation_kontrak_H');
    }
}
