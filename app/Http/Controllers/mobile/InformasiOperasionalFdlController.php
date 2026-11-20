<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Services\IoPeralatanTimService;
use App\Services\JadwalMobilisasiService;
use Illuminate\Http\Request;

class InformasiOperasionalFdlController extends Controller
{
    public function index(Request $request)
    {
        $samplerLogin = mb_strtolower(trim((string) $this->karyawan));
        if ($samplerLogin === '') {
            return response()->json(['data' => [], 'tanggal' => $request->input('tanggal')]);
        }

        $tanggal = $request->input('tanggal') ?: date('Y-m-d', strtotime('+1 day'));
        $timPeralatanMap = IoPeralatanTimService::mapByTanggal($tanggal);

        $rows = Jadwal::query()
            ->select(
                'id',
                'parsial',
                'no_quotation',
                'nama_perusahaan',
                'tanggal',
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
            ->where('is_active', true)
            ->whereNotNull('no_quotation')
            ->whereDate('tanggal', $tanggal)
            ->orderByRaw('kendaraan ASC')
            ->orderBy('jam_mulai')
            ->get()
            ->filter(function ($item) use ($samplerLogin) {
                return $this->rowBelongsToSampler($item, $samplerLogin);
            });

        if ($rows->isEmpty()) {
            return response()->json([
                'data' => [],
                'tanggal' => $tanggal,
            ]);
        }

        $data = $rows
            ->groupBy(fn ($row) => (string) ($row->kendaraan ?? '0'))
            ->map(function ($group) use ($tanggal, $timPeralatanMap) {
                $first = $group->first();
                $batchIds = $group->pluck('id')->filter()->values()->all();

                $peralatanAktual = IoPeralatanTimService::resolvePeralatanForBatches($tanggal, $batchIds);
                if ($peralatanAktual === []) {
                    $samplerCsv = $group->pluck('sampler')->filter()->unique()->implode(', ');
                    $petugasKey = IoPeralatanTimService::normalizePetugasKey($samplerCsv);
                    if ($petugasKey !== '' && isset($timPeralatanMap[$petugasKey])) {
                        $peralatanAktual = $timPeralatanMap[$petugasKey];
                    }
                }

                $mobSample = JadwalMobilisasiService::findActiveByBatchIds($batchIds) ?: [];
                if ($peralatanAktual === [] && !empty($mobSample['peralatan_aktual'])) {
                    $peralatanAktual = $this->decodePeralatan($mobSample['peralatan_aktual']);
                }

                $peralatanDisplay = $this->buildAktualDisplayLists($peralatanAktual);

                $timSampler = $this->buildTimSamplerLabel($group);

                $listPt = $group->map(function ($item) {
                    $quotation = $this->resolveQuotation($item->no_quotation);
                    $mob = JadwalMobilisasiService::findActiveByBatchIds($item->id) ?: [];
                    $ketOps = trim(strip_tags(html_entity_decode(
                        (string) ($mob['keterangan_operasional'] ?? $mob['keterangan_operasional_sp'] ?? ''),
                        ENT_QUOTES,
                        'UTF-8'
                    )));

                    return [
                        'jadwal_id' => (int) $item->id,
                        'no_quotation' => $item->no_quotation,
                        'nama_perusahaan' => $item->nama_perusahaan,
                        'wilayah' => $item->wilayah,
                        'jam_mulai' => $item->jam_mulai,
                        'jam_selesai' => $item->jam_selesai,
                        'durasi' => $item->durasi,
                        'periode' => $item->periode,
                        'note' => $item->note,
                        'pic_nama' => $quotation->nama_pic_sampling ?? null,
                        'pic_telp' => $quotation->no_tlp_pic_sampling ?? null,
                        'alamat_sampling' => $quotation->alamat_sampling ?? null,
                        'keterangan_operasional' => $ketOps,
                        'status_rilis_io' => $mob['status_rilis_io'] ?? 'draft',
                        'waktu_berangkat' => $mob['waktu_berangkat'] ?? null,
                    ];
                })->values();

                return [
                    'kendaraan' => $first->kendaraan,
                    'tim_sampler' => $timSampler,
                    'jadwal_mobil' => [
                        'waktu_berangkat' => $mobSample['waktu_berangkat'] ?? null,
                        'estimasi_jam' => (int) ($mobSample['estimasi_jam'] ?? 0),
                        'estimasi_menit' => (int) ($mobSample['estimasi_menit'] ?? 0),
                        'rute_perjalanan' => $mobSample['rute_perjalanan'] ?? null,
                    ],
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

    private function rowBelongsToSampler($item, string $samplerLogin): bool
    {
        $samplers = collect(explode(',', (string) ($item->sampler ?? '')))
            ->map(fn ($name) => mb_strtolower(trim($name)));

        if ($samplers->contains($samplerLogin)) {
            return true;
        }

        return mb_strtolower(trim((string) ($item->driver ?? ''))) === $samplerLogin;
    }

    private function buildTimSamplerLabel($group): string
    {
        return $group
            ->flatMap(function ($item) {
                $names = collect(explode(',', (string) ($item->sampler ?? '')))
                    ->map(fn ($n) => trim($n))
                    ->filter();

                if ($item->driver) {
                    $names->push(trim((string) $item->driver) . ' (Driver)');
                }

                return $names;
            })
            ->unique()
            ->values()
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