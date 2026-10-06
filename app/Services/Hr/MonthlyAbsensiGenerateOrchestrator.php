<?php

namespace App\Services\Hr;

use App\Models\Absensi;
use App\Models\MasterKaryawan;
use App\Models\RekapLiburKalender;
use App\Models\RekapMasukKerja;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonthlyAbsensiGenerateOrchestrator
{
    public const GRADES_AUTO_GENERATE = ['STAFF', 'SUPERVISOR'];

    /** @var MonthlyAbsensiDataBuilder */
    private $builder;

    public function __construct(MonthlyAbsensiDataBuilder $builder = null)
    {
        $this->builder = $builder ?? new MonthlyAbsensiDataBuilder();
    }

    public function resolveTargetBulanYm(?string $asOfYmd = null): string
    {
        $asOf = $asOfYmd !== null && $asOfYmd !== ''
            ? Carbon::parse($asOfYmd, 'Asia/Jakarta')
            : Carbon::now('Asia/Jakarta');

        return $asOf->startOfMonth()->subMonth()->format('Y-m');
    }

    public function hasActiveRekap(int $karyawanId, string $bulanYm): bool
    {
        $parts = explode('-', $bulanYm);

        return RekapMasukKerja::where('karyawan_id', $karyawanId)
            ->where('tahun', $parts[0] ?? '')
            ->where('bulan', $bulanYm)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{absensi: array<int, array<string, mixed>>, data: array<int, array<string, mixed>>}
     */
    public function buildPayloadFromRows(array $rows): array
    {
        $absensi = [];
        $data = [];

        foreach ($rows as $row) {
            $masuk = trim((string) ($row['masuk'] ?? ''));
            $keluar = trim((string) ($row['keluar'] ?? ''));

            $data[] = [
                'tanggal' => $row['tanggal'] ?? '',
                'shift' => $row['shift'] ?? '',
                'masuk' => $masuk,
                'keluar' => $keluar,
            ];

            if ($masuk === '' && $keluar === '') {
                continue;
            }

            $absensi[] = [
                'id' => $row['karyawan_id'] ?? null,
                'tgl' => $row['tanggal'] ?? '',
                'hari' => $row['hari'] ?? '',
                'masuk' => $masuk,
                'keluar' => $keluar,
                'tgl_masuk' => $row['tgl_masuk'] ?? '',
                'tgl_keluar' => $row['tgl_keluar'] ?? '',
                'id_masuk' => $row['id_masuk'] ?? '',
                'id_keluar' => $row['id_keluar'] ?? '',
                'shift' => $row['shift'] ?? '',
            ];
        }

        return ['absensi' => $absensi, 'data' => $data];
    }

    /**
     * @param array<int, mixed>|null $absensiItems
     * @param array<int, mixed> $rekapRows
     */
    public function persistGenerate(
        int $karyawanId,
        string $bulanYm,
        ?array $absensiItems,
        array $rekapRows,
        ?int $actorKaryawanId
    ): void {
        if ($absensiItems !== null && $absensiItems !== []) {
            $this->applyAbsensiGenerateItems($absensiItems, $karyawanId, $bulanYm);
        }

        $bulan = explode('-', $bulanYm);
        $tahun = $bulan[0] ?? '';

        $existing = RekapMasukKerja::where('karyawan_id', $karyawanId)
            ->where('tahun', $tahun)
            ->where('bulan', $bulanYm)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            RekapMasukKerja::where('karyawan_id', $karyawanId)
                ->where('tahun', $tahun)
                ->where('bulan', $bulanYm)
                ->where('is_active', true)
                ->update([
                    'rejected_by' => $actorKaryawanId,
                    'rejected_at' => date('Y-m-d H:i:s'),
                    'is_active' => false,
                ]);
        }

        $hariKerja = RekapLiburKalender::where('tahun', $tahun)
            ->where('is_active', true)
            ->first();

        if (!$hariKerja) {
            throw new \RuntimeException('Kalender hari kerja belum di set untuk tahun ' . $tahun . '.');
        }

        $masukKerja = [];
        foreach ($rekapRows as $data) {
            $row = is_array($data) ? $data : (array) $data;
            $masuk = $row['masuk'] ?? '';
            $keluar = $row['keluar'] ?? '';
            $shift = $row['shift'] ?? '';
            $tanggal = $row['tanggal'] ?? '';
            if ($keluar !== '' && $masuk !== '') {
                if (in_array($shift, ['SHOB', 'SHOB2', 'SHSECURITY', 'SHSECURITY2', '24jam'], true)) {
                    $masukKerja[] = $tanggal;
                } elseif ($shift != 'off') {
                    $masukKerja[] = $tanggal;
                }
            }
        }

        RekapMasukKerja::insert([
            'karyawan_id' => $karyawanId,
            'tahun' => $tahun,
            'bulan' => $bulanYm,
            'tanggal' => json_encode($masukKerja),
            'added_by' => $actorKaryawanId,
            'added_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array{processed: int, generated: int, skipped: int, failed: int, details: list<array<string, mixed>>}
     */
    public function generateForStaffSupervisor(string $bulanYm, bool $skipExisting = true): array
    {
        $employees = MasterKaryawan::query()
            ->select('id', 'nik_karyawan', 'nama_lengkap', 'grade')
            ->where('is_active', true)
            ->whereIn('grade', self::GRADES_AUTO_GENERATE)
            ->orderBy('id')
            ->get();

        $summary = [
            'processed' => 0,
            'generated' => 0,
            'skipped' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($employees as $employee) {
            $summary['processed']++;
            $karyawanId = (int) $employee->id;

            if ($skipExisting && $this->hasActiveRekap($karyawanId, $bulanYm)) {
                $summary['skipped']++;
                $summary['details'][] = [
                    'karyawan_id' => $karyawanId,
                    'nik' => $employee->nik_karyawan,
                    'nama' => $employee->nama_lengkap,
                    'status' => 'skipped',
                    'message' => 'Rekap aktif untuk periode ini sudah ada.',
                ];

                continue;
            }

            try {
                $rows = $this->builder->buildForKaryawan($karyawanId, $bulanYm);
                if ($rows === []) {
                    $summary['failed']++;
                    $summary['details'][] = [
                        'karyawan_id' => $karyawanId,
                        'nik' => $employee->nik_karyawan,
                        'nama' => $employee->nama_lengkap,
                        'status' => 'failed',
                        'message' => 'Data karyawan tidak ditemukan atau format bulan tidak valid.',
                    ];

                    continue;
                }

                $payload = $this->buildPayloadFromRows($rows);
                $generated = DB::transaction(function () use ($karyawanId, $bulanYm, $payload, $skipExisting) {
                    // Lock a row that exists even before the first monthly rekap.
                    // Concurrent automatic commands wait here, then recheck.
                    $employee = MasterKaryawan::where('id', $karyawanId)->lockForUpdate()->first();
                    if (!$employee) {
                        throw new \RuntimeException('Karyawan tidak ditemukan.');
                    }
                    if ($skipExisting && $this->hasActiveRekap($karyawanId, $bulanYm)) {
                        return false;
                    }
                    $this->persistGenerate(
                        $karyawanId,
                        $bulanYm,
                        // Rekap otomatis hanya membaca punch asli; koreksi punch
                        // tetap melalui jalur generate manual.
                        null,
                        $payload['data'],
                        null
                    );
                    return true;
                });

                if (!$generated) {
                    $summary['skipped']++;
                    $summary['details'][] = [
                        'karyawan_id' => $karyawanId,
                        'nik' => $employee->nik_karyawan,
                        'nama' => $employee->nama_lengkap,
                        'status' => 'skipped',
                        'message' => 'Rekap aktif untuk periode ini sudah ada.',
                    ];
                    continue;
                }

                $summary['generated']++;
                $summary['details'][] = [
                    'karyawan_id' => $karyawanId,
                    'nik' => $employee->nik_karyawan,
                    'nama' => $employee->nama_lengkap,
                    'status' => 'generated',
                    'message' => 'OK',
                ];
            } catch (\Throwable $e) {
                $summary['failed']++;
                $summary['details'][] = [
                    'karyawan_id' => $karyawanId,
                    'nik' => $employee->nik_karyawan,
                    'nama' => $employee->nama_lengkap,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];

                Log::error('absensi:generate-monthly gagal per karyawan', [
                    'karyawan_id' => $karyawanId,
                    'bulan' => $bulanYm,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $summary;
    }

    public function normalizeJamForDb(?string $jam): string
    {
        if ($jam === null) {
            return '';
        }

        $jam = trim((string) $jam);
        if ($jam === '') {
            return '';
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $jam)) {
            return $this->formatJamHmForDb($jam) . ':00';
        }

        if (preg_match('/^(\d{1,2}:\d{2}):\d{2}$/', $jam, $m)) {
            return $this->formatJamHmForDb($m[1]) . ':00';
        }

        return $jam;
    }

    /**
     * @param array<int, mixed> $absensiItems
     */
    private function applyAbsensiGenerateItems(array $absensiItems, int $karyawanId, string $bulanYm): void
    {
        $items = [];
        foreach ($absensiItems as $value) {
            $items[] = $this->castAbsensiItem($value);
        }

        if ($items === []) {
            return;
        }

        $start = $bulanYm . '-01';
        $end = date('Y-m-t', strtotime($start));
        $existing = Absensi::where('karyawan_id', $karyawanId)
            ->whereBetween('tanggal', [$start, date('Y-m-d', strtotime($end . ' +1 day'))])
            ->get(['id', 'tanggal', 'status']);

        $insertRows = [];
        $updates = [];
        $keepBySlot = [];

        foreach ($items as $dataAbsen) {
            $kid = (int) ($dataAbsen->id ?? $karyawanId);
            $masukJam = $this->normalizeJamForDb($dataAbsen->masuk ?? '');
            $keluarJam = $this->normalizeJamForDb($dataAbsen->keluar ?? '');

            if ($masukJam !== '') {
                if (!empty($dataAbsen->id_masuk)) {
                    $idMasuk = (int) $dataAbsen->id_masuk;
                    $updates[$idMasuk] = $masukJam;
                    $keepBySlot['Masuk|' . $dataAbsen->tgl_masuk] = $idMasuk;
                } else {
                    $insertRows[] = [
                        'karyawan_id' => $kid,
                        'tanggal' => $dataAbsen->tgl,
                        'hari' => MonthlyAbsensiDataBuilder::hariIndonesia($dataAbsen->tgl),
                        'jam' => $masukJam,
                        'status' => 'Masuk',
                    ];
                }
            }

            if ($keluarJam !== '') {
                if (!empty($dataAbsen->id_keluar)) {
                    $idKeluar = (int) $dataAbsen->id_keluar;
                    $updates[$idKeluar] = $keluarJam;
                    $keepBySlot['Keluar|' . $dataAbsen->tgl_keluar] = $idKeluar;
                } else {
                    $tanggal = $dataAbsen->tgl;
                    if ($dataAbsen->shift == 'SHSECURITY2' || $dataAbsen->shift == '24jam') {
                        $tanggal = date('Y-m-d', strtotime($dataAbsen->tgl . ' +1 day'));
                    }
                    $insertRows[] = [
                        'karyawan_id' => $kid,
                        'tanggal' => $tanggal,
                        'hari' => MonthlyAbsensiDataBuilder::hariIndonesia($tanggal),
                        'jam' => $keluarJam,
                        'status' => 'Keluar',
                    ];
                }
            }
        }

        $deleteIds = [];
        foreach ($existing as $row) {
            $key = $row->status . '|' . $row->tanggal;
            if (isset($keepBySlot[$key]) && (int) $row->id !== $keepBySlot[$key]) {
                $deleteIds[] = $row->id;
            }
        }

        if ($deleteIds !== []) {
            Absensi::whereIn('id', array_values(array_unique($deleteIds)))->delete();
        }

        foreach ($updates as $id => $jam) {
            Absensi::where('id', $id)->update([
                'kode_kartu' => null,
                'jam' => $jam,
            ]);
        }

        foreach (array_chunk($insertRows, 100) as $chunk) {
            if ($chunk !== []) {
                Absensi::insert($chunk);
            }
        }
    }

    private function castAbsensiItem($value): object
    {
        if (is_object($value)) {
            return $value;
        }

        if (is_array($value)) {
            return (object) $value;
        }

        return (object) json_decode(json_encode($value), true);
    }

    private function formatJamHmForDb(string $jam): string
    {
        if (preg_match('/^(\d{1,2}):(\d{2})/', trim($jam), $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return $jam;
    }
}
