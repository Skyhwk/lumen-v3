<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Skema lengkap modul GA Fixed Asset (tanpa menu — menu ditambah manual).
 * Kategori: master_kategori_aset / master_sub_kategori_aset. Cabang: master_cabang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->createLocationRoomSequenceTables();
        $this->createAssetRegisterTables();
        $this->createImportSupportTables();
        $this->createMovementAndOpnameTables();
        $this->createDepreciationTables();
        $this->seedCabangAliases();
        $this->seedUsefulLifeRules();
        $this->syncSequencesFromExistingAssets();
    }

    public function down(): void
    {
        Schema::dropIfExists('ga_asset_depreciation_yearly');
        Schema::dropIfExists('ga_asset_useful_life_rules');
        Schema::dropIfExists('ga_asset_opname_lines');
        Schema::dropIfExists('ga_asset_opnames');
        Schema::dropIfExists('ga_asset_movements');
        Schema::dropIfExists('ga_asset_import_errors');
        Schema::dropIfExists('ga_asset_import_batches');
        Schema::dropIfExists('ga_asset_cabang_aliases');
        Schema::dropIfExists('ga_asset_command_receipts');
        Schema::dropIfExists('ga_asset_events');
        Schema::dropIfExists('ga_asset_attachments');
        Schema::dropIfExists('ga_asset_identifiers');
        Schema::dropIfExists('ga_asset_acquisitions');
        Schema::dropIfExists('ga_assets');
        Schema::dropIfExists('ga_asset_sequences');
        Schema::dropIfExists('ga_asset_rooms');
        Schema::dropIfExists('ga_asset_locations');
    }

    private function createLocationRoomSequenceTables(): void
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

    private function createAssetRegisterTables(): void
    {
        if (!Schema::hasTable('ga_assets')) {
            Schema::create('ga_assets', function (Blueprint $table) {
                $table->id();
                $table->char('public_id', 26);
                $table->string('asset_code', 80);
                $table->string('record_kind', 20)->default('unit');
                $table->unsignedBigInteger('parent_asset_id')->nullable();
                $table->integer('sub_kategori_aset_id');
                $table->integer('kategori_aset_id');
                $table->string('name', 255);
                $table->string('brand', 150)->nullable();
                $table->text('model_spec')->nullable();
                $table->string('cs_code', 80)->nullable();
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
                $table->date('purchase_date')->nullable();
                $table->decimal('acquisition_cost', 18, 2)->nullable();
                $table->unsignedSmallInteger('useful_life_years')->nullable();
                $table->string('accurate_sync_flag', 8)->nullable();
                $table->string('condition_text', 191)->nullable();
                $table->date('damage_received_date')->nullable();
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

    private function createImportSupportTables(): void
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

    private function createMovementAndOpnameTables(): void
    {
        if (!Schema::hasTable('ga_asset_movements')) {
            Schema::create('ga_asset_movements', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_id');
                $table->string('movement_kind', 16);
                $table->unsignedBigInteger('from_branch_id')->nullable();
                $table->unsignedBigInteger('to_branch_id')->nullable();
                $table->unsignedBigInteger('from_location_id')->nullable();
                $table->unsignedBigInteger('to_location_id')->nullable();
                $table->unsignedBigInteger('from_room_id')->nullable();
                $table->unsignedBigInteger('to_room_id')->nullable();
                $table->string('note', 500)->nullable();
                $table->string('source', 16)->default('manual');
                $table->string('created_by', 191)->nullable();
                $table->dateTime('moved_at');
                $table->timestamps();
                $table->index(['asset_id', 'moved_at']);
            });
        }

        if (!Schema::hasTable('ga_asset_opnames')) {
            Schema::create('ga_asset_opnames', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('branch_id');
                $table->unsignedSmallInteger('period_year');
                $table->unsignedTinyInteger('period_month');
                $table->string('status', 16)->default('open');
                $table->string('opened_by', 191)->nullable();
                $table->dateTime('closed_at')->nullable();
                $table->timestamps();
                $table->unique(['branch_id', 'period_year', 'period_month'], 'ga_asset_opname_period_unique');
            });
        }

        if (!Schema::hasTable('ga_asset_opname_lines')) {
            Schema::create('ga_asset_opname_lines', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('opname_id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('asset_id')->nullable();
                $table->string('scanned_code', 80);
                $table->string('result', 16);
                $table->string('scanned_by', 191)->nullable();
                $table->dateTime('scanned_at');
                $table->timestamps();
                $table->unique(['opname_id', 'scanned_code'], 'ga_asset_opname_scan_unique');
                $table->index(['opname_id', 'result']);
            });
        }
    }

    private function createDepreciationTables(): void
    {
        if (!Schema::hasTable('ga_asset_useful_life_rules')) {
            Schema::create('ga_asset_useful_life_rules', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('source', 16);
                $table->string('category_name', 191);
                $table->string('sub_category_name', 191)->default('');
                $table->unsignedSmallInteger('useful_life_years');
                $table->timestamps();
                $table->unique(['source', 'category_name', 'sub_category_name'], 'ga_asset_life_rule_unique');
            });
        }

        if (!Schema::hasTable('ga_asset_depreciation_yearly')) {
            Schema::create('ga_asset_depreciation_yearly', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_id');
                $table->unsignedSmallInteger('fiscal_year');
                $table->decimal('acquisition_cost', 18, 2);
                $table->unsignedSmallInteger('useful_life_years');
                $table->string('useful_life_source', 16);
                $table->decimal('annual_depreciation', 18, 2);
                $table->decimal('accumulated_depreciation', 18, 2);
                $table->decimal('book_value', 18, 2);
                $table->timestamps();
                $table->unique(['asset_id', 'fiscal_year'], 'ga_asset_depr_year_unique');
                $table->index('fiscal_year');
            });
        }
    }

    private function seedCabangAliases(): void
    {
        if (!Schema::hasTable('ga_asset_cabang_aliases')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['alias' => 'PUSAT', 'id_cabang' => 1],
            ['alias' => 'HEAD OFFICE', 'id_cabang' => 1],
            ['alias' => 'CABANG KARAWANG', 'id_cabang' => 4],
            ['alias' => 'RO-KARAWANG', 'id_cabang' => 4],
            ['alias' => 'KARAWANG', 'id_cabang' => 4],
            ['alias' => 'CABANG PEMALANG', 'id_cabang' => 5],
            ['alias' => 'RO-PEMALANG', 'id_cabang' => 5],
            ['alias' => 'PEMALANG', 'id_cabang' => 5],
            ['alias' => 'CABANG PEMALANG - KARAWANG', 'id_cabang' => 5],
            ['alias' => 'DIREKSI', 'id_cabang' => 1],
            ['alias' => 'GUDANG', 'id_cabang' => 1],
            ['alias' => 'PANTRY', 'id_cabang' => 1],
        ];

        foreach ($rows as $row) {
            DB::table('ga_asset_cabang_aliases')->updateOrInsert(
                ['alias' => $row['alias']],
                [
                    'id_cabang' => $row['id_cabang'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function seedUsefulLifeRules(): void
    {
        if (!Schema::hasTable('ga_asset_useful_life_rules')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $rows = [
            ['source' => 'category', 'category_name' => 'KENDARAAN', 'sub_category_name' => '', 'useful_life_years' => 7],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'SAMPLING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'LAB', 'useful_life_years' => 7],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'KANTOR', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
            ['source' => 'category', 'category_name' => 'PERALATAN', 'sub_category_name' => 'LAINNYA', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'HOUSEKEEPING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'KANTOR', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'LAB', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'SAMPLING', 'useful_life_years' => 5],
            ['source' => 'category', 'category_name' => 'PERLENGKAPAN', 'sub_category_name' => 'LAINNYA', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT ANGKUTAN DARAT BERMOTOR', 'sub_category_name' => 'KENDARAAN', 'useful_life_years' => 7],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT UKUR', 'sub_category_name' => 'INSTRUMENT', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT KANTOR', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT RUMAH TANGGA', 'sub_category_name' => 'FURNITURE', 'useful_life_years' => 5],
            ['source' => 'kemenkeu', 'category_name' => 'ALAT LABORATORIUM LINGKUNGAN HIDUP', 'sub_category_name' => 'LAB', 'useful_life_years' => 7],
            ['source' => 'kemenkeu', 'category_name' => 'KOMPUTER UNIT', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
            ['source' => 'kemenkeu', 'category_name' => 'PERALATAN KOMPUTER', 'sub_category_name' => 'NETWORK', 'useful_life_years' => 4],
        ];

        foreach ($rows as $row) {
            DB::table('ga_asset_useful_life_rules')->updateOrInsert(
                [
                    'source' => $row['source'],
                    'category_name' => $row['category_name'],
                    'sub_category_name' => $row['sub_category_name'],
                ],
                [
                    'useful_life_years' => $row['useful_life_years'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    private function syncSequencesFromExistingAssets(): void
    {
        if (!Schema::hasTable('ga_asset_sequences') || !Schema::hasTable('master_sub_kategori_aset')) {
            return;
        }

        if (!Schema::hasTable('ga_assets')) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $subs = DB::table('master_sub_kategori_aset')->get(['id', 'nama_sub_kategori']);

        foreach ($subs as $sub) {
            $name = strtoupper(trim(preg_replace('/\s+/', ' ', (string) $sub->nama_sub_kategori)));
            if ($name === '') {
                $name = 'ASET';
            }

            $existingMax = (int) DB::table('ga_assets')
                ->where('cs_code', 'like', 'CS-' . $name . '-%')
                ->max(DB::raw("CAST(SUBSTRING_INDEX(cs_code, '-', -1) AS UNSIGNED)"));

            DB::table('ga_asset_sequences')->insertOrIgnore([
                'sub_kategori_aset_id' => $sub->id,
                'last_number' => $existingMax,
                'updated_at' => $now,
            ]);

            DB::table('ga_asset_sequences')
                ->where('sub_kategori_aset_id', $sub->id)
                ->where('last_number', '<', $existingMax)
                ->update([
                    'last_number' => $existingMax,
                    'updated_at' => $now,
                ]);
        }
    }
};
