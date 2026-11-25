<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi_surat_sanksi')) {
            Schema::create('bak_investigasi_surat_sanksi', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('bak_investigasi_id');
                $table->string('jenis', 10)->comment('st | sp');
                $table->unsignedBigInteger('surat_id');
                $table->timestamp('created_at')->nullable();

                $table->unique(['bak_investigasi_id', 'jenis', 'surat_id'], 'bak_inv_ss_inv_jenis_surat_uq');
                $table->index(['jenis', 'surat_id'], 'bak_inv_ss_jenis_surat_idx');

                if (Schema::hasTable('bak_investigasi')) {
                    $table->foreign('bak_investigasi_id', 'bak_inv_ss_inv_fk')
                        ->references('id')
                        ->on('bak_investigasi')
                        ->onDelete('cascade');
                }
            });
        }

        if (Schema::hasTable('bak_investigasi')) {
            if (Schema::hasColumn('bak_investigasi', 'sanksi_st_detail')) {
                DB::statement('ALTER TABLE bak_investigasi MODIFY sanksi_st_detail TEXT NULL');
            }
            if (Schema::hasColumn('bak_investigasi', 'sanksi_sp_detail')) {
                DB::statement('ALTER TABLE bak_investigasi MODIFY sanksi_sp_detail TEXT NULL');
            }

            $now = date('Y-m-d H:i:s');

            if (Schema::hasColumn('bak_investigasi', 'sanksi_surat_teguran_id')) {
                $stRows = DB::table('bak_investigasi')
                    ->whereNotNull('sanksi_surat_teguran_id')
                    ->where('sanksi_surat_teguran_id', '>', 0)
                    ->get(['id', 'sanksi_surat_teguran_id']);

                foreach ($stRows as $row) {
                    DB::table('bak_investigasi_surat_sanksi')->insertOrIgnore([
                        'bak_investigasi_id' => (int) $row->id,
                        'jenis' => 'st',
                        'surat_id' => (int) $row->sanksi_surat_teguran_id,
                        'created_at' => $now,
                    ]);
                }
            }

            if (Schema::hasColumn('bak_investigasi', 'sanksi_surat_peringatan_id')) {
                $spRows = DB::table('bak_investigasi')
                    ->whereNotNull('sanksi_surat_peringatan_id')
                    ->where('sanksi_surat_peringatan_id', '>', 0)
                    ->get(['id', 'sanksi_surat_peringatan_id']);

                foreach ($spRows as $row) {
                    DB::table('bak_investigasi_surat_sanksi')->insertOrIgnore([
                        'bak_investigasi_id' => (int) $row->id,
                        'jenis' => 'sp',
                        'surat_id' => (int) $row->sanksi_surat_peringatan_id,
                        'created_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bak_investigasi_surat_sanksi');
    }
};
