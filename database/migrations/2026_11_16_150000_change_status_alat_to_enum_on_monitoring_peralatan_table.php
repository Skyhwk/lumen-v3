<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STATUS_VALUES = [
        'Tersedia',
        'Rusak',
        'Sedang Di-maintenance',
        'Sedang Dikalibrasi',
        'Sedang Digunakan',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('monitoring_peralatan') || !Schema::hasColumn('monitoring_peralatan', 'status_alat')) {
            return;
        }

        $map = [
            'tersedia' => 'Tersedia',
            'rusak' => 'Rusak',
            'sedang di maintenance' => 'Sedang Di-maintenance',
            'sedang di-maintenance' => 'Sedang Di-maintenance',
            'sedang dimaintenance' => 'Sedang Di-maintenance',
            'sedang di kalibrasi' => 'Sedang Dikalibrasi',
            'sedang dikalibrasi' => 'Sedang Dikalibrasi',
            'sedang digunakan' => 'Sedang Digunakan',
        ];

        $rows = DB::table('monitoring_peralatan')
            ->whereNotNull('status_alat')
            ->where('status_alat', '<>', '')
            ->select('id', 'status_alat')
            ->get();

        foreach ($rows as $row) {
            $raw = trim((string) $row->status_alat);
            $key = mb_strtolower($raw);
            $normalized = $map[$key] ?? null;

            if ($normalized === null) {
                DB::table('monitoring_peralatan')
                    ->where('id', $row->id)
                    ->update(['status_alat' => null]);
                continue;
            }

            if ($normalized !== $raw) {
                DB::table('monitoring_peralatan')
                    ->where('id', $row->id)
                    ->update(['status_alat' => $normalized]);
            }
        }

        DB::table('monitoring_peralatan')
            ->where('status_alat', '')
            ->update(['status_alat' => null]);

        $enumList = collect(self::STATUS_VALUES)
            ->map(fn ($value) => "'" . str_replace("'", "''", $value) . "'")
            ->implode(',');

        DB::statement("ALTER TABLE monitoring_peralatan MODIFY status_alat ENUM({$enumList}) NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('monitoring_peralatan') || !Schema::hasColumn('monitoring_peralatan', 'status_alat')) {
            return;
        }

        DB::statement('ALTER TABLE monitoring_peralatan MODIFY status_alat TEXT NULL');
    }
};
