<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Register aset Fase 1: unit/komponen, perolehan, identitas, lampiran, audit, dan kunci idempotensi.
 * Kategori dan jenis memakai master_kategori_aset serta master_sub_kategori_aset.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ga_assets')) {
            Schema::create('ga_assets', function (Blueprint $table) {
                $table->id();
                $table->char('public_id', 26);
                $table->string('asset_code', 40);
                $table->string('record_kind', 20)->default('unit');
                $table->unsignedBigInteger('parent_asset_id')->nullable();
                $table->integer('sub_kategori_aset_id');
                $table->integer('kategori_aset_id');
                $table->string('name', 255);
                $table->string('brand', 150)->nullable();
                $table->string('model_spec', 255)->nullable();
                $table->string('cs_code', 120)->nullable();
                $table->unsignedInteger('cs_sequence')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->unsignedBigInteger('location_id')->nullable();
                $table->unsignedBigInteger('room_id')->nullable();
                $table->bigInteger('pic_employee_id')->nullable();
                $table->string('department_note', 150)->nullable();
                $table->string('acquisition_origin', 20);
                $table->string('condition', 20);
                $table->string('lifecycle_state', 20)->default('active');
                $table->string('usage_state', 20)->default('unknown');
                $table->string('publication_state', 20)->default('draft');
                $table->string('verification_state', 20)->default('needs_review');
                $table->string('label_legacy', 20)->nullable();
                $table->text('condition_note')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->dateTime('archived_at')->nullable();
                $table->string('archived_by', 255)->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 255)->nullable();
                $table->string('updated_by', 255)->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->foreign('parent_asset_id', 'ga_assets_parent_fk')
                    ->references('id')
                    ->on('ga_assets')
                    ->restrictOnDelete();
                $table->foreign('sub_kategori_aset_id', 'ga_assets_sub_kategori_fk')
                    ->references('id')
                    ->on('master_sub_kategori_aset')
                    ->restrictOnDelete();
                $table->foreign('kategori_aset_id', 'ga_assets_kategori_fk')
                    ->references('id')
                    ->on('master_kategori_aset')
                    ->restrictOnDelete();
                $table->foreign('branch_id', 'ga_assets_branch_fk')
                    ->references('id')
                    ->on('master_cabang')
                    ->restrictOnDelete();
                $table->foreign('location_id', 'ga_assets_location_fk')
                    ->references('id')
                    ->on('ga_asset_locations')
                    ->restrictOnDelete();
                $table->foreign('room_id', 'ga_assets_room_fk')
                    ->references('id')
                    ->on('ga_asset_rooms')
                    ->restrictOnDelete();
                $table->foreign('pic_employee_id', 'ga_assets_pic_fk')
                    ->references('id')
                    ->on('master_karyawan')
                    ->restrictOnDelete();

                $table->unique('public_id', 'ga_assets_public_id_unique');
                $table->unique('asset_code', 'ga_assets_asset_code_unique');
                $table->unique('cs_code', 'ga_assets_cs_code_unique');
                $table->unique(
                    ['sub_kategori_aset_id', 'cs_sequence'],
                    'ga_assets_sub_kategori_cs_sequence_unique'
                );
                $table->index(
                    ['branch_id', 'publication_state', 'id'],
                    'ga_assets_branch_publication_index'
                );
                $table->index(
                    ['sub_kategori_aset_id', 'condition'],
                    'ga_assets_sub_kategori_condition_index'
                );
            });
        }

        if (!Schema::hasTable('ga_asset_acquisitions')) {
            Schema::create('ga_asset_acquisitions', function (Blueprint $table) {
                $table->unsignedBigInteger('asset_id')->primary();
                $table->date('acquisition_date')->nullable();
                $table->string('date_precision', 20)->default('unknown');
                $table->smallInteger('date_raw_d')->nullable();
                $table->smallInteger('date_raw_m')->nullable();
                $table->smallInteger('date_raw_y')->nullable();
                $table->decimal('amount', 18, 2)->nullable();
                $table->char('currency', 3)->default('IDR');
                $table->string('value_basis', 30)->default('unknown');
                $table->smallInteger('useful_life_years')->nullable();
                $table->text('accurate_note')->nullable();
                $table->text('source_note')->nullable();

                $table->foreign('asset_id', 'ga_asset_acquisitions_asset_fk')
                    ->references('id')
                    ->on('ga_assets')
                    ->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('ga_asset_identifiers')) {
            Schema::create('ga_asset_identifiers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->string('scheme', 30);
                $table->string('value_raw', 255);
                $table->string('value_normalized', 255);
                $table->boolean('is_primary')->default(false);
                $table->string('source_ref', 100)->nullable();

                $table->foreign('asset_id', 'ga_asset_identifiers_asset_fk')
                    ->references('id')
                    ->on('ga_assets')
                    ->restrictOnDelete();

                $table->index(['scheme', 'value_normalized'], 'ga_asset_identifiers_scheme_value_index');
            });
        }

        if (!Schema::hasTable('ga_asset_attachments')) {
            Schema::create('ga_asset_attachments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->string('storage_key', 255);
                $table->string('original_name', 255);
                $table->string('mime', 100);
                $table->unsignedBigInteger('size_bytes');
                $table->char('sha256', 64)->nullable();
                $table->string('uploaded_by', 255)->nullable();
                $table->string('visibility', 20)->default('internal');
                $table->dateTime('archived_at')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->foreign('asset_id', 'ga_asset_attachments_asset_fk')
                    ->references('id')
                    ->on('ga_assets')
                    ->restrictOnDelete();
            });
        }

        if (!Schema::hasTable('ga_asset_events')) {
            Schema::create('ga_asset_events', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('asset_id');
                $table->string('event_type', 50);
                $table->string('actor_id', 255)->nullable();
                $table->dateTime('occurred_at');
                $table->text('reason')->nullable();
                $table->json('before_json')->nullable();
                $table->json('after_json')->nullable();
                $table->string('request_id', 64)->nullable();
                $table->string('source_ref', 100)->nullable();

                $table->foreign('asset_id', 'ga_asset_events_asset_fk')
                    ->references('id')
                    ->on('ga_assets')
                    ->restrictOnDelete();

                $table->index(['asset_id', 'id'], 'ga_asset_events_asset_id_index');
            });
        }

        if (!Schema::hasTable('ga_asset_command_receipts')) {
            Schema::create('ga_asset_command_receipts', function (Blueprint $table) {
                $table->id();
                $table->string('scope', 50);
                $table->string('idempotency_key', 64);
                $table->char('payload_hash', 64);
                $table->string('status', 20);
                $table->json('result_json')->nullable();
                $table->dateTime('created_at')->nullable();

                $table->unique(['scope', 'idempotency_key'], 'ga_asset_command_receipts_scope_key_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_asset_command_receipts');
        Schema::dropIfExists('ga_asset_events');
        Schema::dropIfExists('ga_asset_attachments');
        Schema::dropIfExists('ga_asset_identifiers');
        Schema::dropIfExists('ga_asset_acquisitions');
        Schema::dropIfExists('ga_assets');
    }
};
