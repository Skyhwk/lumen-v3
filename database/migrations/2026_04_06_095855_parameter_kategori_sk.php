<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ParameterKategoriSk extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('parameter_kategori_sk', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nama_kategori', 250)->nullable();
            $table->json('parameters')->nullable();
            $table->string('nama_kategori_asli', 250)->nullable();
            $table->string('kategori', 70)->nullable();
            $table->string('sub_kategori', 200)->nullable();
            $table->string('created_by', 70)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->string('deleted_by', 70)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->integer('is_active')->default(1);
        });
    }

    public function down()
    {
        Schema::dropIfExists('parameter_kategori_sk');
    }
}
