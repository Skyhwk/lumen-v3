<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRequestQuotationKontrakDTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('request_quotation_kontrak_D', function (Blueprint $table) {
            $table->id();
            $table->integer('id_request_quotation_kontrak_h')->nullable();
            $table->text('data_pendukung_sampling')->nullable();
            $table->string('periode_kontrak', 7)->nullable();
            
            // Breakdown Harga Detail
            $table->double('harga_air', 11, 2)->default(0.00);
            $table->double('harga_udara', 11, 2)->default(0.00);
            $table->double('harga_emisi', 11, 2)->default(0.00);
            $table->double('harga_padatan', 11, 2)->default(0.00);
            $table->double('harga_swab_test', 11, 2)->default(0.00);
            $table->double('harga_tanah', 11, 2)->default(0.00);
            $table->double('harga_pangan', 11, 2)->default(0.00);
            $table->enum('kalkulasi_by_sistem', ['on', 'off'])->default('off');
            
            // Detail Operasional Lapangan
            $table->string('transportasi', 30)->nullable();
            $table->string('perdiem_jumlah_orang', 10)->nullable();
            $table->string('perdiem_jumlah_hari', 5)->nullable();
            $table->string('jumlah_orang_24jam', 10)->nullable();
            $table->string('jumlah_hari_24jam', 5)->nullable();
            $table->double('harga_transportasi', 11, 2)->default(0.00);
            $table->double('harga_transportasi_total', 11, 2)->nullable();
            $table->double('harga_personil', 11, 2)->default(0.00);
            $table->double('harga_perdiem_personil_total', 11, 2)->nullable();
            $table->double('harga_24jam_personil', 11, 2)->default(0.00);
            $table->double('harga_24jam_personil_total', 11, 2)->nullable();
            
            // Potongan / Diskon Detail
            $table->string('status_sampling', 40)->nullable();
            $table->string('discount_air', 10)->nullable();
            $table->double('total_discount_air', 11, 2)->default(0.00);
            $table->string('discount_non_air', 10)->nullable();
            $table->double('total_discount_non_air', 11, 2)->default(0.00);
            $table->string('discount_udara', 10)->nullable();
            $table->double('total_discount_udara', 11, 2)->default(0.00);
            $table->string('discount_emisi', 10)->nullable();
            $table->string('discount_gabungan', 10)->nullable();
            $table->double('total_discount_gabungan', 11, 2)->default(0.00);
            $table->double('total_discount_emisi', 11, 2)->default(0.00);
            $table->string('cash_discount_persen', 10)->nullable();
            $table->double('total_cash_discount_persen', 11, 2)->default(0.00);
            $table->string('discount_consultant', 10)->nullable();
            $table->string('discount_group', 10)->nullable();
            $table->double('total_discount_group', 11, 2)->default(0.00);
            $table->double('total_discount_consultant', 11, 2)->default(0.00);
            $table->double('cash_discount', 11, 2)->default(0.00);
            $table->text('custom_discount')->nullable();
            $table->double('total_custom_discount', 11, 2)->nullable();
            $table->string('discount_transport', 10)->nullable();
            $table->double('total_discount_transport', 11, 2)->default(0.00);
            $table->string('discount_perdiem', 10)->nullable();
            $table->double('total_discount_perdiem', 11, 2)->default(0.00);
            $table->string('discount_perdiem_24jam', 10)->nullable();
            $table->double('total_discount_perdiem_24jam', 11, 2)->default(0.00);
            $table->string('kode_promo', 20)->nullable();
            $table->json('discount_promo')->nullable();
            $table->double('total_discount_promo', 11, 2)->nullable();
            
            // Pajak & Grand Total Detail
            $table->string('ppn', 5)->default('11%');
            $table->double('total_ppn', 11, 2)->default(0.00);
            $table->double('total_pph', 11, 2)->default(0.00);
            $table->string('pph', 5)->nullable();
            $table->text('biaya_lain')->nullable();
            $table->double('total_biaya_lain', 11, 2)->default(0.00);
            $table->json('biaya_preparasi')->nullable();
            $table->double('total_biaya_preparasi', 11, 2)->nullable();
            $table->text('biaya_di_luar_pajak')->nullable();
            $table->double('total_biaya_di_luar_pajak', 11, 2)->default(0.00);
            $table->double('grand_total', 11, 2)->default(0.00);
            $table->double('total_discount', 11, 2)->default(0.00);
            $table->double('total_dpp', 11, 2)->default(0.00);
            $table->double('piutang', 11, 2)->default(0.00);
            $table->double('biaya_akhir', 11, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_quotation_kontrak_D');
    }
}
