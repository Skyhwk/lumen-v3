<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private const LEGACY_ANALISIS_COLUMNS = [
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
                    ->comment('JSON v1 penyebab + dampak');
            });
        }

        $legacySelect = array_merge(['id'], self::LEGACY_ANALISIS_COLUMNS);
        $legacySelect[] = 'analisis_kasus';
        $legacySelect = array_values(array_unique(array_filter($legacySelect, static function ($col) {
            return Schema::hasColumn('bak_investigasi', $col);
        })));

        $rows = DB::table('bak_investigasi')->select($legacySelect)->get();
        foreach ($rows as $row) {
            if (!empty($row->analisis_kasus)) {
                continue;
            }
            $payload = [
                'v' => 1,
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

        $rows = DB::table('bak_investigasi')->select(['id', 'analisis_kasus'])->get();
        foreach ($rows as $row) {
            $raw = $row->analisis_kasus;
            if ($raw === null || $raw === '') {
                continue;
            }
            $decoded = json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                continue;
            }
            if (!isset($decoded['v'])) {
                $decoded['v'] = 1;
                DB::table('bak_investigasi')->where('id', $row->id)->update([
                    'analisis_kasus' => json_encode($decoded, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }

        Schema::table('bak_investigasi', function (Blueprint $table) {
            foreach (self::LEGACY_ANALISIS_COLUMNS as $column) {
                if (Schema::hasColumn('bak_investigasi', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        // Kolom legacy tidak di-restore otomatis — data ada di analisis_kasus JSON.
    }
};