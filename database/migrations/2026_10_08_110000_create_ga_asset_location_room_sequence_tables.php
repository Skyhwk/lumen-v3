<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lokasi, ruang, dan penghitung nomor CS.
 * Jenis aset memakai master_sub_kategori_aset yang sudah ada (id INT).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_asset_locations')) {
            Schema::create('ga_asset_locations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id');
                $table->string('code', 50);
                $table->string('name', 150);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->foreign('branch_id', 'ga_asset_locations_branch_fk')
                    ->references('id')
                    ->on('master_cabang')
                    ->restrictOnDelete();

                $table->unique(['branch_id', 'code'], 'ga_asset_locations_branch_code_unique');
                $table->index('is_active', 'ga_asset_locations_is_active_index');
            });
        }

        if (!Schema::hasTable('ga_asset_rooms')) {
            Schema::create('ga_asset_rooms', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('location_id');
                $table->string('code', 80)->nullable();
                $table->string('name', 200);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->foreign('location_id', 'ga_asset_rooms_location_fk')
                    ->references('id')
                    ->on('ga_asset_locations')
                    ->restrictOnDelete();

                $table->unique(['location_id', 'name'], 'ga_asset_rooms_location_name_unique');
                $table->index('is_active', 'ga_asset_rooms_is_active_index');
            });
        }

        if (!Schema::hasTable('ga_asset_sequences')) {
            Schema::create('ga_asset_sequences', function (Blueprint $table) {
                $table->id();
                $table->integer('sub_kategori_aset_id');
                $table->unsignedInteger('last_number')->default(0);
                $table->dateTime('updated_at')->nullable();

                $table->foreign('sub_kategori_aset_id', 'ga_asset_sequences_sub_kategori_fk')
                    ->references('id')
                    ->on('master_sub_kategori_aset')
                    ->restrictOnDelete();

                $table->unique('sub_kategori_aset_id', 'ga_asset_sequences_sub_kategori_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_asset_sequences');
        Schema::dropIfExists('ga_asset_rooms');
        Schema::dropIfExists('ga_asset_locations');
    }
};
