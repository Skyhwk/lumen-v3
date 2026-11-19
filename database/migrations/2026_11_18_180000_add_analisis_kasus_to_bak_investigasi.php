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
            $hasKronologiAnchor = Schema::hasColumn('bak_investigasi', 'kronologi_catatan_khusus');
            Schema::table('bak_investigasi', function (Blueprint $table) use ($hasKronologiAnchor) {
                $col = $table->longText('analisis_kasus')
                    ->nullable()
                    ->comment('JSON penyebab + dampak (step D/E)');
                if ($hasKronologiAnchor) {
                    $col->after('kronologi_catatan_khusus');
                }
            });
        }

        $legacyColumns = [
            'penyebab_hmf',
            'penyebab_hmf_keterangan',
            'penyebab_sys',
            'penyebab_sys_keterangan',
            'penyebab_tec',
            'penyebab_tec_keterangan',
            'penyebab_ext',
            'penyebab_ext_keterangan',
            'penyebab_lainnya',
            'penyebab_lainnya_keterangan',
            'dampak_fin',
            'dampak_fin_aset',
            'dampak_ops',
            'dampak_ops_delay_layanan',
            'dampak_hse',
            'dampak_hse_near_miss',
            'dampak_hse_injury',
            'dampak_hse_fatality',
            'dampak_leg',
            'dampak_leg_risiko_sanksi',
            'dampak_lainnya',
            'dampak_lainnya_keterangan',
            'dampak_catatan_khusus',
        ];

        $availableLegacy = array_values(array_filter($legacyColumns, static function (string $col): bool {
            return Schema::hasColumn('bak_investigasi', $col);
        }));

        // Jika migrasi 190000 sudah jalan lebih dulu, kolom legacy sudah dihapus — lewati backfill.
        if ($availableLegacy === []) {
            return;
        }

        $selectCols = array_merge(['id'], $availableLegacy);
        $rows = DB::table('bak_investigasi')
            ->where(function ($q) {
                $q->whereNull('analisis_kasus')->orWhere('analisis_kasus', '');
            })
            ->get($selectCols);

        foreach ($rows as $row) {
            $payload = [
                'penyebab' => [
                    'hmf' => ['on' => (bool) ($row->penyebab_hmf ?? false), 'note' => $row->penyebab_hmf_keterangan ?? null],
                    'sys' => ['on' => (bool) ($row->penyebab_sys ?? false), 'note' => $row->penyebab_sys_keterangan ?? null],
                    'tec' => ['on' => (bool) ($row->penyebab_tec ?? false), 'note' => $row->penyebab_tec_keterangan ?? null],
                    'ext' => ['on' => (bool) ($row->penyebab_ext ?? false), 'note' => $row->penyebab_ext_keterangan ?? null],
                    'lainnya' => ['on' => (bool) ($row->penyebab_lainnya ?? false), 'note' => $row->penyebab_lainnya_keterangan ?? null],
                ],
                'dampak' => [
                    'fin' => ['on' => (bool) ($row->dampak_fin ?? false), 'aset' => (bool) ($row->dampak_fin_aset ?? false)],
                    'ops' => ['on' => (bool) ($row->dampak_ops ?? false), 'delay_layanan' => (bool) ($row->dampak_ops_delay_layanan ?? false)],
                    'hse' => [
                        'on' => (bool) ($row->dampak_hse ?? false),
                        'near_miss' => (bool) ($row->dampak_hse_near_miss ?? false),
                        'injury' => (bool) ($row->dampak_hse_injury ?? false),
                        'fatality' => (bool) ($row->dampak_hse_fatality ?? false),
                    ],
                    'leg' => ['on' => (bool) ($row->dampak_leg ?? false), 'risiko_sanksi' => (bool) ($row->dampak_leg_risiko_sanksi ?? false)],
                    'lainnya' => ['on' => (bool) ($row->dampak_lainnya ?? false), 'note' => $row->dampak_lainnya_keterangan ?? null],
                    'catatan_khusus' => $row->dampak_catatan_khusus ?? null,
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
