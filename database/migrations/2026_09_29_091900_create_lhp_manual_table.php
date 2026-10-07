<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('lhp_manual')) {
            return;
        }

        Schema::create('lhp_manual', function (Blueprint $table) {
            $table->id();
            $table->string('no_order', 50)->nullable();
            $table->string('no_lhp', 200)->nullable();
            $table->string('no_quotation', 50)->nullable();
            $table->string('nama_perusahaan', 255)->nullable();
            $table->string('status_sampling', 10)->nullable();
            $table->json('parameter_uji')->nullable();
            $table->string('kategori_2', 50)->nullable();
            $table->string('kategori_3', 50)->nullable();
            $table->date('tanggal_sampling')->nullable();
            $table->date('tanggal_terima')->nullable();
            $table->string('file_qr', 255)->nullable();
            $table->string('file_lhp', 255)->nullable();
            $table->date('tanggal_lhp')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('created_by', 70)->nullable();
            $table->dateTime('created_at')->nullable();
            $table->string('updated_by', 70)->nullable();
            $table->dateTime('updated_at')->nullable();
            $table->dateTime('deleted_at')->nullable();
            $table->string('deleted_by', 70)->nullable();

            $table->index('no_order', 'idx_lhp_manual_no_order');
            $table->index('no_lhp', 'idx_lhp_manual_no_lhp');
            $table->index('no_quotation', 'idx_lhp_manual_no_quotation');
            $table->index('is_active', 'idx_lhp_manual_is_active');
            $table->index('deleted_at', 'idx_lhp_manual_deleted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lhp_manual');
    }
};
