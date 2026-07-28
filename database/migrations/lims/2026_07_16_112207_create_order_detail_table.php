<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderDetailTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('order_detail', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('id_order_header')->nullable();
            $table->string('no_order', 50)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->text('alamat_perusahaan')->nullable();
            $table->string('no_quotation', 50)->nullable();
            $table->string('no_sampel', 100)->nullable();
            $table->string('koding_sampling', 100)->nullable();
            $table->enum('kontrak', ['C', 'N'])->nullable();
            $table->string('periode', 10)->nullable();
            $table->string('kategori_1', 10)->nullable();
            $table->string('fpps', 11)->nullable();
            $table->date('tanggal_sampling')->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->string('stp_stps', 50)->nullable();
            $table->string('konsultan', 250)->nullable();
            $table->string('kategori_2', 50)->nullable();
            $table->string('kategori_3', 50)->nullable();
            $table->string('cfr', 200)->nullable();
            $table->text('keterangan_1')->nullable();
            $table->string('keterangan_2', 200)->nullable();
            $table->json('parameter')->nullable();
            $table->text('regulasi')->nullable();
            $table->text('persiapan')->nullable();
            $table->string('file_koding_sampling', 50)->nullable();
            $table->string('file_koding_sampel', 100)->nullable();
            
            // Audit Trails & Status Kerja
            $table->timestamp('created_at')->nullable();
            $table->string('created_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->string('deleted_by', 70)->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->integer('is_approve')->default(0);
            $table->integer('is_active')->default(1);
            $table->integer('status')->default(0);
            $table->string('rejected_by', 150)->nullable();
            $table->timestamp('rejected_at')->nullable();

            // Indexing sesuai DDL
            $table->index('no_sampel', 'idx_no_sampel');
            $table->index('is_active', 'idx_order_detail_active');
            $table->index('id_order_header', 'idx_order_detail_id_order_header');
            $table->index('periode', 'idx_order_detail_periode');
            $table->index('tanggal_sampling', 'idx_order_detail_tanggal_sampling');
            $table->index('tanggal_terima', 'idx_order_detail_tanggal_terima');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_detail');
    }
}
