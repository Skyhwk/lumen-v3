<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAsset;
use App\Models\Jadwal;
use App\Models\JadwalMobilisasi;
use App\Models\JadwalMobilisasiAset;
use App\Models\MasterCabang;
use App\Models\MasterKaryawan;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\SamplingPlan;
use App\Services\IoPeralatanTimService;
use App\Services\JadwalMobilisasiService;
use App\Services\RenderInformasiOperasionalService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Yajra\Datatables\Datatables;

class InformasiOperasionalController extends Controller
{
    public function index(Request $request)
    {
        $tanggal = $this->tanggal($request);
        $jadwalTable = (new Jadwal())->getTable();
        $spTable = (new SamplingPlan())->getTable();
        $cabangTable = (new MasterCabang())->getTable();
        $qtTable = (new QuotationNonKontrak())->getTable();
        $qtcTable = (new QuotationKontrakH())->getTable();

        $query = Jadwal::query()
            ->from($jadwalTable)
            ->leftJoin($spTable . ' as sp', 'sp.id', '=', $jadwalTable . '.id_sampling')
            ->leftJoin($cabangTable . ' as mc', 'mc.id', '=', $jadwalTable . '.id_cabang')
            ->leftJoin($qtTable . ' as rq', 'rq.no_document', '=', $jadwalTable . '.no_quotation')
            ->leftJoin($qtcTable . ' as qtc', 'qtc.no_document', '=', $jadwalTable . '.no_quotation')
            ->select(
                $jadwalTable . '.id_sampling',
                $jadwalTable . '.parsial',
                $jadwalTable . '.no_quotation',
                $jadwalTable . '.nama_perusahaan',
                $jadwalTable . '.tanggal',
                $jadwalTable . '.periode',
                $jadwalTable . '.jam_mulai',
                $jadwalTable . '.jam_selesai',
                $jadwalTable . '.kategori',
                $jadwalTable . '.durasi',
                $jadwalTable . '.status',
                $jadwalTable . '.warna',
                $jadwalTable . '.note',
                $jadwalTable . '.driver',
                $jadwalTable . '.id_cabang',
                $jadwalTable . '.wilayah',
                $jadwalTable . '.kendaraan',
                DB::raw("GROUP_CONCAT(DISTINCT NULLIF(TRIM({$jadwalTable}.sampler), '') SEPARATOR ', ') as sampler"),
                DB::raw("GROUP_CONCAT({$jadwalTable}.id) as batch_id"),
                DB::raw('MAX(mc.nama_cabang) as nama_cabang'),
                DB::raw('MAX(COALESCE(rq.nama_pic_sampling, qtc.nama_pic_sampling)) as pic_nama'),
                DB::raw('MAX(COALESCE(rq.no_tlp_pic_sampling, qtc.no_tlp_pic_sampling)) as pic_telp'),
                DB::raw('MAX(COALESCE(rq.email_pic_sampling, qtc.email_pic_sampling)) as pic_email'),
                DB::raw('MAX(sp.is_approved) as is_approved'),
                DB::raw('MAX(sp.status_jadwal) as status_jadwal'),
                DB::raw('MAX(sp.tambahan) as qs_tambahan'),
                DB::raw('MAX(sp.keterangan_lain) as qs_keterangan_lain'),
                DB::raw('MAX(sp.petugas_jadwal) as petugas_jadwal'),
                DB::raw('MAX(sp.approved_at) as approved_at'),
                DB::raw('MAX(sp.approved_by) as approved_by'),
                DB::raw('MAX(sp.created_by) as created_by_sp'),
                DB::raw('MAX(sp.google_maps_url) as google_maps_url')
            )
            ->where($jadwalTable . '.is_active', true)
            ->whereNotNull($jadwalTable . '.no_quotation')
            ->whereDate($jadwalTable . '.tanggal', $tanggal)
            ->groupBy(
                $jadwalTable . '.id_sampling',
                $jadwalTable . '.parsial',
                $jadwalTable . '.no_quotation',
                $jadwalTable . '.nama_perusahaan',
                $jadwalTable . '.tanggal',
                $jadwalTable . '.periode',
                $jadwalTable . '.jam_mulai',
                $jadwalTable . '.jam_selesai',
                $jadwalTable . '.kategori',
                $jadwalTable . '.durasi',
                $jadwalTable . '.status',
                $jadwalTable . '.warna',
                $jadwalTable . '.note',
                $jadwalTable . '.driver',
                $jadwalTable . '.id_cabang',
                $jadwalTable . '.wilayah',
                $jadwalTable . '.kendaraan'
            );

        $this->applyCabangFilter($query, $request, $jadwalTable);

        if ($request->filled('warna')) {
            $query->whereRaw("LOWER({$jadwalTable}.warna) = ?", [strtolower(trim((string) $request->warna))]);
        }

        $query->orderBy($jadwalTable . '.jam_mulai', 'ASC');

        // Bawaan aktual = level TIM + TANGGAL (bukan per PT).
        $timPeralatanMap = IoPeralatanTimService::mapByTanggal($tanggal);

        return Datatables::of($query)
            ->addColumn('lokasi_penempatan', function ($row) {
                return $row->nama_cabang ?: 'HEAD OFFICE (Default)';
            })
            ->addColumn('mobilisasi', function ($row) use ($timPeralatanMap) {
                if (empty($row->batch_id)) {
                    return null;
                }

                $mob = JadwalMobilisasiService::findActiveByBatchIds($row->batch_id);
                if (!$mob) {
                    return null;
                }

                $peralatan = $this->decodePeralatan($mob['peralatan_sampling'] ?? []);
                $petugasKey = IoPeralatanTimService::normalizePetugasKey($row->sampler ?? '');
                $peralatanAktual = [];
                if ($petugasKey !== '' && isset($timPeralatanMap[$petugasKey])) {
                    $peralatanAktual = $timPeralatanMap[$petugasKey];
                } elseif (!empty($mob['peralatan_aktual'])) {
                    // fallback data lama yang sempat disimpan di jadwal_mobilisasi
                    $peralatanAktual = $this->decodePeralatan($mob['peralatan_aktual']);
                }

                $namaAlat = [];
                $jumlahAlat = [];
                foreach ($peralatan as $item) {
                    $nama = trim((string) ($item['nama_alat'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $namaAlat[] = $nama;
                    $jumlahAlat[] = (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
                }

                $namaAlatAktual = [];
                $jumlahAlatAktual = [];
                foreach ($peralatanAktual as $item) {
                    $nama = trim((string) ($item['nama_alat'] ?? ''));
                    if ($nama === '') {
                        continue;
                    }
                    $namaAlatAktual[] = $nama;
                    $jumlahAlatAktual[] = (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0);
                }

                $ketOps = trim(html_entity_decode(strip_tags((string) ($mob['keterangan_operasional'] ?? $mob['keterangan_operasional_sp'] ?? '')), ENT_QUOTES, 'UTF-8'));

                return [
                    'id' => $mob['id'] ?? null,
                    'tujuan_sampling' => $mob['tujuan_sampling'] ?? null,
                    'waktu_berangkat' => $mob['waktu_berangkat'] ?? null,
                    'estimasi_jam' => $mob['estimasi_jam'] ?? 0,
                    'estimasi_menit' => $mob['estimasi_menit'] ?? 0,
                    'keterangan_akomodasi' => $mob['keterangan_akomodasi'] ?? null,
                    'rute_perjalanan' => $mob['rute_perjalanan'] ?? null,
                    'estimasi_perjalanan' => $mob['estimasi_perjalanan'] ?? null,
                    'status_rilis_io' => $mob['status_rilis_io'] ?? 'draft',
                    'has_keterangan' => $ketOps !== '',
                    'keterangan_operasional' => $ketOps,
                    'peralatan_summary' => $this->requestSummary($peralatan),
                    'nama_alat' => $namaAlat,
                    'jumlah_alat_list' => $jumlahAlat,
                    'jumlah_alat' => $this->requestCount($peralatan),
                    'peralatan_aktual' => $peralatanAktual,
                    'nama_alat_aktual' => $namaAlatAktual,
                    'jumlah_alat_aktual' => $jumlahAlatAktual,
                    'has_peralatan_aktual' => count($namaAlatAktual) > 0,
                ];
            })
            ->addColumn('keterangan_qs', function ($row) {
                $items = array_merge(
                    $this->parseJsonList($row->qs_tambahan ?? null, true),
                    $this->parseJsonList($row->qs_keterangan_lain ?? null, false)
                );

                return $items ? implode(', ', $items) : '';
            })
            ->addColumn('petugas_sampling_plan', function ($row) {
                return $row->petugas_jadwal ?: ($row->created_by_sp ?? '');
            })
            ->addColumn('validasi_sp', function ($row) {
                if ((int) ($row->is_approved ?? 0) !== 1 || empty($row->approved_at)) {
                    return '';
                }
                try {
                    return Carbon::parse($row->approved_at)->locale('id')->translatedFormat('d F Y H:i');
                } catch (\Throwable $e) {
                    return (string) $row->approved_at;
                }
            })
            ->with(['cabang_options' => $this->cabangOptions()])
            ->make(true);
    }

    public function show(Request $request)
    {
        $row = $this->resolveJadwalContext($request);
        if (!$row) {
            return response()->json(['message' => 'Data jadwal tidak ditemukan.'], 404);
        }

        $record = !empty($row['batch_id'])
            ? JadwalMobilisasiService::findActiveRecordByBatchIds($row['batch_id'])
            : null;

        $mobilisasi = $record ? JadwalMobilisasiService::findActiveByBatchIds($row['batch_id']) : null;
        $groups = $this->buildPeralatanGroups($row, $record, true);
        $payload = $this->decorateDocumentPayload($row, $record, $mobilisasi, $groups);
        $payload['html'] = RenderInformasiOperasionalService::buildInnerHtml($payload);

        return response()->json(['data' => $payload]);
    }

    public function renderPdf(Request $request)
    {
        $row = $this->resolveJadwalContext($request);
        if (!$row) {
            return response()->json(['message' => 'Data jadwal tidak ditemukan.'], 404);
        }

        $record = !empty($row['batch_id'])
            ? JadwalMobilisasiService::findActiveRecordByBatchIds($row['batch_id'])
            : null;
        $mobilisasi = $record ? JadwalMobilisasiService::findActiveByBatchIds($row['batch_id']) : null;
        $groups = $this->buildPeralatanGroups($row, $record, false);

        $payload = $this->decorateDocumentPayload($row, $record, $mobilisasi, $groups);
        $filename = (new RenderInformasiOperasionalService())->renderPdf($payload);
        if (!$filename) {
            return response()->json(['message' => 'Gagal merender PDF Informasi Operasional.'], 500);
        }

        return response()->json([
            'filename' => $filename,
            'path' => RenderInformasiOperasionalService::DIR . '/' . $filename,
        ]);
    }

    public function save(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required',
            'warna' => 'nullable|string',
            'keterangan_operasional' => 'nullable|string',
            'peralatan_sampling' => 'nullable|array',
            'items' => 'nullable|array',
            'items.*.id_aset' => 'required|integer',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        $row = $this->resolveJadwalContext($request);
        if (!$row) {
            return response()->json(['message' => 'Data jadwal tidak ditemukan.'], 404);
        }

        $warna = strtolower(trim((string) $request->input('warna', $row['warna'] ?? '')));
        if ($warna !== '' && !in_array($warna, ['merah', 'biru', 'hitam'], true)) {
            return response()->json(['message' => 'Kategori persiapan (warna) tidak valid.'], 422);
        }

        $items = array_values(array_filter($request->input('items', []), fn ($item) => !empty($item['id_aset'])));
        $ids = array_map(fn ($item) => (int) $item['id_aset'], $items);
        if (count($ids) !== count(array_unique($ids))) {
            return response()->json(['message' => 'Nomor alat yang sama tidak boleh dipilih lebih dari sekali dalam tim ini.'], 422);
        }

        $record = JadwalMobilisasiService::findActiveRecordByBatchIds($row['batch_id']);
        if (!$record) {
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $normalized = JadwalMobilisasiService::normalizeBatchIds($row['batch_id']);
            $record = JadwalMobilisasi::create([
                'id_sampling' => $row['id_sampling'] ?? null,
                'no_quotation' => $row['no_quotation'] ?? null,
                'tanggal' => $row['tanggal'] ?? null,
                'warna' => $warna !== '' ? $warna : null,
                'batch_id' => $normalized['csv'],
                'batch_jadwal_ids' => $normalized['ids'],
                'is_active' => true,
                'status_rilis_io' => 'draft',
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $peralatan = $request->input('peralatan_sampling', []);
        foreach ($peralatan as $alat) {
            $jumlah = (int) ($alat['jumlah_alat'] ?? $alat['jumlah'] ?? 0);
            $nama = trim((string) ($alat['nama_alat'] ?? ''));
            if ($nama === '' || $jumlah < 1) {
                continue;
            }
            $nomorCount = 0;
            foreach ($items as $item) {
                if (strcasecmp(trim((string) ($item['nama_alat'] ?? '')), $nama) === 0) {
                    $nomorCount++;
                }
            }
            if ($nomorCount > $jumlah) {
                return response()->json([
                    'message' => "Nomor alat untuk {$nama} melebihi jumlah yang diinput ({$jumlah}).",
                ], 422);
            }
        }

        DB::transaction(function () use ($record, $request, $row, $warna, $items) {
            $batchIds = JadwalMobilisasiService::normalizeBatchIds($row['batch_id']);
            if (!empty($batchIds['ids']) && $warna !== '') {
                Jadwal::whereIn('id', $batchIds['ids'])->where('is_active', true)->update([
                    'warna' => $warna,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);
            }

            JadwalMobilisasiService::updateInformasiOperasional($record, [
                'warna' => $warna,
                'peralatan_sampling' => $request->input('peralatan_sampling', []),
                'keterangan_operasional' => $request->input('keterangan_operasional', ''),
            ], $this->karyawan);

            JadwalMobilisasiAset::where('id_mobilisasi', $record->id)
                ->where('is_active', true)
                ->update([
                    'is_active' => false,
                    'updated_by' => $this->karyawan,
                    'updated_at' => Carbon::now(),
                ]);

            foreach ($items as $item) {
                JadwalMobilisasiAset::create([
                    'id_mobilisasi' => $record->id,
                    'id_aset' => (int) $item['id_aset'],
                    'nama_alat' => $item['nama_alat'] ?? null,
                    'kategori_aset_id' => $this->nullableInt($item['kategori_aset_id'] ?? null),
                    'sub_kategori_aset_id' => $this->nullableInt($item['sub_kategori_aset_id'] ?? null),
                    'created_by' => $this->karyawan,
                    'updated_by' => $this->karyawan,
                    'is_active' => true,
                ]);
            }
        });

        return response()->json(['message' => 'Informasi operasional berhasil disimpan.']);
    }

    public function getPeralatanAvailable(Request $request)
    {
        $data = JadwalMobilisasiService::getPeralatanAvailable(
            $request->input('tanggal'),
            $request->input('branch_id') ?? $request->input('id_cabang'),
            $request->input('batch_id'),
            $request->input('warna'),
            $request->input('exclude_petugas_key') ?: $request->input('petugas_key')
        );

        return response()->json([
            'status' => true,
            'data' => $data,
        ]);
    }

    /**
     * Ringkasan peralatan 1 tim (N perusahaan) untuk modal Update Peralatan.
     * Request per PT (read-only referensi) + bawaan aktual = milik TIM/HARI.
     */
    public function showPeralatanTim(Request $request)
    {
        $batchGroups = $this->normalizeBatchGroupList($request->input('batch_ids', $request->input('batch_id')));
        if ($batchGroups === []) {
            return response()->json(['message' => 'batch_ids wajib diisi.'], 422);
        }

        $companies = [];
        $toolMap = [];
        $legacyAktual = null;
        $petugas = trim((string) $request->input('petugas_sampling', ''));
        $tanggal = null;
        $idCabang = null;
        $warnaSet = [];
        $cabangSet = [];

        foreach ($batchGroups as $batchIds) {
            $ctxRequest = Request::create('/', 'POST', ['batch_id' => $batchIds]);
            $row = $this->resolveJadwalContext($ctxRequest);
            if (!$row) {
                continue;
            }

            if ($petugas === '' && !empty($row['sampler'])) {
                $petugas = trim((string) $row['sampler']);
            }
            if ($tanggal === null && !empty($row['tanggal'])) {
                $tanggal = Carbon::parse($row['tanggal'])->format('Y-m-d');
            }
            if ($idCabang === null && !empty($row['id_cabang'])) {
                $idCabang = (int) $row['id_cabang'];
            }
            if (!empty($row['id_cabang'])) {
                $cabangSet[(int) $row['id_cabang']] = true;
            }
            if (!empty($row['warna'])) {
                $warnaSet[strtolower(trim((string) $row['warna']))] = true;
            }

            $mob = JadwalMobilisasiService::findActiveByBatchIds($batchIds);
            $requestItems = $this->decodePeralatan($mob['peralatan_sampling'] ?? []);
            $aktualItems = $this->decodePeralatan($mob['peralatan_aktual'] ?? []);
            if ($legacyAktual === null && $aktualItems !== []) {
                $legacyAktual = $aktualItems;
            }

            $perCompanyTools = [];
            foreach ($requestItems as $item) {
                $nama = trim((string) ($item['nama_alat'] ?? ''));
                if ($nama === '') {
                    continue;
                }
                $qty = max(0, (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0));
                $key = mb_strtolower($nama);
                $perCompanyTools[] = [
                    'nama_alat' => $nama,
                    'jumlah_alat' => $qty,
                ];

                if (!isset($toolMap[$key])) {
                    $toolMap[$key] = [
                        'nama_alat' => $nama,
                        'id_kategori_peralatan' => $item['id_kategori_peralatan'] ?? null,
                        'jenis_peralatan' => $item['jenis_peralatan'] ?? null,
                        'warna' => $item['warna'] ?? ($row['warna'] ?? null),
                        'id_cabang' => $item['id_cabang'] ?? ($row['id_cabang'] ?? null),
                        'request_total' => 0,
                        'request_max' => 0,
                        'per_company' => [],
                    ];
                }
                $toolMap[$key]['request_total'] += $qty;
                $toolMap[$key]['request_max'] = max($toolMap[$key]['request_max'], $qty);
                $toolMap[$key]['per_company'][] = [
                    'nama_perusahaan' => $row['nama_perusahaan'] ?? '-',
                    'batch_id' => $batchIds,
                    'jumlah_alat' => $qty,
                ];
            }

            $companies[] = [
                'batch_id' => $batchIds,
                'id_mobilisasi' => $mob['id'] ?? null,
                'nama_perusahaan' => $row['nama_perusahaan'] ?? '-',
                'wilayah' => $row['wilayah'] ?? null,
                'jam_mulai' => $row['jam_mulai'] ?? null,
                'tanggal' => $row['tanggal'] ?? null,
                'id_cabang' => $row['id_cabang'] ?? null,
                'warna' => $row['warna'] ?? null,
                'peralatan_request' => $perCompanyTools,
            ];
        }

        if ($companies === []) {
            return response()->json(['message' => 'Data jadwal tim tidak ditemukan.'], 404);
        }

        $suggested = [];
        foreach ($toolMap as $tool) {
            $suggested[] = [
                'nama_alat' => $tool['nama_alat'],
                'jumlah_alat' => max(1, (int) $tool['request_max']),
                'request_total' => (int) $tool['request_total'],
                'request_max' => (int) $tool['request_max'],
                'id_kategori_peralatan' => $tool['id_kategori_peralatan'],
                'jenis_peralatan' => $tool['jenis_peralatan'],
                'warna' => $tool['warna'],
                'id_cabang' => $tool['id_cabang'],
                'per_company' => $tool['per_company'],
            ];
        }

        $petugasKey = IoPeralatanTimService::normalizePetugasKey($petugas);
        $savedTim = ($tanggal && $petugasKey !== '')
            ? IoPeralatanTimService::findActive($tanggal, $petugasKey)
            : null;

        $savedAktual = null;
        if ($savedTim && !empty($savedTim->peralatan)) {
            $savedAktual = $savedTim->peralatan;
        } elseif ($legacyAktual !== null) {
            $savedAktual = $legacyAktual;
        }

        $aktual = $savedAktual !== null
            ? JadwalMobilisasiService::normalizePeralatanList($savedAktual)
            : array_map(function ($item) {
                return [
                    'nama_alat' => $item['nama_alat'],
                    'jumlah_alat' => $item['jumlah_alat'],
                    'id_kategori_peralatan' => $item['id_kategori_peralatan'],
                    'jenis_peralatan' => $item['jenis_peralatan'],
                    'warna' => $item['warna'],
                    'id_cabang' => $item['id_cabang'],
                ];
            }, $suggested);

        return response()->json([
            'petugas_sampling' => $petugas,
            'petugas_key' => $petugasKey,
            'tanggal' => $tanggal,
            'id_cabang' => $idCabang,
            'cabang_ids' => array_map('intval', array_keys($cabangSet)),
            'warna_list' => array_values(array_keys($warnaSet)),
            'company_count' => count($companies),
            'companies' => $companies,
            'request_summary' => $suggested,
            'peralatan_aktual' => $aktual,
            'has_saved_aktual' => $savedAktual !== null,
            'snapshots' => ($savedTim && is_array($savedTim->snapshots)) ? $savedTim->snapshots : [],
            'storage' => 'io_peralatan_tim',
        ]);
    }

    /**
     * Simpan bawaan aktual peralatan level TIM + TANGGAL (bukan per PT).
     */
    public function updatePeralatanTim(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_ids' => 'required|array|min:1',
            'petugas_sampling' => 'nullable|string',
            'peralatan_aktual' => 'required|array|min:1',
            'peralatan_aktual.*.nama_alat' => 'required|string',
            'peralatan_aktual.*.jumlah_alat' => 'required|integer|min:1',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if (!Schema::hasTable('io_peralatan_tim')) {
            return response()->json([
                'message' => 'Tabel io_peralatan_tim belum tersedia. Jalankan migrate terlebih dahulu.',
            ], 422);
        }

        $batchGroups = $this->normalizeBatchGroupList($request->input('batch_ids'));
        if ($batchGroups === []) {
            return response()->json(['message' => 'batch_ids tidak valid.'], 422);
        }

        $peralatan = JadwalMobilisasiService::normalizePeralatanList($request->input('peralatan_aktual', []));
        if ($peralatan === []) {
            return response()->json(['message' => 'Minimal 1 alat dengan jumlah >= 1.'], 422);
        }

        $petugas = trim((string) $request->input('petugas_sampling', ''));
        $tanggal = null;
        $idCabang = null;
        $flatBatchIds = [];

        foreach ($batchGroups as $batchIds) {
            $ctxRequest = Request::create('/', 'POST', ['batch_id' => $batchIds]);
            $row = $this->resolveJadwalContext($ctxRequest);
            if (!$row) {
                continue;
            }
            if ($petugas === '' && !empty($row['sampler'])) {
                $petugas = trim((string) $row['sampler']);
            }
            if ($tanggal === null && !empty($row['tanggal'])) {
                $tanggal = Carbon::parse($row['tanggal'])->format('Y-m-d');
            }
            if ($idCabang === null && !empty($row['id_cabang'])) {
                $idCabang = (int) $row['id_cabang'];
            }
            if (is_array($batchIds)) {
                foreach ($batchIds as $id) {
                    $flatBatchIds[] = $id;
                }
            } else {
                $flatBatchIds[] = $batchIds;
            }
        }

        if ($tanggal === null || trim($petugas) === '') {
            return response()->json([
                'message' => 'Tanggal / petugas sampling tim tidak ditemukan.',
            ], 422);
        }

        $record = null;
        DB::transaction(function () use ($tanggal, $petugas, $flatBatchIds, $peralatan, $idCabang, $batchGroups, &$record) {
            $record = IoPeralatanTimService::upsert(
                $tanggal,
                $petugas,
                $flatBatchIds,
                $peralatan,
                $idCabang,
                $this->karyawan
            );

            // Mirror opsional ke jadwal_mobilisasi (fallback baca lama) jika kolom masih ada.
            if (Schema::hasColumn('jadwal_mobilisasi', 'peralatan_aktual')) {
                JadwalMobilisasiService::syncPeralatanAktualForBatches(
                    $batchGroups,
                    $peralatan,
                    $this->karyawan
                );
            }
        });

        return response()->json([
            'message' => 'Peralatan bawaan aktual tim berhasil disimpan.',
            'id' => $record->id ?? null,
            'tanggal' => $tanggal,
            'petugas_sampling' => $petugas,
            'snapshots_count' => is_array($record->snapshots ?? null) ? count($record->snapshots) : 0,
            'storage' => 'io_peralatan_tim',
        ]);
    }

    /**
     * Update keterangan operasional per perusahaan (1 batch / no QT → 1 jadwal_mobilisasi).
     * Tidak mengubah peralatan.
     */
    public function updateKeteranganOperasional(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'batch_id' => 'required',
            'keterangan_operasional' => 'nullable|string',
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 422);
        }

        if (!Schema::hasColumn('jadwal_mobilisasi', 'keterangan_operasional')) {
            return response()->json([
                'message' => 'Kolom keterangan_operasional belum tersedia.',
            ], 422);
        }

        $row = $this->resolveJadwalContext($request);
        if (!$row) {
            return response()->json(['message' => 'Data jadwal tidak ditemukan.'], 404);
        }

        $record = JadwalMobilisasiService::findActiveRecordByBatchIds($row['batch_id']);
        if (!$record) {
            $now = Carbon::now()->format('Y-m-d H:i:s');
            $normalized = JadwalMobilisasiService::normalizeBatchIds($row['batch_id']);
            $record = JadwalMobilisasi::create([
                'id_sampling' => $row['id_sampling'] ?? null,
                'no_quotation' => $row['no_quotation'] ?? null,
                'tanggal' => $row['tanggal'] ?? null,
                'warna' => $row['warna'] ?? null,
                'batch_id' => $normalized['csv'],
                'batch_jadwal_ids' => $normalized['ids'],
                'is_active' => true,
                'status_rilis_io' => 'draft',
                'created_by' => $this->karyawan,
                'updated_by' => $this->karyawan,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        JadwalMobilisasiService::updateInformasiOperasional($record, [
            'warna' => $record->warna,
            'keterangan_operasional' => $request->input('keterangan_operasional', ''),
        ], $this->karyawan);

        return response()->json([
            'message' => 'Keterangan operasional berhasil disimpan.',
            'no_quotation' => $row['no_quotation'] ?? null,
            'id_mobilisasi' => $record->id,
        ]);
    }

    /**
     * @param  mixed  $raw
     * @return array<int, string|array>
     */
    private function normalizeBatchGroupList($raw): array
    {
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $raw = $decoded;
            } else {
                $raw = [$raw];
            }
        }
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $item) {
            $normalized = JadwalMobilisasiService::normalizeBatchIds($item);
            if (!empty($normalized['ids'])) {
                $result[] = $normalized['csv'] !== '' ? $normalized['csv'] : $normalized['ids'];
            }
        }

        return $result;
    }

    public function getNomorAlatAvailable(Request $request)
    {
        $namaAlat = trim((string) $request->input('nama_alat', ''));
        if ($namaAlat === '') {
            return response()->json(['data' => []]);
        }

        return response()->json([
            'data' => $this->assetOptions(
                $namaAlat,
                $request->input('tanggal'),
                $this->nullableInt($request->input('id_mobilisasi')),
                $this->nullableInt($request->input('id_cabang') ?? $request->input('branch_id')),
                array_filter(array_map('intval', (array) $request->input('keep_ids', []))),
                $this->nullableInt($request->input('kategori_aset_id')),
                $this->nullableInt($request->input('sub_kategori_aset_id'))
            ),
        ]);
    }

    private function resolveJadwalContext(Request $request): ?array
    {
        $batchIds = JadwalMobilisasiService::normalizeBatchIds($request->input('batch_id'));
        if (empty($batchIds['ids'])) {
            return null;
        }

        $rows = Jadwal::query()
            ->whereIn('id', $batchIds['ids'])
            ->where('is_active', true)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $first = $rows->first();
        $sp = SamplingPlan::find($first->id_sampling);
        $quotation = null;
        if ($first->no_quotation && strpos((string) $first->no_quotation, '/QTC/') !== false) {
            $quotation = QuotationKontrakH::where('no_document', $first->no_quotation)->first();
        } else {
            $quotation = QuotationNonKontrak::where('no_document', $first->no_quotation)->first();
        }

        $cabang = $first->id_cabang
            ? MasterCabang::select('id', 'nama_cabang')->find($first->id_cabang)
            : null;

        return [
            'batch_id' => $batchIds['csv'],
            'id_sampling' => $first->id_sampling,
            'no_quotation' => $first->no_quotation,
            'nama_perusahaan' => $first->nama_perusahaan,
            'tanggal' => $first->tanggal,
            'jam_mulai' => $first->jam_mulai,
            'jam_selesai' => $first->jam_selesai,
            'warna' => $first->warna,
            'id_cabang' => $first->id_cabang,
            'nama_cabang' => $cabang ? ($cabang->nama_cabang ?? 'HEAD OFFICE (Default)') : 'HEAD OFFICE (Default)',
            'kendaraan' => $first->kendaraan,
            'driver' => $first->driver,
            'sampler' => $rows->pluck('sampler')->filter()->unique()->implode(', '),
            'wilayah' => $first->wilayah,
            'status' => $first->status,
            'pic_nama' => $quotation ? ($quotation->nama_pic_sampling ?? null) : null,
            'pic_telp' => $quotation ? ($quotation->no_tlp_pic_sampling ?? null) : null,
            'pic_email' => $quotation ? ($quotation->email_pic_sampling ?? null) : null,
            'alamat_sampling' => $quotation ? ($quotation->alamat_sampling ?? null) : null,
            'qs_tambahan' => $sp ? ($sp->tambahan ?? null) : null,
            'qs_keterangan_lain' => $sp ? ($sp->keterangan_lain ?? null) : null,
            'is_approved' => $sp ? ($sp->is_approved ?? 0) : 0,
            'status_jadwal' => $sp ? ($sp->status_jadwal ?? null) : null,
            'petugas_jadwal' => $sp ? ($sp->petugas_jadwal ?? null) : null,
            'approved_by' => $sp ? ($sp->approved_by ?? null) : null,
            'approved_at' => $sp ? ($sp->approved_at ?? null) : null,
            'created_by_sp' => $sp ? ($sp->created_by ?? $first->created_by) : $first->created_by,
            'jabatan_pic' => $quotation ? ($quotation->jabatan_pic_sampling ?? null) : null,
            'kategori' => $rows->pluck('kategori')->filter()->values()->all(),
        ];
    }

    private function buildPeralatanGroups(array $row, $record, bool $withOptions = true): array
    {
        $peralatan = $this->decodePeralatan($record ? $record->peralatan_sampling : []);
        $assigned = $record
            ? JadwalMobilisasiAset::query()
                ->where('id_mobilisasi', $record->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get()
            : collect();

        $assetMap = [];
        $assetIds = $assigned->pluck('id_aset')->filter()->unique()->all();
        if ($assetIds) {
            $assetMap = GaAsset::whereIn('id', $assetIds)
                ->get(['id', 'cs_code', 'name'])
                ->keyBy('id');
        }

        $remainingAssigned = $assigned;
        $groups = [];
        foreach ($peralatan as $index => $item) {
            $nama = trim((string) ($item['nama_alat'] ?? ''));
            $jumlah = max(1, (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 1));
            $kategoriId = $this->nullableInt($item['kategori_aset_id'] ?? null);
            $subKategoriId = $this->nullableInt($item['sub_kategori_aset_id'] ?? null);
            $matched = $this->takeAssigned($remainingAssigned, $nama, $kategoriId, $subKategoriId);
            $pickedIds = array_map(fn ($aset) => (int) $aset->id_aset, $matched);
            $slots = array_pad($pickedIds, $jumlah, null);
            $nomorAlat = [];
            foreach ($pickedIds as $idAset) {
                $asset = $assetMap[$idAset] ?? null;
                $code = trim((string) ($asset->cs_code ?? ''));
                if ($code !== '') {
                    $nomorAlat[] = $code;
                }
            }

            $group = [
                'key' => $index,
                'nama_alat' => $nama,
                'jumlah_alat' => $jumlah,
                'id_kategori_peralatan' => $this->nullableInt($item['id_kategori_peralatan'] ?? null),
                'jenis_peralatan' => $item['jenis_peralatan'] ?? null,
                'warna' => $item['warna'] ?? ($row['warna'] ?? null),
                'id_cabang' => $this->nullableInt($item['id_cabang'] ?? $item['branch_id'] ?? $row['id_cabang'] ?? null),
                'kategori_aset_id' => $kategoriId,
                'sub_kategori_aset_id' => $subKategoriId,
                'slots' => $slots,
                'nomor_alat' => $nomorAlat,
            ];
            if ($withOptions) {
                $group['options'] = $this->assetOptions(
                    $nama,
                    $row['tanggal'] ?? null,
                    $record ? $record->id : null,
                    $this->nullableInt($item['id_cabang'] ?? $item['branch_id'] ?? $row['id_cabang'] ?? null),
                    $slots,
                    $kategoriId,
                    $subKategoriId
                );
            }
            $groups[] = $group;
        }

        return $groups;
    }

    private function decorateDocumentPayload(array $row, $record, $mobilisasi, array $groups): array
    {
        $tanggalRaw = $row['tanggal'] ?? null;
        $tanggalLabel = '-';
        if ($tanggalRaw) {
            try {
                $tanggalLabel = Carbon::parse($tanggalRaw)->locale('id')->translatedFormat('d F Y');
            } catch (\Throwable $e) {
                $tanggalLabel = (string) $tanggalRaw;
            }
        }

        $validasiAt = '-';
        if (!empty($row['approved_at'])) {
            try {
                $validasiAt = Carbon::parse($row['approved_at'])->locale('id')->translatedFormat('d F Y H:i');
            } catch (\Throwable $e) {
                $validasiAt = (string) $row['approved_at'];
            }
        }

        $picParts = array_filter([
            $row['pic_nama'] ?? null,
            !empty($row['jabatan_pic']) ? '(' . $row['jabatan_pic'] . ')' : null,
            $row['pic_telp'] ?? null,
        ]);

        $mob = is_array($mobilisasi) ? $mobilisasi : [];
        $keteranganOps = $mob['keterangan_operasional'] ?? $mob['keterangan_operasional_sp'] ?? '';
        $isApproved = (int) ($row['is_approved'] ?? 0) === 1;
        $samples = $this->collectSamples($row);

        return array_merge($row, [
            'id_mobilisasi' => $record ? $record->id : null,
            'judul_dokumen' => RenderInformasiOperasionalService::judulByWarna($row['warna'] ?? ''),
            'tanggal_label' => $tanggalLabel,
            'pic_display' => $picParts ? implode(' - ', $picParts) : '-',
            'no_order' => $samples['no_order'],
            'samples' => $samples['samples'],
            'jumlah_sampel' => $samples['jumlah_sampel'],
            'tujuan_sampling' => $mob['tujuan_sampling'] ?? null,
            'waktu_berangkat' => $mob['waktu_berangkat'] ?? null,
            'keterangan_akomodasi' => $mob['keterangan_akomodasi'] ?? null,
            'rute_perjalanan' => $mob['rute_perjalanan'] ?? null,
            'estimasi_perjalanan' => $mob['estimasi_perjalanan'] ?? null,
            'keterangan_operasional' => $keteranganOps,
            'keterangan_operasional_sp' => $mob['keterangan_operasional_sp'] ?? '',
            'status_rilis_io' => $mob['status_rilis_io'] ?? 'draft',
            'groups' => $groups,
            'petugas' => $this->petugasSampling($row['sampler'] ?? ''),
            'peralatan' => $this->assignedPeralatanFromMonitoringAlat($record),
            'qs_tambahan' => $this->parseJsonList($row['qs_tambahan'] ?? null, true),
            'qs_keterangan_lain' => $this->parseJsonList($row['qs_keterangan_lain'] ?? null, false),
            'petugas_sampling_plan' => $row['petugas_jadwal'] ?? ($row['created_by_sp'] ?? '-'),
            'validasi_by' => $isApproved ? ($row['approved_by'] ?? '-') : '-',
            'validasi_at' => $isApproved ? $validasiAt : '-',
        ]);
    }

    private function assignedPeralatanFromMonitoringAlat($record): array
    {
        if (!$record || !Schema::hasTable('jadwal_mobilisasi_aset')) {
            return [];
        }

        $assigned = JadwalMobilisasiAset::query()
            ->where('id_mobilisasi', $record->id)
            ->where('is_active', true)
            ->orderBy('id')
            ->get();
        if ($assigned->isEmpty()) {
            return [];
        }

        $assetIds = $assigned->pluck('id_aset')->filter()->unique()->all();
        if (!$assetIds) {
            return [];
        }

        $assetMap = GaAsset::query()
            ->whereIn('id', $assetIds)
            ->get(['id', 'cs_code', 'name'])
            ->keyBy('id');

        $grouped = [];
        foreach ($assigned as $row) {
            $asset = $assetMap[$row->id_aset] ?? null;
            $cs = trim((string) ($asset->cs_code ?? ''));
            if ($cs === '') {
                continue;
            }

            $namaAlat = trim((string) ($row->nama_alat ?? ''));
            $namaAset = trim((string) ($asset->name ?? ''));
            $key = strtolower($namaAlat !== '' ? $namaAlat : $namaAset);
            if ($key === '') {
                $key = 'aset-' . (int) $row->id_aset;
            }

            if (!isset($grouped[$key])) {
                $grouped[$key] = [
                    'nama_alat' => $namaAlat !== '' ? $namaAlat : $namaAset,
                    'nama_aset' => $namaAset,
                    'jenis_peralatan' => null,
                    'jumlah' => 0,
                    'nomor_alat' => [],
                    'cs_code' => [],
                ];
            }

            $grouped[$key]['jumlah']++;
            $grouped[$key]['nomor_alat'][] = $cs;
            $grouped[$key]['cs_code'][] = $cs;
        }

        return array_values($grouped);
    }

    private function petugasSampling(?string $samplerCsv): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', preg_split('/,/', (string) $samplerCsv) ?: []))));
        if (!$names) {
            return [];
        }

        $metaByNama = [];
        try {
            $rows = MasterKaryawan::query()
                ->whereIn('nama_lengkap', $names)
                ->where('is_active', true)
                ->get();

            foreach ($rows as $row) {
                $jabatanNama = '-';
                try {
                    $rel = $row->getRelationValue('jabatan');
                    if (is_object($rel) && !empty($rel->nama_jabatan)) {
                        $jabatanNama = $rel->nama_jabatan;
                    }
                } catch (\Throwable $e) {
                    $jabatanNama = '-';
                }
                $metaByNama[$row->nama_lengkap] = [
                    'jabatan' => $jabatanNama,
                    'kompetensi' => $this->kompetensiKaryawan($row),
                ];
            }
        } catch (\Throwable $e) {
            $metaByNama = [];
        }

        return array_map(function ($nama) use ($metaByNama) {
            return [
                'nama' => $nama,
                'jabatan' => $metaByNama[$nama]['jabatan'] ?? '-',
                'kompetensi' => $metaByNama[$nama]['kompetensi'] ?? '-',
            ];
        }, $names);
    }

    private function kompetensiKaryawan($row): string
    {
        foreach (['kompetensi', 'grade', 'kategori_sampler', 'level'] as $field) {
            $value = trim((string) ($row->{$field} ?? ''));
            if ($value !== '') {
                return $value;
            }
        }

        try {
            $skill = $row->keahlian_karyawan()->orderBy('id')->value('keahlian');
            if (trim((string) $skill) !== '') {
                return trim((string) $skill);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        try {
            $sert = $row->sertifikat_karyawan()->orderBy('id')->value('nama_sertifikat');
            if (trim((string) $sert) !== '') {
                return trim((string) $sert);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        return '-';
    }

    private function collectSamples(array $row): array
    {
        $entries = [];
        foreach ((array) ($row['kategori'] ?? []) as $raw) {
            $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
            if (!is_array($decoded)) {
                $decoded = is_string($raw) && trim($raw) !== '' ? [trim($raw)] : [];
            }
            foreach ($decoded as $item) {
                $item = trim((string) $item);
                if ($item === '') {
                    continue;
                }
                $parts = explode(' - ', $item, 2);
                $entries[] = [
                    'kategori' => trim($parts[0]),
                    'kode' => isset($parts[1]) ? trim($parts[1]) : '',
                ];
            }
        }

        $noOrder = null;
        try {
            $header = OrderHeader::where('no_document', $row['no_quotation'] ?? '')
                ->where('is_active', true)
                ->first(['no_order']);
            $noOrder = $header->no_order ?? null;
        } catch (\Throwable $e) {
            $noOrder = null;
        }

        if (!$noOrder) {
            $segments = preg_split('/[\/]/', (string) ($row['no_quotation'] ?? ''));
            $noOrder = $segments ? end($segments) : (string) ($row['no_quotation'] ?? '');
        }

        $samples = [];
        $seen = [];
        foreach ($entries as $entry) {
            $kode = $entry['kode'];
            if ($kode === '') {
                $noSampel = '';
            } elseif (strpos($kode, '/') !== false) {
                $noSampel = $kode;
            } else {
                $noSampel = $noOrder . '/' . $kode;
            }
            $key = $noSampel . '|' . $entry['kategori'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $samples[] = [
                'no_sampel' => $noSampel,
                'kategori' => $this->displayKategori($entry['kategori']),
            ];
        }

        if (!$samples) {
            try {
                $query = OrderDetail::query()
                    ->where('no_quotation', $row['no_quotation'] ?? '')
                    ->where('is_active', true);
                if (!empty($row['tanggal'])) {
                    $query->whereDate('tanggal_sampling', $row['tanggal']);
                }
                $details = $query->orderBy('no_sampel')->get(['no_sampel', 'kategori_3', 'kategori_2']);
                foreach ($details as $detail) {
                    $samples[] = [
                        'no_sampel' => $detail->no_sampel,
                        'kategori' => $this->displayKategori($detail->kategori_3 ?: $detail->kategori_2),
                    ];
                }
            } catch (\Throwable $e) {
                $samples = [];
            }
        }

        return [
            'no_order' => $noOrder,
            'samples' => $samples,
            'jumlah_sampel' => count($samples),
        ];
    }

    private function displayKategori(?string $raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return '-';
        }
        if (preg_match('/^\d+\s*-\s*(.+)$/', $raw, $match)) {
            return trim($match[1]);
        }

        return $raw;
    }

    private function parseJsonList($raw, bool $asQsLabel = false): array
    {
        if (is_array($raw)) {
            $items = $raw;
        } elseif (is_string($raw) && trim($raw) !== '') {
            $decodedHtml = html_entity_decode($raw, ENT_QUOTES, 'UTF-8');
            $parsed = json_decode($decodedHtml, true);
            $items = is_array($parsed) ? $parsed : [$decodedHtml];
        } else {
            $items = [];
        }

        $labels = [
            'genset' => 'GENSET',
            'sarungLatex' => 'SARUNG TANGAN (LATEX)',
            'masker' => 'MASKER',
            'sarungKain' => 'SARUNG TANGAN (KAIN)',
            'faceShield' => 'FACE SHIELD',
            'workingPermit' => 'WORKING PERMIT',
            'apdLengkap' => 'APD LENGKAP',
            'pickupSampel' => 'PICK UP SAMPEL',
            'hazmat' => 'HAZMAT',
        ];

        $out = [];
        foreach ($items as $item) {
            $value = trim((string) $item);
            if ($value === '') {
                continue;
            }
            if ($asQsLabel && isset($labels[$value])) {
                $out[] = $labels[$value];
            } else {
                $out[] = $value;
            }
        }

        return $out;
    }

    private function applyCabangFilter($query, Request $request, string $jadwalTable): void
    {
        $privileges = array_map('strval', (array) $this->privilageCabang);
        $isPusat = in_array('1', $privileges, true);

        if ($isPusat) {
            if ($request->filled('id_cabang_filter')) {
                $idCabang = is_array($request->id_cabang_filter)
                    ? $request->id_cabang_filter
                    : [$request->id_cabang_filter];
                $query->where(function ($q) use ($idCabang, $jadwalTable) {
                    $filtered = array_filter($idCabang, fn ($v) => $v !== 'null' && $v !== '');
                    if (!empty($filtered)) {
                        $q->whereIn($jadwalTable . '.id_cabang', $filtered);
                    }
                    if (in_array('null', $idCabang, true) || in_array('', $idCabang, true)) {
                        $q->orWhereNull($jadwalTable . '.id_cabang');
                    }
                });
            }
            return;
        }

        $query->whereIn($jadwalTable . '.id_cabang', $privileges);
        if ($request->filled('id_cabang_filter')) {
            $reqFilter = is_array($request->id_cabang_filter)
                ? $request->id_cabang_filter
                : [$request->id_cabang_filter];
            $query->whereIn($jadwalTable . '.id_cabang', $reqFilter);
        }
    }

    private function cabangOptions(): array
    {
        $privileges = array_map('strval', (array) $this->privilageCabang);
        $isPusat = in_array('1', $privileges, true);
        $query = MasterCabang::select('id', 'nama_cabang');
        if (!$isPusat) {
            $query->whereIn('id', $privileges);
        }

        return $query->get()->map(fn ($item) => [
            'value' => $item->id,
            'label' => $item->nama_cabang,
        ])->values()->all();
    }

    private function eligibleAsset()
    {
        return GaAsset::query()
            ->where('is_active', true)
            ->where('condition', 'normal')
            ->where(function ($query) {
                $query->where('record_kind', GaAsset::RECORD_UNIT)
                    ->orWhereNull('record_kind');
            });
    }

    private function assetOptions($namaAlat, $tanggal, $currentMobilisasiId, $branchId, array $keepIds = [], $kategoriId = null, $subKategoriId = null)
    {
        $usedIds = [];
        if ($tanggal && Schema::hasTable('jadwal_mobilisasi_aset')) {
            $usedQuery = DB::table('jadwal_mobilisasi_aset as a')
                ->join('jadwal_mobilisasi as m', 'm.id', '=', 'a.id_mobilisasi')
                ->where('a.is_active', true)
                ->where('m.is_active', true)
                ->whereDate('m.tanggal', $tanggal);
            if ($currentMobilisasiId) {
                $usedQuery->where('m.id', '!=', $currentMobilisasiId);
            }
            $usedIds = $usedQuery->pluck('a.id_aset')->map(fn ($id) => (int) $id)->all();
        }

        $columns = [
            'id', 'name', 'cs_code', 'brand', 'model_spec',
            'kategori_aset_id', 'sub_kategori_aset_id',
        ];

        $buildQuery = function ($useKategori, $useBranch) use ($namaAlat, $kategoriId, $subKategoriId, $branchId, $usedIds) {
            $query = $this->eligibleAsset();
            $nama = trim((string) $namaAlat);

            if ($useKategori && $subKategoriId) {
                $query->where('sub_kategori_aset_id', $subKategoriId);
            } elseif ($useKategori && $kategoriId) {
                $query->where('kategori_aset_id', $kategoriId);
            }

            if ($nama !== '') {
                $query->where(function ($inner) use ($nama) {
                    $inner->where('name', 'like', '%' . $nama . '%')
                        ->orWhere('cs_code', 'like', '%' . $nama . '%');
                });
            }

            if ($useBranch && $branchId) {
                $query->where('branch_id', $branchId);
            }
            if ($usedIds) {
                $query->whereNotIn('id', $usedIds);
            }

            return $query;
        };

        $options = $buildQuery(true, true)
            ->orderBy('cs_code')
            ->orderBy('name')
            ->get($columns);

        if ($options->isEmpty() && ($kategoriId || $subKategoriId) && trim((string) $namaAlat) !== '') {
            $options = $buildQuery(false, true)
                ->orderBy('cs_code')
                ->orderBy('name')
                ->get($columns);
        }

        if ($options->isEmpty() && $branchId) {
            $options = $buildQuery(false, false)
                ->orderBy('cs_code')
                ->orderBy('name')
                ->get($columns);
        }

        $keepIds = array_values(array_unique(array_filter(array_map('intval', $keepIds))));
        $missing = array_diff($keepIds, $options->pluck('id')->map(fn ($id) => (int) $id)->all());
        if ($missing) {
            $options = $options->concat(GaAsset::whereIn('id', $missing)->get($columns));
        }

        return $options->map(function ($asset) {
            $code = trim((string) ($asset->cs_code ?? ''));
            $name = trim((string) ($asset->name ?? ''));
            $label = $code !== '' && $name !== '' && strcasecmp($code, $name) !== 0
                ? $code . ' — ' . $name
                : ($code !== '' ? $code : $name);

            return [
                'id' => $asset->id,
                'text' => $label,
                'label' => $label,
                'name' => $name,
                'cs_code' => $code,
                'kategori_aset_id' => $asset->kategori_aset_id,
                'sub_kategori_aset_id' => $asset->sub_kategori_aset_id,
            ];
        })->values();
    }

    private function takeAssigned(&$assigned, $nama, $kategoriId, $subKategoriId)
    {
        $picked = [];
        $remaining = [];
        foreach ($assigned as $row) {
            $rowNama = trim((string) $row->nama_alat);
            $matchNama = $nama !== '' && $rowNama !== '' && strcasecmp($rowNama, $nama) === 0;
            $matchSub = $subKategoriId && (int) $row->sub_kategori_aset_id === $subKategoriId;
            $matchKat = !$subKategoriId && $kategoriId && (int) $row->kategori_aset_id === $kategoriId;
            if ($matchNama || ($rowNama === '' && ($matchSub || $matchKat))) {
                $picked[] = $row;
            } else {
                $remaining[] = $row;
            }
        }
        $assigned = collect($remaining);

        return $picked;
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

    private function requestSummary(array $items): string
    {
        if (!$items) {
            return '-';
        }

        return collect($items)
            ->map(fn ($item) => trim(($item['nama_alat'] ?? 'Alat') . ' x' . (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0)))
            ->implode(', ');
    }

    private function requestCount(array $items): int
    {
        return (int) collect($items)->sum(fn ($item) => (int) ($item['jumlah_alat'] ?? $item['jumlah'] ?? 0));
    }

    private function hasKeterangan($record): bool
    {
        foreach (['keterangan_operasional', 'keterangan_operasional_sp'] as $col) {
            $val = trim((string) ($record->{$col} ?? ''));
            if ($val !== '') {
                return true;
            }
        }

        return false;
    }

    private function tanggal(Request $request): string
    {
        $tanggal = $request->input('tanggal');
        if (!$tanggal || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal)) {
            return Carbon::now('Asia/Jakarta')->toDateString();
        }

        return $tanggal;
    }

    private function nullableInt($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value ?: null;
    }
}
