<?php

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

        if (!Schema::hasColumn('bak_investigasi', 'analisis_kasus')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->longText('analisis_kasus')
                    ->nullable()
                    ->after('kronologi_catatan_khusus')
                    ->comment('JSON penyebab + dampak (step D/E)');
            });
        }

        $rows = DB::table('bak_investigasi')
            ->whereNull('analisis_kasus')
            ->orWhere('analisis_kasus', '')
            ->get(['id', 'penyebab_hmf', 'penyebab_hmf_keterangan', 'penyebab_sys', 'penyebab_sys_keterangan', 'penyebab_tec', 'penyebab_tec_keterangan', 'penyebab_ext', 'penyebab_ext_keterangan', 'penyebab_lainnya', 'penyebab_lainnya_keterangan', 'dampak_fin', 'dampak_fin_aset', 'dampak_ops', 'dampak_ops_delay_layanan', 'dampak_hse', 'dampak_hse_near_miss', 'dampak_hse_injury', 'dampak_hse_fatality', 'dampak_leg', 'dampak_leg_risiko_sanksi', 'dampak_lainnya', 'dampak_lainnya_keterangan', 'dampak_catatan_khusus']);

        foreach ($rows as $row) {
            $payload = [
                'penyebab' => [
                    'hmf' => ['on' => (bool) $row->penyebab_hmf, 'note' => $row->penyebab_hmf_keterangan],
                    'sys' => ['on' => (bool) $row->penyebab_sys, 'note' => $row->penyebab_sys_keterangan],
                    'tec' => ['on' => (bool) $row->penyebab_tec, 'note' => $row->penyebab_tec_keterangan],
                    'ext' => ['on' => (bool) $row->penyebab_ext, 'note' => $row->penyebab_ext_keterangan],
                    'lainnya' => ['on' => (bool) $row->penyebab_lainnya, 'note' => $row->penyebab_lainnya_keterangan],
                ],
                'dampak' => [
                    'fin' => ['on' => (bool) $row->dampak_fin, 'aset' => (bool) $row->dampak_fin_aset],
                    'ops' => ['on' => (bool) $row->dampak_ops, 'delay_layanan' => (bool) $row->dampak_ops_delay_layanan],
                    'hse' => [
                        'on' => (bool) $row->dampak_hse,
                        'near_miss' => (bool) $row->dampak_hse_near_miss,
                        'injury' => (bool) $row->dampak_hse_injury,
                        'fatality' => (bool) $row->dampak_hse_fatality,
                    ],
                    'leg' => ['on' => (bool) $row->dampak_leg, 'risiko_sanksi' => (bool) $row->dampak_leg_risiko_sanksi],
                    'lainnya' => ['on' => (bool) $row->dampak_lainnya, 'note' => $row->dampak_lainnya_keterangan],
                    'catatan_khusus' => $row->dampak_catatan_khusus,
                ],
            ];

            DB::table('bak_investigasi')->where('id', $row->id)->update([
                'analisis_kasus' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('bak_investigasi') && Schema::hasColumn('bak_investigasi', 'analisis_kasus')) {
            Schema::table('bak_investigasi', function (Blueprint $table) {
                $table->dropColumn('analisis_kasus');
            });
        }
    }
};