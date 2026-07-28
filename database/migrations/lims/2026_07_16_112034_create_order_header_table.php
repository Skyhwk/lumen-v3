<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderHeaderTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('order_header', function (Blueprint $table) {
            $table->id();
            $table->string('id_pelanggan', 30)->nullable();
            $table->string('no_order', 50)->nullable();
            $table->string('no_quotation', 50)->nullable();
            $table->string('no_document', 50)->nullable();
            $table->enum('flag_status', ['draft', 'emailed', 'sp', 'ordered', 'void'])->nullable();
            $table->integer('id_cabang')->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('konsultan', 100)->nullable();
            $table->text('alamat_kantor')->nullable();
            $table->string('no_tlp_perusahaan', 20)->nullable();
            $table->string('nama_pic_order', 250)->nullable();
            $table->string('jabatan_pic_order', 130)->nullable();
            $table->string('no_pic_order', 20)->nullable();
            $table->string('email_pic_order', 100)->nullable();
            $table->text('alamat_sampling')->nullable();
            $table->string('no_tlp_sampling', 20)->nullable();
            $table->string('nama_pic_sampling', 250)->nullable();
            $table->string('jabatan_pic_sampling', 130)->nullable();
            $table->string('no_tlp_pic_sampling', 20)->nullable();
            $table->string('email_pic_sampling', 100)->nullable();
            $table->enum('kategori_customer', ['Manufaktur', 'Domestik'])->nullable();
            $table->string('sub_kategori', 70)->nullable();
            $table->string('bahan_customer', 70)->nullable();
            $table->string('merk_customer', 70)->nullable();
            $table->string('status_wilayah', 30)->nullable();
            $table->string('wilayah', 70)->nullable();
            $table->text('syarat_ketentuan')->nullable();
            $table->text('keterangan_tambahan')->nullable();
            
            // Komponen Biaya / Finansial
            $table->double('total_ppn', 11, 2)->nullable();
            $table->double('grand_total', 11, 2)->nullable();
            $table->double('total_discount', 11, 2)->nullable();
            $table->double('total_dpp', 11, 2)->nullable();
            $table->double('piutang', 11, 2)->nullable();
            $table->double('biaya_akhir', 11, 2)->nullable();
            
            // Sistem & Kontrol Order
            $table->string('no_po', 70)->nullable();
            $table->date('tanggal_penawaran')->nullable();
            $table->date('tanggal_order')->nullable();
            $table->integer('is_revisi')->default(0);
            $table->boolean('is_generate_link')->default(0);
            $table->bigInteger('id_token')->nullable();
            $table->string('status_quotation', 100)->nullable();
            
            // Audit Trails (Manual tracking)
            $table->string('created_by', 50)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 50)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('is_active')->default(1);
            $table->string('deleted_by', 50)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('sales_id')->nullable();

            // Indexing sesuai DDL
            $table->index(['no_document', 'is_active'], 'idx_doc_active_OH');
            $table->index('is_active', 'idx_order_header_is_active');
            $table->index(['no_order', 'is_active'], 'idx_order_header_no_order_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_header');
    }
}
