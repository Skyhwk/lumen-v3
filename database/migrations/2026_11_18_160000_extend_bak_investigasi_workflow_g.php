<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        Schema::table('bak_investigasi', function (Blueprint $table) {
            if (!Schema::hasColumn('bak_investigasi', 'rekomendasi_alur')) {
                $table->enum('rekomendasi_alur', ['internal_disciplinary', 'komite_manajemen'])->nullable()->after('dampak_catatan_khusus');
            }
            if (!Schema::hasColumn('bak_investigasi', 'keputusan_sanksi')) {
                $table->enum('keputusan_sanksi', ['tanpa_sanksi', 'dengan_sanksi'])->nullable()->after('rekomendasi_alur');
            }
            if (!Schema::hasColumn('bak_investigasi', 'submitted_at')) {
                $table->timestamp('submitted_at')->nullable()->after('updated_by');
            }
            if (!Schema::hasColumn('bak_investigasi', 'submitted_by')) {
                $table->string('submitted_by', 150)->nullable()->after('submitted_at');
            }
            if (!Schema::hasColumn('bak_investigasi', 'submitted_by_karyawan_id')) {
                $table->unsignedInteger('submitted_by_karyawan_id')->nullable()->after('submitted_by');
            }
            if (!Schema::hasColumn('bak_investigasi', 'legal_approved_at')) {
                $table->timestamp('legal_approved_at')->nullable()->after('submitted_by_karyawan_id');
            }
            if (!Schema::hasColumn('bak_investigasi', 'legal_approved_by')) {
                $table->string('legal_approved_by', 150)->nullable()->after('legal_approved_at');
            }
            if (!Schema::hasColumn('bak_investigasi', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('legal_approved_by');
            }
            if (!Schema::hasColumn('bak_investigasi', 'published_by')) {
                $table->string('published_by', 150)->nullable()->after('published_at');
            }
            if (!Schema::hasColumn('bak_investigasi', 'approval_chain')) {
                $table->longText('approval_chain')->nullable()->after('published_by');
            }
            if (!Schema::hasColumn('bak_investigasi', 'rejection_reason')) {
                $table->string('rejection_reason', 500)->nullable()->after('approval_chain');
            }
            if (!Schema::hasColumn('bak_investigasi', 'creator_karyawan_id')) {
                $table->unsignedInteger('creator_karyawan_id')->nullable()->after('created_by');
            }
        });

        if (Schema::hasTable('bak_investigasi_pengesahan')) {
            Schema::table('bak_investigasi_pengesahan', function (Blueprint $table) {
                if (!Schema::hasColumn('bak_investigasi_pengesahan', 'state')) {
                    $table->string('state', 20)->default('pending')->after('keterangan');
                }
                if (!Schema::hasColumn('bak_investigasi_pengesahan', 'berita_acara_id')) {
                    $table->unsignedBigInteger('berita_acara_id')->nullable()->after('state');
                }
                if (!Schema::hasColumn('bak_investigasi_pengesahan', 'acted_at')) {
                    $table->timestamp('acted_at')->nullable()->after('karyawan_id');
                }
                if (!Schema::hasColumn('bak_investigasi_pengesahan', 'actor_name')) {
                    $table->string('actor_name', 150)->nullable()->after('acted_at');
                }
            });
        }

        if (Schema::hasTable('menu_data_access')) {
            $key = 'investigasi-awal';
            $exists = DB::table('menu_data_access')
                ->where('menu_key', $key)
                ->where('is_active', true)
                ->exists();
            if (!$exists) {
                DB::table('menu_data_access')->insert([
                    'menu_key' => $key,
                    'grades' => json_encode(['PENANDATANGAN', 'LEGAL & DISCIPLINARY MANAGER'], JSON_UNESCAPED_UNICODE),
                    'is_active' => true,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return;
        }

        Schema::table('bak_investigasi', function (Blueprint $table) {
            foreach (
                [
                    'rekomendasi_alur',
                    'keputusan_sanksi',
                    'submitted_at',
                    'submitted_by',
                    'submitted_by_karyawan_id',
                    'legal_approved_at',
                    'legal_approved_by',
                    'published_at',
                    'published_by',
                    'approval_chain',
                    'rejection_reason',
                    'creator_karyawan_id',
                ] as $col
            ) {
                if (Schema::hasColumn('bak_investigasi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        if (Schema::hasTable('bak_investigasi_pengesahan')) {
            Schema::table('bak_investigasi_pengesahan', function (Blueprint $table) {
                foreach (['state', 'berita_acara_id', 'acted_at', 'actor_name'] as $col) {
                    if (Schema::hasColumn('bak_investigasi_pengesahan', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};