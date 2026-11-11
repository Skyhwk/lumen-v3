<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_wilayah_k3')) {
            Schema::create('master_wilayah_k3', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('wilayah_pelanggan', 255);
                $table->unsignedBigInteger('id_cabang')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->string('deleted_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();
                $table->dateTime('deleted_at')->nullable();

                $table->index(['is_active', 'wilayah_pelanggan']);
                $table->index('id_cabang');
            });
        }

        if (!Schema::hasTable('master_wilayah_k3_sampling')) {
            Schema::create('master_wilayah_k3_sampling', function (Blueprint $table) {
                $table->unsignedBigInteger('master_wilayah_k3_id');
                $table->unsignedBigInteger('id_master_wilayah_sampling');

                $table->primary(
                    ['master_wilayah_k3_id', 'id_master_wilayah_sampling'],
                    'pk_master_wilayah_k3_sampling'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_wilayah_k3_sampling');
        Schema::dropIfExists('master_wilayah_k3');
    }
};
