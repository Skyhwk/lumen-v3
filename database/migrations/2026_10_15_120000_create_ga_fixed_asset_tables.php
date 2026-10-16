<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_asset_category_l1')) {
            Schema::create('ga_asset_category_l1', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('code', 64);
                $table->string('name', 128);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique('code');
            });
        }

        if (!Schema::hasTable('ga_asset_category_l2')) {
            Schema::create('ga_asset_category_l2', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('category_l1_id');
                $table->string('code', 64);
                $table->string('name', 128);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['category_l1_id', 'code']);
                $table->index('category_l1_id');
            });
        }

        if (!Schema::hasTable('ga_asset_rooms')) {
            Schema::create('ga_asset_rooms', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('id_cabang');
                $table->string('name', 191);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['id_cabang', 'name']);
                $table->index('id_cabang');
            });
        }

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

        if (!Schema::hasTable('ga_assets')) {
            Schema::create('ga_assets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('asset_code', 64);
                $table->string('barcode_value', 64);
                $table->unsignedBigInteger('category_l1_id')->nullable();
                $table->unsignedBigInteger('category_l2_id')->nullable();
                $table->string('asset_name', 191)->nullable();
                $table->string('brand', 191)->nullable();
                $table->string('spec', 191)->nullable();
                $table->date('purchase_date')->nullable();
                $table->decimal('acquisition_cost', 18, 2)->nullable();
                $table->string('acquisition_status', 32)->default('new');
                $table->string('operational_status', 32)->default('normal');
                $table->boolean('label_applied')->default(false);
                $table->unsignedBigInteger('id_cabang')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->unsignedSmallInteger('useful_life_years')->nullable();
                $table->string('accurate_sync_flag', 8)->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedBigInteger('last_import_batch_id')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->unique('asset_code');
                $table->index('barcode_value');
                $table->index(['id_cabang', 'room_id']);
                $table->index('operational_status');
                $table->index('category_l1_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_assets');
        Schema::dropIfExists('ga_asset_import_errors');
        Schema::dropIfExists('ga_asset_import_batches');
        Schema::dropIfExists('ga_asset_cabang_aliases');
        Schema::dropIfExists('ga_asset_rooms');
        Schema::dropIfExists('ga_asset_category_l2');
        Schema::dropIfExists('ga_asset_category_l1');
    }
};
