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

        $addedProses = false;

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) use (&$addedProses) {
            if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'proses_status')) {
                $table->string('proses_status', 32)->default('permintaan_dokumen')->after('status');
                $table->index('proses_status', 'bak_proses_status_idx');
                $addedProses = true;
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

        if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'proses_status')) {
            return;
        }

        DB::table('berita_acara_kejadian_disipliner')
            ->where('status', 'issued')
            ->where(function ($query) {
                $query->where('proses_status', 'permintaan_dokumen')
                    ->orWhereNull('proses_status')
                    ->orWhere('proses_status', '');
            })
            ->update(['proses_status' => 'sudah_diproses']);
    }

    public function down(): void
    {
        // no-op
    }
};