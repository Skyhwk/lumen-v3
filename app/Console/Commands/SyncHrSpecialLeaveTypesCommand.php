<?php

namespace App\Console\Commands;

use App\Models\Hr\HrSpecialLeaveType;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncHrSpecialLeaveTypesCommand extends Command
{
    protected $signature = 'greatday:sync-special-leave-types';

    protected $description = 'Sinkron jenis cuti khusus sesuai ketentuan HR (sheet Ketentuan)';

    /**
     * duration_unit: weekday | calendar | open (sesuai surat, tanpa batas hari tetap)
     * max_uses: null = tidak dibatasi jumlah pengajuan (masih dibatasi duration per pengajuan)
     *
     * @var list<array<string, mixed>>
     */
    private const TYPES = [
        ['code' => 'wife_birth', 'name' => 'Istri sah melahirkan', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '3 hari kerja'],
        ['code' => 'wife_miscarriage', 'name' => 'Istri sah keguguran', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '3 hari kerja'],
        ['code' => 'core_family_death', 'name' => 'Keluarga inti meninggal', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => 'Suami/istri/anak/orang tua/mertua — 3 hari kerja'],
        ['code' => 'household_death', 'name' => 'Anggota keluarga satu KK meninggal', 'duration' => 1, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '1 hari kerja'],
        ['code' => 'employee_disaster', 'name' => 'Musibah karyawan', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => 'Kebakaran/kebanjiran/bencana — 3 hari kerja'],
        ['code' => 'employee_miscarriage', 'name' => 'Keguguran karyawan sebelum HPL', 'duration' => 45, 'duration_unit' => 'calendar', 'max_uses' => null, 'requires_attachment' => true, 'description' => '45 hari kalender sesuai surat dokter'],
        ['code' => 'education', 'name' => 'Keperluan pendidikan karyawan', 'duration' => 2, 'duration_unit' => 'weekday', 'max_uses' => 2, 'requires_attachment' => true, 'description' => '2 hari kerja per jenjang (maks. 2 pengajuan)'],
        ['code' => 'employee_marriage', 'name' => 'Pernikahan sah karyawan', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => 1, 'requires_attachment' => true, 'description' => 'Maksimal 1x selama masa kerja'],
        ['code' => 'employee_circumcision', 'name' => 'Khitan karyawan', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '3 hari kerja'],
        ['code' => 'child_marriage', 'name' => 'Menikahkan anak sah', 'duration' => 3, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '3 hari kerja'],
        ['code' => 'child_circumcision', 'name' => 'Mengkhitankan anak sah', 'duration' => 2, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '2 hari kerja'],
        ['code' => 'child_baptism', 'name' => 'Membaptiskan anak sah', 'duration' => 2, 'duration_unit' => 'weekday', 'max_uses' => null, 'requires_attachment' => false, 'description' => '2 hari kerja'],
        ['code' => 'government_summons', 'name' => 'Panggilan instansi pemerintahan', 'duration' => 0, 'duration_unit' => 'open', 'max_uses' => null, 'requires_attachment' => true, 'description' => 'Sesuai surat resmi'],
        ['code' => 'religious_travel', 'name' => 'Perjalanan ibadah', 'duration' => 15, 'duration_unit' => 'calendar', 'max_uses' => null, 'requires_attachment' => true, 'description' => '15 hari kalender termasuk administrasi'],
        ['code' => 'hajj', 'name' => 'Ibadah haji', 'duration' => 45, 'duration_unit' => 'calendar', 'max_uses' => 1, 'requires_attachment' => true, 'description' => '45 hari kalender — maksimal 1x selama masa kerja'],
    ];

    public function handle(): int
    {
        $now = Carbon::now();
        $created = 0;
        $updated = 0;

        foreach (self::TYPES as $row) {
            $existing = HrSpecialLeaveType::query()
                ->where('code', $row['code'])
                ->orWhere('name', $row['name'])
                ->first();

            $payload = [
                'code' => $row['code'],
                'name' => $row['name'],
                'duration' => $row['duration'],
                'duration_unit' => $row['duration_unit'],
                'max_uses' => $row['max_uses'],
                'requires_attachment' => (bool) $row['requires_attachment'],
                'description' => $row['description'],
                'updated_at' => $now,
                'updated_by' => 'sync-special-leave-types',
                'is_active' => true,
            ];

            if ($existing) {
                $existing->update($payload);
                $updated++;
                continue;
            }

            HrSpecialLeaveType::create(array_merge($payload, [
                'created_at' => $now,
                'created_by' => 'sync-special-leave-types',
            ]));
            $created++;
        }

        $this->info("Cuti khusus: created={$created} updated={$updated}");

        return 0;
    }
}
