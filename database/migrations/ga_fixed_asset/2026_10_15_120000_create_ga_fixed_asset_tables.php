<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel pendukung import Excel GA Asset (bukan skema register ga_assets).
 * Kategori aset memakai master_kategori_aset / master_sub_kategori_aset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_asset_cabang_aliases')) {
            Schema::create('ga_asset_cabang_aliases', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('alias', 191);
                $table->unsignedBigInteger('id_cabang');
                $table->timestamps();
                $table->unique('alias');
                $table->index('id_cabang');
            });
        }

        if (!Schema::hasTable('ga_asset_import_batches')) {
            Schema::create('ga_asset_import_batches', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('filename', 255);
                $table->string('sheet_name', 64)->default('DATA');
                $table->boolean('is_committed')->default(false);
                $table->unsignedInteger('rows_total')->default(0);
                $table->unsignedInteger('rows_imported')->default(0);
                $table->unsignedInteger('rows_updated')->default(0);
                $table->unsignedInteger('rows_skipped')->default(0);
                $table->unsignedInteger('rows_failed')->default(0);
                $table->unsignedBigInteger('created_by')->nullable();
                $table->string('created_by_name', 191)->nullable();
                $table->timestamps();
                $table->index(['is_committed', 'created_at']);
            });
        }

        if (!Schema::hasTable('ga_asset_import_errors')) {
            Schema::create('ga_asset_import_errors', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('batch_id');
                $table->unsignedInteger('row_number')->nullable();
                $table->string('asset_code', 64)->nullable();
                $table->text('message');
                $table->timestamp('created_at')->useCurrent();
                $table->index('batch_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_asset_import_errors');
        Schema::dropIfExists('ga_asset_import_batches');
        Schema::dropIfExists('ga_asset_cabang_aliases');
    }
};
