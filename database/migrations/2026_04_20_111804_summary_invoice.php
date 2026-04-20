<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class SummaryInvoice extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('summary_invoice', function (Blueprint $table) {
            $table->id();
        
            $table->string('no_invoice')->unique();
        
            $table->string('created_by')->nullable();
            $table->string('faktur_pajak')->nullable();
        
            $table->decimal('total_tagihan', 18, 2)->default(0);
            $table->decimal('nilai_tagihan', 18, 2)->default(0);
        
            $table->string('rekening')->nullable();
            $table->text('keterangan')->nullable();
        
            $table->string('nama_pj')->nullable();
            $table->string('jabatan_pj')->nullable();
        
            $table->date('tgl_invoice')->nullable();
            $table->string('no_faktur')->nullable();
        
            $table->text('alamat_penagihan')->nullable();
            $table->string('nama_pic')->nullable();
            $table->string('no_pic')->nullable();
            $table->string('email_pic')->nullable();
            $table->string('jabatan_pic')->nullable();
        
            $table->string('no_po')->nullable();
            $table->string('no_spk')->nullable();
        
            $table->date('tgl_jatuh_tempo')->nullable();
        
            $table->string('filename')->nullable();
            $table->string('upload_file')->nullable();
            $table->string('file_pph')->nullable();
        
            $table->string('consultant')->nullable();
            $table->string('document')->nullable();
        
            $table->unsignedBigInteger('sales_id')->nullable();
            $table->string('sales_penanggung_jawab')->nullable();
        
            $table->timestamp('created_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->string('emailed_by')->nullable();
        
            $table->timestamp('tgl_pelunasan')->nullable();
            $table->decimal('nilai_pelunasan', 18, 2)->default(0);
        
            $table->boolean('is_generate')->default(false);
            $table->string('generated_by')->nullable();
            $table->timestamp('generated_at')->nullable();
        
            $table->boolean('expired')->default(false);
        
            $table->unsignedBigInteger('pelanggan_id')->nullable();
            $table->text('detail_pendukung')->nullable();
        
            $table->string('nama_customer')->nullable();
            $table->boolean('is_revisi')->default(false);
        
            $table->text('no_orders')->nullable();
            $table->string('status_lunas')->nullable();
        
            $table->json('history')->nullable();
        
            $table->timestamp('updated_at')->nullable();
        
            $table->index('no_invoice');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('summary_invoice');
    }
}
