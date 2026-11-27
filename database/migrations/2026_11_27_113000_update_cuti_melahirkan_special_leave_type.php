<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now()->format('Y-m-d H:i:s');
        $description = 'Cuti melahirkan selama 3 bulan sejak tanggal mulai (khusus karyawan perempuan, wajib lampiran surat dokter/HPL). Bulan mulai cuti tetap dibayar penuh, bulan cuti penuh Rp 0, dan bulan kembali masuk kerja dihitung prorata.';

        if (Schema::hasTable('hr_special_leave_type')) {
            $existing = DB::table('hr_special_leave_type')
                ->where('code', 'maternity')
                ->orWhere(function ($q) {
                    $q->whereRaw('LOWER(name) IN (?, ?)', ['melahirkan', 'cuti melahirkan']);
                })
                ->first();

            $payload = [
                'code' => 'maternity',
                'name' => 'Cuti Melahirkan',
                'duration' => 3,
                'duration_unit' => 'month',
                'max_uses' => null,
                'requires_attachment' => 1,
                'description' => $description,
                'updated_by' => 'migration',
                'updated_at' => $now,
                'is_active' => 1,
            ];

            if ($existing) {
                DB::table('hr_special_leave_type')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('hr_special_leave_type')->insert(array_merge($payload, [
                    'created_by' => 'migration',
                    'created_at' => $now,
                ]));
            }
        }

        try {
            if (Schema::connection('intilab_apps')->hasTable('special_leave_types')) {
                $legacy = DB::connection('intilab_apps')->table('special_leave_types')
                    ->whereRaw('LOWER(name) IN (?, ?)', ['melahirkan', 'cuti melahirkan'])
                    ->first();

                if ($legacy) {
                    DB::connection('intilab_apps')->table('special_leave_types')
                        ->where('id', $legacy->id)
                        ->update([
                            'name' => 'Cuti Melahirkan',
                            'duration' => 90,
                            'description' => $description,
                            'updated_by' => 'migration',
                            'updated_at' => $now,
                            'is_active' => 1,
                        ]);
                } else {
                    DB::connection('intilab_apps')->table('special_leave_types')->insert([
                        'name' => 'Cuti Melahirkan',
                        'duration' => 90,
                        'description' => $description,
                        'created_by' => 'migration',
                        'created_at' => $now,
                        'updated_by' => 'migration',
                        'updated_at' => $now,
                        'is_active' => 1,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Abaikan jika koneksi legacy tidak tersedia di environment tertentu
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('hr_special_leave_type')) {
            DB::table('hr_special_leave_type')
                ->where('code', 'maternity')
                ->update([
                    'code' => null,
                    'name' => 'Melahirkan',
                    'duration' => 90,
                    'duration_unit' => 'weekday',
                    'requires_attachment' => 0,
                ]);
        }
    }
};
