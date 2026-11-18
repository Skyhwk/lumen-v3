<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'proses_status')) {
                $table->string('proses_status', 32)->default('permintaan_dokumen')->after('status');
                $table->index('proses_status', 'bak_proses_status_idx');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'approval_step')) {
                $table->unsignedTinyInteger('approval_step')->default(0)->after('proses_status');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'approval_chain')) {
                $table->json('approval_chain')->nullable()->after('approval_step');
            }
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'approval_log')) {
                $table->json('approval_log')->nullable()->after('approval_chain');
            }
        });

        DB::table('berita_acara_kejadian_disipliner')->orderBy('id')->each(function ($row) {
            $proses = 'permintaan_dokumen';
            if ($row->status === 'issued') {
                $proses = 'sudah_diproses';
            } elseif ($row->status === 'cancelled') {
                $proses = 'permintaan_dokumen';
            }

            DB::table('berita_acara_kejadian_disipliner')->where('id', $row->id)->update([
                'proses_status' => $proses,
            ]);
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            foreach (['approval_log', 'approval_chain', 'approval_step', 'proses_status'] as $column) {
                if (Schema::hasColumn('berita_acara_kejadian_disipliner', $column)) {
                    if ($column === 'proses_status') {
                        $table->dropIndex('bak_proses_status_idx');
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};