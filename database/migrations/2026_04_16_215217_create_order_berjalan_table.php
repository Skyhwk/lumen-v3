<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrderBerjalanTable extends Migration
{
    public function up()
    {
        Schema::create('order_berjalan', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('id_pelanggan', 10)->nullable();
            $table->string('jenis_order', 70)->nullable();
            $table->string('no_penawaran', 70)->nullable();
            $table->string('no_order', 10)->nullable();
            $table->date('tgl_order')->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('alamat_sampling', 255)->nullable();
            $table->boolean('is_revisi')->default(0);
            $table->tinyInteger('sales_id')->nullable();

            // 🔥 JSON besar
            $table->longText('dataOrderDetail')->nullable();

            $table->boolean('status_selesai')->default(0);

            $table->timestamps();

            // optional index biar cepat
            $table->index('no_order');
            $table->index('status_selesai');
        });
    }

    public function down()
    {
        Schema::dropIfExists('order_berjalan');
    }
}