<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\IoPeralatanTimService;
use App\Services\JadwalMobilisasiService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class InformasiOperasionalFdlController extends Controller
{
    public function index(Request $request)
    {
        $samplerLogin = mb_strtolower($this->cleanMemberName((string) $this->karyawan));
        $seeAllSchedules = $this->isItProgrammingDivisionMember();
        if ($samplerLogin === '' && !$seeAllSchedules) {
            return response()->json(['data' => [], 'tanggal' => $request->input('tanggal')]);
        }

        $tanggal = $request->input('tanggal') ?: date('Y-m-d', strtotime('+1 day'));
        $timPeralatanMap = IoPeralatanTimService::mapByTanggal($tanggal);

        // Identitas keberangkatan mengikuti groupBy pada InformasiOperasionalController (web).
        $rows = Jadwal::query()
            ->select(
                'id',
                'no_quotation',
                'nama_perusahaan',
                'periode',
                'jam_mulai',
                'jam_selesai',
                'driver',
                'durasi',
                'wilayah',
                'kendaraan',
                'sampler',
                'note'
            )
            ->selectRaw('MIN(id) OVER (PARTITION BY id_sampling, parsial, no_quotation, nama_perusahaan,
                tanggal, periode, jam_mulai, jam_selesai, kategori, durasi, status, warna, note, driver,
                id_cabang, wilayah, kendaraan) AS trip_id')
            ->where('is_active', true)
            ->whereNotNull('no_quotation')
            ->whereDate('tanggal', $tanggal)
            ->orderBy('jam_mulai')
            ->orderBy('id')
            ->toBase()
            ->get();

        $data = $this->groupTripsByTeam($rows, $samplerLogin, $seeAllSchedules)
            ->map(function (Collection $group, string $teamKey) use ($tanggal, $timPeralatanMap) {
                $batchIds = JadwalMobilisasiService::flattenBatchIds($group->pluck('batch_id')->all());
                $peralatanAktual = IoPeralatanTimService::resolvePeralatanForBatches($tanggal, $batchIds);
                if ($peralatanAktual === []) {
                    $peralatanAktual = $timPeralatanMap[$teamKey] ?? [];
                }

                $listPt = $group->map(function ($trip) use (&$peralatanAktual) {
                    $mob = JadwalMobilisasiService::findActiveByBatchIds($trip->batch_id) ?: [];
                    if ($peralatanAktual === []) {
                        $peralatanAktual = $this->decodePeralatan($mob['peralatan_aktual'] ?? null);
                    }
                    return $this->buildTripPayload($trip, $mob);
                })->values();
                $peralatanDisplay = $this->buildAktualDisplayLists($peralatanAktual);

                $singleTrip = $listPt->count() === 1 ? $listPt->first() : null;

                return [
                    'kendaraan' => $singleTrip['kendaraan'] ?? null,
                    'tim_sampler' => $this->buildTimSamplerLabel($group),
                    'jadwal_mobil' => $singleTrip ? Arr::only($singleTrip, [
                        'waktu_berangkat', 'estimasi_jam', 'estimasi_menit', 'rute_perjalanan',
                    ]) : null,
                    'peralatan_aktual' => $peralatanDisplay['items'],
                    'has_peralatan_aktual' => count($peralatanDisplay['items']) > 0,
                    'list_pt' => $listPt,
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
            'tanggal' => $tanggal,
        ]);
    }

    private function groupTripsByTeam(Collection $rows, string $samplerLogin, bool $seeAllSchedules): Collection
    {
        return $rows
            ->groupBy('trip_id')
            ->map(function (Collection $tripRows) {
                $trip = $tripRows->first();
                $trip->batch_id = $tripRows->pluck('id')->implode(',');
                $trip->team_members = $this->tripMembers($tripRows->pluck('sampler')->implode(','), $trip->driver);
                return $trip;
            })
            ->filter(fn ($trip) => $seeAllSchedules || isset($trip->team_members[$samplerLogin]))
            ->groupBy(function ($trip) {
                $teamKey = IoPeralatanTimService::normalizePetugasKey(implode(', ', $trip->team_members));
                return $teamKey ?: 'trip:' . $trip->trip_id;
            });
    }

    private function buildTripPayload(object $trip, array $mob): array
    {
        $quotation = $this->resolveQuotation($trip->no_quotation);
        $ketOps = trim(strip_tags(html_entity_decode(
            (string) ($mob['keterangan_operasional'] ?? $mob['keterangan_operasional_sp'] ?? ''),
            ENT_QUOTES,
            'UTF-8'
        )));
        $estimasiJam = (int) ($mob['estimasi_jam'] ?? 0);
        $estimasiMenit = (int) ($mob['estimasi_menit'] ?? 0);

        return [
            'jadwal_id' => (int) $trip->trip_id,
            'batch_id' => $trip->batch_id,
            'no_quotation' => $trip->no_quotation,
            'nama_perusahaan' => $trip->nama_perusahaan,
            'wilayah' => $trip->wilayah,
            'kendaraan' => $trip->kendaraan,
            'jam_mulai' => $trip->jam_mulai,
            'jam_selesai' => $trip->jam_selesai,
            'durasi' => $trip->durasi,
            'periode' => $trip->periode,
            'note' => $trip->note,
            'pic_nama' => $quotation->nama_pic_sampling ?? null,
            'pic_telp' => $quotation->no_tlp_pic_sampling ?? null,
            'alamat_sampling' => $quotation->alamat_sampling ?? null,
            'keterangan_operasional' => $ketOps,
            'status_rilis_io' => $mob['status_rilis_io'] ?? 'draft',
            'waktu_berangkat' => $this->resolveJamBerangkat($trip->jam_mulai, $mob['waktu_berangkat'] ?? null, $estimasiJam, $estimasiMenit),
            'estimasi_jam' => $estimasiJam,
            'estimasi_menit' => $estimasiMenit,
            'rute_perjalanan' => $mob['rute_perjalanan'] ?? null,
        ];
    }

    private function resolveJamBerangkat(?string $jamMulai, $waktuBerangkat, int $estimasiJam = 0, int $estimasiMenit = 0): ?string
    {
        $durasiMenit = $this->parseDurasiBerangkatMenit($waktuBerangkat, $estimasiJam, $estimasiMenit);
        if ($durasiMenit <= 0) {
            return $this->normalizeJamDisplay($waktuBerangkat);
        }

        $mulaiMenit = $this->parseJamKeMenit($jamMulai);
        if ($mulaiMenit === null) {
            return null;
        }

        return $this->menitKeJam($mulaiMenit - $durasiMenit);
    }

    private function parseDurasiBerangkatMenit($waktuBerangkat, int $estimasiJam, int $estimasiMenit): int
    {
        $text = trim((string) $waktuBerangkat);
        if ($text !== '' && !preg_match('/^\d{1,2}[.:]\d{2}/', str_replace('.', ':', $text))) {
            $total = 0;
            if (preg_match('/(\d+)\s*Jam/i', $text, $mJam)) {
                $total += (int) $mJam[1] * 60;
            }
            if (preg_match('/(\d+)\s*Menit/i', $text, $mMenit)) {
                $total += (int) $mMenit[1];
            }
            if ($total > 0) {
                return $total;
            }
            if (ctype_digit($text)) {
                return (int) $text;
            }
        }

        return max(0, $estimasiJam) * 60 + max(0, $estimasiMenit);
    }

    private function parseJamKeMenit(?string $jam): ?int
    {
        $jam = trim((string) $jam);
        if ($jam === '') {
            return null;
        }

        $jam = str_replace('.', ':', $jam);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $jam, $m)) {
            return ((int) $m[1]) * 60 + (int) $m[2];
        }

        $timestamp = strtotime($jam);
        if ($timestamp !== false) {
            return (int) date('G', $timestamp) * 60 + (int) date('i', $timestamp);
        }

        return null;
    }

    private function menitKeJam(int $totalMenit): string
    {
        $totalMenit = (($totalMenit % 1440) + 1440) % 1440;

        return sprintf('%02d:%02d', intdiv($totalMenit, 60), $totalMenit % 60);
    }

    private function normalizeJamDisplay($value): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        $text = str_replace('.', ':', $text);
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $text, $m)) {
            return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
        }

        return null;
    }

    private function cleanMemberName(string $name): string
    {
        $name = html_entity_decode($name, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s*\(driver\)\s*$/i', '', $name));
    }

    private function tripMembers(string $samplerCsv, ?string $driver): array
    {
        $members = [];
        $names = explode(',', $samplerCsv);
        $names[] = $driver ?? '';
        foreach ($names as $value) {
            $name = $this->cleanMemberName($value);
            if ($name !== '') {
                $members[mb_strtolower($name)] ??= $name;
            }
        }

        return $members;
    }

    private function buildTimSamplerLabel(Collection $group): string
    {
        $drivers = $group->pluck('driver')
            ->map(fn ($driver) => mb_strtolower($this->cleanMemberName((string) $driver)))
            ->flip();

        return collect($group->first()->team_members)
            ->map(fn ($name, $key) => $drivers->has($key) ? $name . ' (Driver)' : $name)
            ->implode(', ');
    }

    private function resolveQuotation(?string $noQt)
    {
        if (!$noQt) {
            return null;
        }

        if (strpos($noQt, 'QTC') !== false) {
            return QuotationKontrakH::query()
                ->select('nama_pic_sampling', 'no_tlp_pic_sampling', 'alamat_sampling')
                ->where('no_document', $noQt)
                ->where('is_active', true)
                ->first();
        }

        return QuotationNonKontrak::query()
            ->select('nama_pic_sampling', 'no_tlp_pic_sampling', 'alamat_sampling')
            ->where('no_document', $noQt)
            ->where('is_active', true)
            ->first();
    }

    /**
     * @return array{items: list<array{nama_alat: string, jumlah: int, kode_aset: list<string>}>}
     */
    private function buildAktualDisplayLists(array $items): array
    {
        $byNama = [];
        foreach (JadwalMobilisasiService::normalizePeralatanList($items) as $item) {
            $nama = trim((string) ($item['nama_alat'] ?? ''));
            if ($nama === '') {
                continue;
            }
            $key = mb_strtolower($nama);
            if (!isset($byNama[$key])) {
                $byNama[$key] = [
                    'nama_alat' => $nama,
                    'kode_labels' => [],
                    'kode_seen' => [],
                    'jumlah_fallback' => 0,
                ];
            }

            $qty = max(0, (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0));
            if ($qty > $byNama[$key]['jumlah_fallback']) {
                $byNama[$key]['jumlah_fallback'] = $qty;
            }

            $warna = trim((string) ($item['warna'] ?? ''));
            $list = $item['kode_aset_list'] ?? [];
            if (!is_array($list)) {
                continue;
            }
            foreach ($list as $kodeRaw) {
                $kode = trim((string) $kodeRaw);
                if ($kode === '') {
                    continue;
                }
                $kodeKey = mb_strtolower($kode);
                if (isset($byNama[$key]['kode_seen'][$kodeKey])) {
                    continue;
                }
                $byNama[$key]['kode_seen'][$kodeKey] = true;
                $byNama[$key]['kode_labels'][] = $warna !== '' ? "{$kode} ({$warna})" : $kode;
            }
        }

        $result = [];
        foreach ($byNama as $entry) {
            $labels = $entry['kode_labels'];
            $result[] = [
                'nama_alat' => $entry['nama_alat'],
                'jumlah' => count($labels) > 0 ? count($labels) : (int) $entry['jumlah_fallback'],
                'kode_aset' => $labels,
            ];
        }

        return ['items' => $result];
    }

    private function decodePeralatan($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            return is_array($decoded) ? array_values($decoded) : [];
        }

        return [];
    }
}
