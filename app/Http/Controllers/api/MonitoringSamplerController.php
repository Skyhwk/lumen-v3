<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BasSampelSelesai;
use App\Models\OrderDetail;
use App\Models\PersiapanSampelHeader;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class MonitoringSamplerController extends Controller
{
    public function index(Request $request)
    {
        try {
            [$periodeAwal, $periodeAkhir] = $this->resolvePeriodeRange($request);

            $existingWork = DB::table('persiapan_sampel_header')
                ->select(
                    'id',
                    'no_document',
                    'no_order',
                    'no_quotation',
                    'tanggal_sampling',
                    'sampler_jadwal',
                    'no_sampel',
                    'is_downloaded_stps',
                    'is_printed_stps'
                )
                ->where('is_active', true)
                ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
                ->get();

            $headerSamples = [];
            $allSampleNos = [];
            foreach ($existingWork as $row) {
                $samples = $this->decodeNoSampelList($row->no_sampel);
                $headerSamples[(int) $row->id] = $samples;
                foreach ($samples as $noSampel) {
                    $allSampleNos[$noSampel] = true;
                }
            }

            $selesaiLookup = $this->buildSelesaiLookup($existingWork, $periodeAwal, $periodeAkhir);
            $orderDetailsBySampel = $this->loadOrderDetailsBySampel(array_keys($allSampleNos));

            $doneList = [];
            $headerByOrderDate = [];

            foreach ($existingWork as $row) {
                $samples = $headerSamples[(int) $row->id] ?? [];
                $headerMeta = [
                    'id_persiapan_sampel_header' => (int) $row->id,
                    'no_stps' => $this->noStpsFromHeader($row),
                    'jumlah_titik' => count($samples),
                    'titik_diambil' => $this->countTitikDiambil(
                        $samples,
                        $row->no_order,
                        $row->tanggal_sampling,
                        $selesaiLookup,
                        $orderDetailsBySampel
                    ),
                    'is_proccess' => true,
                    'is_downloaded_stps' => $row->is_downloaded_stps,
                    'is_printed_stps' => $row->is_printed_stps,
                ];

                $orderDateKey = sprintf('%s|%s', trim($row->no_order), trim($row->tanggal_sampling));
                if (!isset($headerByOrderDate[$orderDateKey])) {
                    $headerByOrderDate[$orderDateKey] = $headerMeta;
                }

                $headerSamplers = explode(',', $row->sampler_jadwal ?? '');
                foreach ($headerSamplers as $name) {
                    $cleanName = strtolower(trim($name));
                    if ($cleanName === '') {
                        continue;
                    }

                    $key = sprintf('%s|%s|%s', trim($row->no_order), trim($row->tanggal_sampling), $cleanName);
                    $doneList[$key] = $headerMeta;
                }
            }

            $myPrivileges = is_array($this->privilageCabang) ? $this->privilageCabang : [];
            $isOrangPusat = in_array('1', $myPrivileges) || in_array(1, $myPrivileges);
            $query = OrderDetail::query();
            if (!$isOrangPusat) {
                $query->whereHas('orderHeader.samplingPlan.jadwal', function ($q) use ($myPrivileges) {
                    $q->where('is_active', true);
                    $q->whereIn('id_cabang', $myPrivileges);
                });
            }

            $data = $query->with([
                'orderHeader' => function ($q) {
                    $q->select([
                        'id', 'tanggal_order', 'nama_perusahaan', 'konsultan', 'no_document',
                        'alamat_sampling', 'nama_pic_order', 'nama_pic_sampling',
                        'no_tlp_pic_sampling', 'jabatan_pic_sampling', 'jabatan_pic_order', 'is_revisi',
                    ]);
                },
                'orderHeader.samplingPlan' => function ($q) {
                    $q->select(['id', 'periode_kontrak', 'quotation_id', 'status_quotation', 'is_active'])
                        ->where('is_active', true);
                },
                'orderHeader.samplingPlan.jadwal' => function ($q) use ($isOrangPusat, $myPrivileges) {
                    $q->select([
                        'id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang',
                        DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler'),
                    ])
                    ->where('is_active', true)
                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                    if (!$isOrangPusat) {
                        $q->whereIn('id_cabang', $myPrivileges);
                    }
                },
            ])
            ->select(['id_order_header', 'no_order', 'kategori_1', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
            ->where('is_active', true)
            ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
            ->get();

            $groupedData = [];

            foreach ($data as $item) {
                if (!$item->orderHeader || $item->orderHeader->sampling->isEmpty()) {
                    continue;
                }

                $orderHeader = $item->orderHeader;
                $periode = $item->periode ?? '';
                $targetPlan = null;
                if ($periode) {
                    $targetPlan = $orderHeader->sampling->firstWhere('periode_kontrak', $periode);
                }
                if (!$targetPlan) {
                    $targetPlan = $orderHeader->sampling->first();
                }
                if (!$targetPlan || $targetPlan->jadwal->isEmpty()) {
                    continue;
                }

                foreach ($targetPlan->jadwal as $schedule) {
                    if (!$isOrangPusat && !in_array($schedule->id_cabang, $this->privilageCabang)) {
                        continue;
                    }
                    if ($schedule->tanggal !== $item->tanggal_sampling) {
                        continue;
                    }

                    $currentSamplers = explode(',', $schedule->sampler ?? '');
                    $pendingSamplers = [];
                    $headerMeta = null;

                    foreach ($currentSamplers as $singleSampler) {
                        $cleanTargetName = strtolower(trim($singleSampler));
                        if ($cleanTargetName === '') {
                            continue;
                        }

                        $checkKey = sprintf('%s|%s|%s', trim($item->no_order), trim($schedule->tanggal), $cleanTargetName);
                        if (!isset($doneList[$checkKey])) {
                            continue;
                        }

                        $pendingSamplers[] = trim($singleSampler);
                        $headerMeta = $doneList[$checkKey];
                    }

                    if ($pendingSamplers === []) {
                        continue;
                    }

                    if ($headerMeta === null) {
                        $orderDateKey = sprintf('%s|%s', trim($item->no_order), trim($schedule->tanggal));
                        $headerMeta = $headerByOrderDate[$orderDateKey] ?? null;
                    }
                    if ($headerMeta === null) {
                        continue;
                    }

                    $schedule->sampler = implode(',', $pendingSamplers);
                    $kategori = implode(',', json_decode($schedule->kategori, true) ?? []);
                    $durasiLabel = $this->mapDurasi($schedule->durasi);

                    $key = $orderHeader->no_document . '|' .
                        $item->no_order . '|' .
                        $schedule->tanggal . '|' .
                        $schedule->jam_mulai . '|' .
                        $kategori;

                    if (isset($groupedData[$key])) {
                        $existingSamplers = explode(',', $groupedData[$key]['sampler']);
                        $newSamplers = explode(',', $schedule->sampler ?? '');
                        $merged = array_unique(array_merge($existingSamplers, $newSamplers));
                        $groupedData[$key]['sampler'] = implode(',', array_filter($merged));
                        $groupedData[$key]['durasi'] = $this->mergeDurasiLabels(
                            $groupedData[$key]['durasi'] ?? '-',
                            $durasiLabel
                        );
                    } else {
                        $groupedData[$key] = [
                            'id_persiapan_sampel_header' => $headerMeta['id_persiapan_sampel_header'],
                            'no_stps' => $headerMeta['no_stps'],
                            'nomor_quotation' => $orderHeader->no_document ?? '',
                            'nama_perusahaan' => $orderHeader->nama_perusahaan ?? '',
                            'jadwal' => $schedule->tanggal,
                            'sampler' => $schedule->sampler ?? '',
                            'jumlah_titik' => $headerMeta['jumlah_titik'],
                            'titik_diambil' => $headerMeta['titik_diambil'],
                            'durasi' => $durasiLabel,
                            'no_order' => $item->no_order ?? '',
                            'periode' => $periode,
                            'kategori' => $kategori,
                            'is_revisi' => $orderHeader->is_revisi,
                        ];
                    }
                }
            }

            return DataTables::of(collect(array_values($groupedData)))->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $headerId = (int) $request->id_persiapan_sampel_header;
            if ($headerId <= 0) {
                return response()->json(['message' => 'ID persiapan sampel tidak valid.'], 422);
            }

            $header = PersiapanSampelHeader::where('id', $headerId)
                ->where('is_active', 1)
                ->first();

            if (!$header) {
                return response()->json(['message' => 'Dokumen STPS tidak ditemukan.'], 404);
            }

            $noSampelList = collect(json_decode($header->no_sampel, true) ?: [])
                ->map(function ($noSampel) {
                    return trim((string) $noSampel);
                })
                ->filter()
                ->unique()
                ->sort(function ($a, $b) {
                    return strnatcmp($a, $b);
                })
                ->values();

            $orderDetails = OrderDetail::where('is_active', true)
                ->whereIn('no_sampel', $noSampelList->all())
                ->get(['no_sampel', 'kategori_2', 'kategori_3', 'parameter'])
                ->keyBy(function ($row) {
                    return trim((string) $row->no_sampel);
                });

            $selesaiRows = BasSampelSelesai::where('no_order', $header->no_order)
                ->where('tanggal_sampling', $header->tanggal_sampling)
                ->orderByDesc('id')
                ->get(['no_sampel', 'sub_kategori', 'kategori', 'created_at']);

            $selesaiLookup = [];
            $selesaiSubKategori = collect();
            foreach ($selesaiRows as $row) {
                $key = trim((string) $row->no_sampel);
                $selesaiLookup[$key] = true;
                if (!$selesaiSubKategori->has($key)) {
                    $selesaiSubKategori->put($key, $row);
                }
            }

            $samples = [];
            foreach ($noSampelList as $noSampel) {
                $orderDetail = $orderDetails->get($noSampel);
                $basRow = $selesaiSubKategori->get($noSampel);
                $isPsikologi = $this->isPsikologiSample($orderDetail, $basRow);
                $isK3 = $this->isK3Sample($orderDetail);
                $subKategori = $isPsikologi ? 'Psikologi' : $this->resolveSubKategori($orderDetail, $basRow);
                $isSelesai = isset($selesaiLookup[$noSampel]) || $isPsikologi || $isK3;

                $samples[] = [
                    'no_sampel' => $noSampel,
                    'sub_kategori' => $subKategori,
                    'status' => $isSelesai ? 'Selesai' : 'Belum Selesai',
                    'is_selesai' => $isSelesai,
                    'tanggal_selesai' => $this->formatTanggalSelesai($basRow->created_at ?? null),
                ];
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'id_persiapan_sampel_header' => (int) $header->id,
                    'no_stps' => $this->noStpsFromHeader($header),
                    'nomor_quotation' => $header->no_quotation,
                    'nama_perusahaan' => $header->nama_perusahaan,
                    'jadwal' => $header->tanggal_sampling,
                    'sampler' => $header->sampler_jadwal,
                    'jumlah_titik' => count($samples),
                    'titik_diambil' => collect($samples)->where('is_selesai', true)->count(),
                    'samples' => $samples,
                ],
            ], 200);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }

    private function noStpsFromHeader($header): string
    {
        $noDocument = (string) ($header->no_document ?? '');
        if ($noDocument === '') {
            return 'ISL/STPS/' . (int) ($header->id ?? 0);
        }

        return str_replace('/PS/', '/STPS/', $noDocument);
    }

    private function countTitik($noSampelJson): int
    {
        return count($this->decodeNoSampelList($noSampelJson));
    }

    private function decodeNoSampelList($noSampelJson): array
    {
        $decoded = is_string($noSampelJson) ? json_decode($noSampelJson, true) : $noSampelJson;
        if (!is_array($decoded)) {
            return [];
        }

        return collect($decoded)
            ->map(function ($noSampel) {
                return trim((string) $noSampel);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function mapDurasi($durasi): string
    {
        $map = [
            0 => 'Sesaat',
            1 => '8 Jam',
            2 => '1x24 Jam',
            3 => '2x24 Jam',
            4 => '3x24 Jam',
            5 => '4x24 Jam',
            6 => '5x24 Jam',
            7 => '6x24 Jam',
            8 => '7x24 Jam',
            9 => '8x24 Jam',
        ];

        $key = is_numeric($durasi) ? (int) $durasi : null;

        return $key !== null && isset($map[$key]) ? $map[$key] : '-';
    }

    private function mergeDurasiLabels(string $existing, string $incoming): string
    {
        $parts = collect(explode(',', $existing))
            ->merge(explode(',', $incoming))
            ->map(function ($value) {
                return trim($value);
            })
            ->filter(function ($value) {
                return $value !== '' && $value !== '-';
            })
            ->unique()
            ->values();

        return $parts->isEmpty() ? '-' : $parts->implode(', ');
    }

    private function buildSelesaiLookup($headers, $periodeAwal, $periodeAkhir): array
    {
        $orders = collect($headers)
            ->pluck('no_order')
            ->map(function ($noOrder) {
                return trim((string) $noOrder);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($orders === []) {
            return [];
        }

        $rows = BasSampelSelesai::query()
            ->whereIn('no_order', $orders)
            ->whereBetween('tanggal_sampling', [$periodeAwal, $periodeAkhir])
            ->get(['no_order', 'tanggal_sampling', 'no_sampel']);

        $lookup = [];
        foreach ($rows as $row) {
            $key = sprintf(
                '%s|%s|%s',
                trim((string) $row->no_order),
                trim((string) $row->tanggal_sampling),
                trim((string) $row->no_sampel)
            );
            $lookup[$key] = true;
        }

        return $lookup;
    }

    private function loadOrderDetailsBySampel(array $sampleNos)
    {
        if ($sampleNos === []) {
            return collect();
        }

        return OrderDetail::where('is_active', true)
            ->whereIn('no_sampel', $sampleNos)
            ->get(['no_sampel', 'kategori_3', 'parameter'])
            ->keyBy(function ($row) {
                return trim((string) $row->no_sampel);
            });
    }

    private function countTitikDiambil(array $samples, $noOrder, $tanggalSampling, array $selesaiLookup, $orderDetailsBySampel): int
    {
        $taken = 0;
        $orderKey = trim((string) $noOrder);
        $tanggalKey = trim((string) $tanggalSampling);

        foreach ($samples as $noSampel) {
            $selesaiKey = sprintf('%s|%s|%s', $orderKey, $tanggalKey, $noSampel);
            $orderDetail = $orderDetailsBySampel->get($noSampel);
            if (isset($selesaiLookup[$selesaiKey]) || $this->isPsikologiSample($orderDetail) || $this->isK3Sample($orderDetail)) {
                $taken++;
            }
        }

        return $taken;
    }

    private function resolveSubKategori($orderDetail, $basRow): string
    {
        if ($orderDetail && !empty($orderDetail->kategori_3)) {
            return $this->stripKategoriPrefix((string) $orderDetail->kategori_3);
        }
        if ($basRow && !empty($basRow->sub_kategori)) {
            $kategori = trim((string) ($basRow->kategori ?? ''));
            $sub = trim((string) $basRow->sub_kategori);

            return $kategori !== '' ? trim($kategori . ' ' . $sub) : $sub;
        }
        if ($orderDetail && !empty($orderDetail->kategori_2)) {
            return $this->stripKategoriPrefix((string) $orderDetail->kategori_2);
        }

        return '-';
    }

    private function isPsikologiSample($orderDetail, $basRow = null): bool
    {
        if ($orderDetail) {
            if ($this->fieldContains($orderDetail->kategori_3 ?? null, 'Psikologi')) {
                return true;
            }
            if ($this->fieldContains($orderDetail->parameter ?? null, 'Psikologi')) {
                return true;
            }
        }

        if ($basRow) {
            if ($this->fieldContains($basRow->kategori ?? null, 'Psikologi')) {
                return true;
            }
            if ($this->fieldContains($basRow->sub_kategori ?? null, 'Psikologi')) {
                return true;
            }
        }

        return false;
    }

    private function isK3Sample($orderDetail): bool
    {
        return $orderDetail && $this->fieldContains($orderDetail->parameter ?? null, 'K3');
    }

    private function fieldContains($raw, string $needle): bool
    {
        if ($raw === null || $raw === '') {
            return false;
        }

        if (!is_string($raw)) {
            $raw = json_encode($raw);
        }

        return stripos($raw, $needle) !== false;
    }

    private function formatTanggalSelesai($createdAt): ?string
    {
        if ($createdAt === null || $createdAt === '') {
            return null;
        }

        return Carbon::parse($createdAt)->subHours(7)->format('Y-m-d H:i:s');
    }

    private function stripKategoriPrefix(string $value): string
    {
        return trim((string) preg_replace('/^\d+-/', '', $value));
    }

    /** Bulan berjalan dipotong sampai hari ini (Asia/Jakarta). Bulan lain tetap 1 s/d akhir bulan. */
    private function resolvePeriodeRange(Request $request): array
    {
        $awal = $request->input('periode_awal');
        $akhir = $request->input('periode_akhir');
        $today = Carbon::now('Asia/Jakarta')->startOfDay();

        if (!$awal && !$akhir) {
            $awal = $today->copy()->startOfMonth()->toDateString();
            $akhir = $today->toDateString();
            return [$awal, $akhir];
        }

        try {
            $start = $awal ? Carbon::parse($awal, 'Asia/Jakarta')->startOfDay() : $today->copy()->startOfMonth();
            $end = $akhir ? Carbon::parse($akhir, 'Asia/Jakarta')->startOfDay() : $start->copy()->endOfMonth();
        } catch (\Throwable $e) {
            return [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()];
        }

        if ($start->isSameMonth($today) && $end->gt($today)) {
            $end = $today->copy();
        }

        return [$start->toDateString(), $end->toDateString()];
    }
}
