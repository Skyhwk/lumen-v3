<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\BakInvestigasiDocumentService;
use App\Services\BakInvestigasiService;
use App\Services\BakLaporanService;
use App\Services\BeritaAcaraKejadianDocumentService;
use App\Services\GenerateQrDocumentBeritaAcaraKejadian;
use App\Services\InvestigasiAccessService;
use App\Services\InvestigasiPengesahanService;
use App\Services\MenuDataAccessService;
use App\Support\InvestigasiAwalStatus;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Investigasi Awal (F1) — endpoint khusus modul investigasi.
 * Tidak mencampur logika CRUD BAK di BeritaAcaraKejadianController.
 */
class InvestigasiController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return response()->json(['success' => true, 'data' => [], 'counts' => []], 200);
        }

        $workflowTab = strtolower(trim((string) $request->input('workflow_tab', 'pengisian')));
        $periode = (int) $request->input('periode', 0);

        $query = DB::table('bak_investigasi')->where('is_active', true);

        if ($periode >= 2000) {
            $query->whereYear('tanggal_kejadian', $periode);
        }

        if ($workflowTab === 'hasil') {
            $query->where('status', InvestigasiAwalStatus::MENUNGGU_HASIL);
        } elseif ($workflowTab === 'pengesahan') {
            $query->whereIn('status', [
                InvestigasiAwalStatus::ISSUED,
                InvestigasiAwalStatus::MENUNGGU_PENGESAHAN,
            ]);
        } else {
            $query->where('status', InvestigasiAwalStatus::DRAFT);
        }

        $rows = $query->orderByDesc('updated_at')->limit(500)->get();

        $creatorIds = $rows->pluck('creator_karyawan_id')
            ->filter(static fn ($id) => (int) $id > 0)
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
        $creatorNamesById = [];
        if ($creatorIds !== [] && Schema::hasTable('master_karyawan')) {
            $creatorNamesById = MasterKaryawan::query()
                ->whereIn('id', $creatorIds)
                ->pluck('nama_lengkap', 'id')
                ->all();
        }

        $counts = [
            'pengisian' => (int) DB::table('bak_investigasi')
                ->where('is_active', true)
                ->where('status', InvestigasiAwalStatus::DRAFT)
                ->count(),
            'hasil' => (int) DB::table('bak_investigasi')
                ->where('is_active', true)
                ->where('status', InvestigasiAwalStatus::MENUNGGU_HASIL)
                ->count(),
            'pengesahan' => (int) DB::table('bak_investigasi')
                ->where('is_active', true)
                ->whereIn('status', [
                    InvestigasiAwalStatus::ISSUED,
                    InvestigasiAwalStatus::MENUNGGU_PENGESAHAN,
                ])
                ->count(),
        ];

        $investigasiIds = $rows->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn ($id) => $id > 0)
            ->values()
            ->all();
        $bakNomorByInvestigasi = $this->loadBakNomorByInvestigasiIds($investigasiIds);

        $data = $rows->map(
            fn ($row) => $this->formatInvestigasiRow($row, $creatorNamesById, $bakNomorByInvestigasi)
        )->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'counts' => $counts,
        ], 200);
    }

    public function detail(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0 || !Schema::hasTable('bak_investigasi')) {
            return response()->json(['success' => false, 'message' => 'ID tidak valid.'], 422);
        }

        $row = DB::table('bak_investigasi')->where('id', $id)->where('is_active', true)->first();
        if ($row === null) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        $pihak = Schema::hasTable('bak_investigasi_pihak')
            ? DB::table('bak_investigasi_pihak')->where('bak_investigasi_id', $id)->orderBy('urutan')->get()
            : collect();

        $tindakLanjut = Schema::hasTable('bak_investigasi_tindak_lanjut')
            ? DB::table('bak_investigasi_tindak_lanjut')->where('bak_investigasi_id', $id)->orderBy('urutan')->get()
            : collect();

        $pengesahan = Schema::hasTable('bak_investigasi_pengesahan')
            ? DB::table('bak_investigasi_pengesahan')->where('bak_investigasi_id', $id)->orderBy('urutan')->get()
            : collect();

        $bakIds = Schema::hasTable('bak_investigasi_berita_acara')
            ? DB::table('bak_investigasi_berita_acara')
                ->where('bak_investigasi_id', $id)
                ->pluck('berita_acara_id')
                ->map(fn ($v) => (int) $v)
                ->all()
            : [];

        $bakNomorByInvestigasi = $this->loadBakNomorByInvestigasiIds([$id]);
        $linkedBak = $this->loadLinkedBakRowsForInvestigasi($id);

        return response()->json([
            'success' => true,
            'data' => array_merge($this->formatInvestigasiRow($row, [], $bakNomorByInvestigasi), [
                'record' => $row,
                'pihak' => $pihak,
                'tindak_lanjut' => $tindakLanjut,
                'pengesahan' => $pengesahan,
                'bak_ids' => $bakIds,
                'linked_bak' => $linkedBak,
                'can_approve_diketahui' => $this->user_id
                    ? app(InvestigasiPengesahanService::class)->viewerCanApproveDiketahui($id, (int) $this->user_id)
                    : false,
                'is_legal_manager' => $this->viewerIsLegalManager($request),
            ]),
        ], 200);
    }

    /**
     * Opsi surat teguran (G.1) — difilter karyawan terlapor / BAK terkait bila ada.
     */
    public function listSuratTeguranOptions(Request $request)
    {
        if (!Schema::hasTable('surat_teguran_disipliner')) {
            return response()->json(['success' => true, 'data' => []], 200);
        }

        $investigasiId = (int) $request->input('investigasi_id', 0);
        $search = trim((string) $request->input('q', ''));
        $karyawanIds = $this->resolveInvestigasiKaryawanIds($investigasiId);
        $selectedId = (int) $request->input('selected_id', 0);

        $query = DB::table('surat_teguran_disipliner as st')
            ->join('master_karyawan as mk', 'mk.id', '=', 'st.karyawan_id')
            ->where('st.is_active', true)
            ->where('mk.is_active', true)
            ->whereIn('st.status', ['draft', 'issued']);

        if ($karyawanIds !== []) {
            $query->whereIn('st.karyawan_id', $karyawanIds);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('st.nomor_surat', 'like', $like)
                    ->orWhere('st.perihal', 'like', $like)
                    ->orWhere('mk.nama_lengkap', 'like', $like)
                    ->orWhere('mk.nik_karyawan', 'like', $like);
            });
        }

        $rows = $query
            ->orderByDesc('st.tanggal_terbit')
            ->orderByDesc('st.id')
            ->limit(100)
            ->get([
                'st.id',
                'st.nomor_surat',
                'st.perihal',
                'st.status',
                'st.tanggal_terbit',
                'st.karyawan_id',
                'mk.nama_lengkap',
                'mk.nik_karyawan',
            ]);

        $rowIds = $rows->pluck('id')->map(static fn ($v) => (int) $v)->all();
        if ($selectedId > 0 && !in_array($selectedId, $rowIds, true)) {
            $extra = DB::table('surat_teguran_disipliner as st')
                ->join('master_karyawan as mk', 'mk.id', '=', 'st.karyawan_id')
                ->where('st.id', $selectedId)
                ->where('st.is_active', true)
                ->first([
                    'st.id',
                    'st.nomor_surat',
                    'st.perihal',
                    'st.status',
                    'st.tanggal_terbit',
                    'st.karyawan_id',
                    'mk.nama_lengkap',
                    'mk.nik_karyawan',
                ]);
            if ($extra !== null) {
                $rows->prepend($extra);
            }
        }

        $data = $rows->map(function ($row) {
            $nama = trim((string) ($row->nama_lengkap ?? ''));
            $nik = trim((string) ($row->nik_karyawan ?? ''));
            $pegawai = $nik !== '' ? ($nik . ' — ' . $nama) : $nama;
            $nomor = trim((string) ($row->nomor_surat ?? ''));

            return [
                'id' => (int) $row->id,
                'nomor_surat' => $nomor,
                'perihal' => (string) ($row->perihal ?? ''),
                'status' => (string) ($row->status ?? ''),
                'tanggal_terbit' => $row->tanggal_terbit,
                'karyawan_id' => (int) ($row->karyawan_id ?? 0),
                'nama_karyawan' => $nama,
                'text' => trim($nomor . ' — ' . $pegawai . ' [' . strtoupper((string) $row->status) . ']'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Opsi surat peringatan / SP (G.1).
     */
    public function listSuratPeringatanOptions(Request $request)
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return response()->json(['success' => true, 'data' => []], 200);
        }

        $investigasiId = (int) $request->input('investigasi_id', 0);
        $search = trim((string) $request->input('q', ''));
        $karyawanIds = $this->resolveInvestigasiKaryawanIds($investigasiId);
        $selectedId = (int) $request->input('selected_id', 0);

        $query = DB::table('surat_peringatan_disipliner as sp')
            ->join('master_karyawan as mk', 'mk.id', '=', 'sp.karyawan_id')
            ->where('sp.is_active', true)
            ->where('mk.is_active', true)
            ->whereIn('sp.status', ['draft', 'issued']);

        if ($karyawanIds !== []) {
            $query->whereIn('sp.karyawan_id', $karyawanIds);
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('sp.nomor_surat', 'like', $like)
                    ->orWhere('sp.jenis_peringatan', 'like', $like)
                    ->orWhere('mk.nama_lengkap', 'like', $like)
                    ->orWhere('mk.nik_karyawan', 'like', $like);
            });
        }

        $rows = $query
            ->orderByDesc('sp.tanggal_terbit')
            ->orderByDesc('sp.id')
            ->limit(100)
            ->get([
                'sp.id',
                'sp.nomor_surat',
                'sp.jenis_peringatan',
                'sp.status',
                'sp.tanggal_terbit',
                'sp.karyawan_id',
                'mk.nama_lengkap',
                'mk.nik_karyawan',
            ]);

        $rowIds = $rows->pluck('id')->map(static fn ($v) => (int) $v)->all();
        if ($selectedId > 0 && !in_array($selectedId, $rowIds, true)) {
            $extra = DB::table('surat_peringatan_disipliner as sp')
                ->join('master_karyawan as mk', 'mk.id', '=', 'sp.karyawan_id')
                ->where('sp.id', $selectedId)
                ->where('sp.is_active', true)
                ->first([
                    'sp.id',
                    'sp.nomor_surat',
                    'sp.jenis_peringatan',
                    'sp.status',
                    'sp.tanggal_terbit',
                    'sp.karyawan_id',
                    'mk.nama_lengkap',
                    'mk.nik_karyawan',
                ]);
            if ($extra !== null) {
                $rows->prepend($extra);
            }
        }

        $data = $rows->map(function ($row) {
            $nama = trim((string) ($row->nama_lengkap ?? ''));
            $nik = trim((string) ($row->nik_karyawan ?? ''));
            $pegawai = $nik !== '' ? ($nik . ' — ' . $nama) : $nama;
            $nomor = trim((string) ($row->nomor_surat ?? ''));
            $jenis = trim((string) ($row->jenis_peringatan ?? ''));

            return [
                'id' => (int) $row->id,
                'nomor_surat' => $nomor,
                'jenis_peringatan' => $jenis,
                'status' => (string) ($row->status ?? ''),
                'tanggal_terbit' => $row->tanggal_terbit,
                'karyawan_id' => (int) ($row->karyawan_id ?? 0),
                'nama_karyawan' => $nama,
                'text' => trim(($jenis !== '' ? $jenis . ' — ' : '') . $nomor . ' — ' . $pegawai . ' [' . strtoupper((string) $row->status) . ']'),
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data], 200);
    }

    /**
     * Opsi multi-select No BAK untuk form Investigasi Awal (F1).
     * Response shape sama persis dengan implementasi sebelumnya di BAK controller.
     */
    public function listForInvestigasi(Request $request)
    {
        try {
            $investigasiId = (int) $request->input('investigasi_id', 0);
            $query = $this->bakOptionsQuery($investigasiId)->whereIn('bak.status', [
                BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
            ]);

            $periode = (int) $request->input('periode', 0);
            if ($periode >= 2000) {
                $query->whereYear('bak.tanggal_kejadian', $periode);
            }

            $search = trim((string) $request->input('q', ''));
            if ($search !== '') {
                $query->where(function ($q) use ($search) {
                    $q->where('bak.nomor_surat', 'like', '%' . $search . '%')
                        ->orWhere('bak.created_by', 'like', '%' . $search . '%')
                        ->orWhere('bak.perihal', 'like', '%' . $search . '%');
                });
            }

            $collection = $query
                ->orderByDesc('bak.tanggal_kejadian')
                ->orderByDesc('bak.id')
                ->limit(500)
                ->get();

            $creatorNames = $collection
                ->map(fn ($row) => trim((string) ($row->created_by ?? '')))
                ->filter()
                ->unique()
                ->values()
                ->all();

            $divisiByCreator = [];
            if (count($creatorNames) > 0) {
                $employees = MasterKaryawan::query()
                    ->from('master_karyawan as mk')
                    ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
                    ->where('mk.is_active', true)
                    ->whereIn('mk.nama_lengkap', $creatorNames)
                    ->select('mk.nama_lengkap', 'md.nama_divisi', 'mk.department')
                    ->get();

                foreach ($employees as $emp) {
                    $key = mb_strtolower(trim((string) ($emp->nama_lengkap ?? '')));
                    if ($key === '' || isset($divisiByCreator[$key])) {
                        continue;
                    }
                    $divisi = trim((string) ($emp->nama_divisi ?? ''));
                    if ($divisi === '') {
                        $divisi = trim((string) ($emp->department ?? ''));
                    }
                    $divisiByCreator[$key] = $divisi;
                }
            }

            $rows = $collection
                ->map(function ($row) use ($divisiByCreator) {
                    $dokumentasi = $this->decodeDokumentasiList($row->dokumentasi ?? null);
                    $tanggal = $row->tanggal_kejadian
                        ? Carbon::parse($row->tanggal_kejadian)->format('Y-m-d')
                        : null;
                    $createdAt = $row->created_at
                        ? Carbon::parse($row->created_at)->format('Y-m-d')
                        : null;
                    $createdBy = trim((string) ($row->created_by ?? ''));
                    $creatorKey = mb_strtolower($createdBy);

                    return [
                        'id' => (int) $row->id,
                        'nomor_surat' => (string) ($row->nomor_surat ?? ''),
                        'created_by' => $createdBy,
                        'created_by_divisi' => $divisiByCreator[$creatorKey] ?? '',
                        'perihal' => trim((string) ($row->perihal ?? '')),
                        'tanggal_kejadian' => $tanggal,
                        'created_at' => $createdAt,
                        'status' => (string) ($row->status ?? ''),
                        'dokumentasi' => $dokumentasi,
                        'jumlah_lampiran' => count($dokumentasi),
                        'has_document' => !empty($row->file_path),
                        'nama_berkas' => (string) ($row->nama_berkas ?? ''),
                        'file_path' => (string) ($row->file_path ?? ''),
                    ];
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => $rows,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    /**
     * Simpan Investigasi Awal baru — Case Code (no_document) digenerate di server.
     */
    public function store(Request $request)
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel investigasi belum tersedia. Jalankan migrasi database.',
            ], 503);
        }

        $this->validate($request, [
            'judul_kejadian' => 'required|string|max:500',
            'tanggal_kejadian' => 'required|date',
            'bak_ids' => 'required|array|min:1',
        ]);

        $input = $request->all();
        $actor = BakInvestigasiService::actorFromRequest($request);
        $input['created_by'] = trim((string) ($actor ?: $this->karyawan ?? '')) ?: null;
        $input['updated_by'] = $input['created_by'];

        $analisisErr = BakInvestigasiService::validateAnalisisInput($input);
        if ($analisisErr !== null) {
            return response()->json(['success' => false, 'message' => $analisisErr], 422);
        }

        try {
            DB::beginTransaction();

            $noDocument = BakInvestigasiService::generateUniqueNoDocument();
            $header = BakInvestigasiService::mapDraftHeaderUpdate($input);
            $header['no_document'] = $noDocument;
            $header['status'] = InvestigasiAwalStatus::DRAFT;
            $header['creator_karyawan_id'] = $this->user_id ?: null;
            $id = (int) DB::table('bak_investigasi')->insertGetId($header);

            app(InvestigasiPengesahanService::class)->ensurePengesahanShell($id);

            BakInvestigasiService::replacePihak($id, is_array($input['pihak'] ?? null) ? $input['pihak'] : []);
            BakInvestigasiService::replaceTindakLanjut(
                $id,
                is_array($input['tindak_lanjut'] ?? null) ? $input['tindak_lanjut'] : []
            );

            $bakIds = is_array($input['bak_ids'] ?? null) ? $input['bak_ids'] : [];
            BakLaporanService::syncInvestigasiLinks($id, $bakIds);

            DB::commit();

            $row = DB::table('bak_investigasi')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Investigasi awal tersimpan.',
                'data' => $this->formatInvestigasiRow($row),
            ], 201);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    /**
     * Update investigasi — no_document tidak boleh diubah dari client.
     */
    public function update(Request $request)
    {
        if (!Schema::hasTable('bak_investigasi')) {
            return response()->json([
                'success' => false,
                'message' => 'Tabel investigasi belum tersedia. Jalankan migrasi database.',
            ], 503);
        }

        $this->validate($request, [
            'id' => 'required|integer',
            'judul_kejadian' => 'required|string|max:500',
            'tanggal_kejadian' => 'required|date',
        ]);

        $id = (int) $request->input('id');
        $existing = DB::table('bak_investigasi')->where('id', $id)->where('is_active', true)->first();
        if ($existing === null) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        if (($existing->status ?? '') !== InvestigasiAwalStatus::DRAFT) {
            return response()->json([
                'success' => false,
                'message' => 'Investigasi tidak dapat diubah setelah diajukan.',
            ], 403);
        }

        $input = $request->all();
        $input['updated_by'] = BakInvestigasiService::actorFromRequest($request);
        $input['status'] = InvestigasiAwalStatus::DRAFT;

        $analisisErr = BakInvestigasiService::validateAnalisisInput($input);
        if ($analisisErr !== null) {
            return response()->json(['success' => false, 'message' => $analisisErr], 422);
        }

        try {
            DB::beginTransaction();

            $header = BakInvestigasiService::mapDraftHeaderUpdate($input);
            unset($header['no_document'], $header['created_at'], $header['created_by'], $header['status']);
            $header['updated_at'] = Carbon::now();

            DB::table('bak_investigasi')->where('id', $id)->update($header);

            BakInvestigasiService::replacePihak($id, is_array($input['pihak'] ?? null) ? $input['pihak'] : []);
            BakInvestigasiService::replaceTindakLanjut(
                $id,
                is_array($input['tindak_lanjut'] ?? null) ? $input['tindak_lanjut'] : []
            );

            $bakIds = is_array($input['bak_ids'] ?? null) ? $input['bak_ids'] : [];
            BakLaporanService::syncInvestigasiLinks($id, $bakIds);

            DB::commit();

            $row = DB::table('bak_investigasi')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Investigasi awal diperbarui.',
                'data' => $this->formatInvestigasiRow($row),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function submit(Request $request)
    {
        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');

        $row = DB::table('bak_investigasi')->where('id', $id)->where('is_active', true)->first();
        if ($row === null) {
            return response()->json(['success' => false, 'message' => 'Data tidak ditemukan.'], 404);
        }

        if (($row->status ?? '') !== InvestigasiAwalStatus::DRAFT) {
            return response()->json(['success' => false, 'message' => 'Status tidak valid untuk pengajuan.'], 422);
        }

        $actor = $this->resolveActorKaryawan();
        if ($actor === null) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan.'], 403);
        }

        try {
            DB::beginTransaction();

            app(InvestigasiPengesahanService::class)->approveDisusun($id, $actor);

            DB::table('bak_investigasi')->where('id', $id)->update([
                'status' => InvestigasiAwalStatus::MENUNGGU_HASIL,
                'submitted_at' => Carbon::now(),
                'submitted_by' => $actor->nama_lengkap,
                'submitted_by_karyawan_id' => $actor->id,
                'updated_by' => $actor->nama_lengkap,
                'updated_at' => Carbon::now(),
            ]);

            BakLaporanService::syncBeritaAcaraProsesForInvestigasi($id);

            DB::commit();

            $fresh = DB::table('bak_investigasi')->where('id', $id)->first();

            return response()->json([
                'success' => true,
                'message' => 'Investigasi diajukan. Menunggu hasil (G) dari Legal.',
                'data' => $this->formatInvestigasiRow($fresh),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $ex->getMessage()], 500);
        }
    }

    public function saveHasilInvestigasi(Request $request)
    {
        if (!$this->viewerIsLegalManager($request)) {
            return response()->json(['success' => false, 'message' => 'Akses Legal diperlukan.'], 403);
        }

        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');
        $row = DB::table('bak_investigasi')->where('id', $id)->first();
        if ($row === null || ($row->status ?? '') !== InvestigasiAwalStatus::MENUNGGU_HASIL) {
            return response()->json(['success' => false, 'message' => 'Status tidak valid.'], 422);
        }

        $g1 = $request->all();
        $err = BakInvestigasiService::validateG1($g1);
        if ($err !== null) {
            return response()->json(['success' => false, 'message' => $err], 422);
        }

        BakInvestigasiService::applyG1Fields($id, $g1);

        return response()->json([
            'success' => true,
            'message' => 'Hasil investigasi (G.1) disimpan.',
            'data' => $this->formatInvestigasiRow(DB::table('bak_investigasi')->where('id', $id)->first()),
        ], 200);
    }

    public function approveLegal(Request $request)
    {
        if (!$this->viewerIsLegalManager($request)) {
            return response()->json(['success' => false, 'message' => 'Akses Legal diperlukan.'], 403);
        }

        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');

        $actor = $this->resolveActorKaryawan();
        if ($actor === null) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan.'], 403);
        }

        $g1 = $request->all();
        $err = BakInvestigasiService::validateG1($g1);
        if ($err !== null) {
            return response()->json(['success' => false, 'message' => $err], 422);
        }

        try {
            DB::beginTransaction();
            app(InvestigasiPengesahanService::class)->approveLegal($id, $actor, $g1);
            app(BakInvestigasiDocumentService::class)->issuePdf($id);
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $ex->getMessage()], 500);
        }

        $fresh = DB::table('bak_investigasi')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Disetujui Legal & Disciplinary Manager. Dokumen F1 diterbitkan.',
            'data' => $this->formatInvestigasiRow($fresh),
        ], 200);
    }

    public function approveDiketahui(Request $request)
    {
        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');
        $actor = $this->resolveActorKaryawan();
        if ($actor === null) {
            return response()->json(['success' => false, 'message' => 'Data karyawan tidak ditemukan.'], 403);
        }

        $pengesahan = app(InvestigasiPengesahanService::class);
        if (!$pengesahan->viewerCanApproveDiketahui($id, (int) $actor->id)) {
            return response()->json(['success' => false, 'message' => 'Anda tidak berhak menyetujui.'], 403);
        }

        try {
            DB::beginTransaction();
            $ok = $pengesahan->approveDiketahui($id, $actor);
            if (!$ok) {
                DB::rollBack();

                return response()->json(['success' => false, 'message' => 'Gagal menyetujui.'], 422);
            }

            $row = DB::table('bak_investigasi')->where('id', $id)->first();
            if (($row->status ?? '') === InvestigasiAwalStatus::ISSUED) {
                app(BakInvestigasiDocumentService::class)->issuePdf($id);
                DB::table('bak_investigasi')->where('id', $id)->update([
                    'published_by' => $actor->nama_lengkap,
                    'updated_at' => Carbon::now(),
                ]);
            }
            DB::commit();
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => $ex->getMessage()], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Pengesahan Diketahui berhasil.',
            'data' => $this->formatInvestigasiRow(DB::table('bak_investigasi')->where('id', $id)->first()),
        ], 200);
    }

    public function rejectPengesahan(Request $request)
    {
        $this->validate($request, [
            'id' => 'required|integer',
            'reason' => 'required|string|max:500',
        ]);

        if (!$this->viewerIsLegalManager($request)) {
            return response()->json(['success' => false, 'message' => 'Akses Legal diperlukan.'], 403);
        }

        $id = (int) $request->input('id');
        DB::table('bak_investigasi')->where('id', $id)->update([
            'status' => InvestigasiAwalStatus::DRAFT,
            'rejection_reason' => $request->input('reason'),
            'updated_at' => Carbon::now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Dikembalikan ke pembuat.'], 200);
    }

    public function issue(Request $request, BakInvestigasiDocumentService $documentService)
    {
        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');

        if (!$this->viewerIsLegalManager($request)) {
            return response()->json(['success' => false, 'message' => 'Akses Legal diperlukan.'], 403);
        }

        $pengesahan = app(InvestigasiPengesahanService::class);
        if (!$pengesahan->pengesahanComplete($id)) {
            return response()->json(['success' => false, 'message' => 'Pengesahan belum lengkap.'], 422);
        }

        DB::table('bak_investigasi')->where('id', $id)->update([
            'status' => InvestigasiAwalStatus::ISSUED,
            'published_at' => Carbon::now(),
            'published_by' => $this->karyawan,
            'updated_at' => Carbon::now(),
        ]);

        $file = $documentService->issuePdf($id);

        return response()->json([
            'success' => true,
            'message' => 'Dokumen F1 diterbitkan.',
            'data' => $this->formatInvestigasiRow(DB::table('bak_investigasi')->where('id', $id)->first()),
            'file_name' => $file['file_name'] ?? null,
        ], 200);
    }

    public function printInvestigasiPdf(Request $request, BakInvestigasiDocumentService $documentService)
    {
        $this->validate($request, ['id' => 'required|integer']);
        $id = (int) $request->input('id');

        try {
            $binary = $documentService->printPdfBinary($id);

            return response()->json([
                'success' => true,
                'data' => base64_encode($binary),
                'pdf_ready' => true,
                'file_name' => 'investigasi-awal.pdf',
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json(['success' => false, 'message' => $ex->getMessage()], 500);
        }
    }

    /**
     * Preview PDF BAK dari form Investigasi Awal (tanpa rantai visibilitas Pembuatan BAK).
     */
    public function printPdf(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        try {
            $this->validate($request, ['id' => 'required|integer']);

            $investigasiId = (int) $request->input('investigasi_id', 0);
            $record = $this->findBakForInvestigasiPreview($this->resolveId($request), $investigasiId);
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau BAK belum siap untuk investigasi.',
                ], 403);
            }

            $document = BeritaAcaraKejadianDisipliner::find($record->id);
            if ($document === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.',
                ], 404);
            }

            if (!empty($document->file_qr)) {
                GenerateQrDocumentBeritaAcaraKejadian::ensurePublicSvgForFile($document->file_qr);
            }

            $pdfBinary = $documentService->resolvePdfBinary($document, $document);
            $document->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF berita acara kejadian siap.',
                'data' => base64_encode($pdfBinary),
                'pdf_ready' => true,
                'file_name' => $document->nama_berkas ?: ($document->nomor_surat . '.pdf'),
                'file_path' => $document->file_path,
                'nomor_surat' => (string) ($document->nomor_surat ?? ''),
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    /**
     * Query ringan kolom BAK yang dibutuhkan opsi investigasi saja.
     */
    private function bakOptionsQuery(int $investigasiId = 0)
    {
        $linkedBakIds = [];
        if ($investigasiId > 0 && Schema::hasTable('bak_investigasi_berita_acara')) {
            $linkedBakIds = DB::table('bak_investigasi_berita_acara')
                ->where('bak_investigasi_id', $investigasiId)
                ->pluck('berita_acara_id')
                ->map(static fn ($id) => (int) $id)
                ->all();
        }

        $query = BeritaAcaraKejadianDisipliner::query()
            ->from('berita_acara_kejadian_disipliner as bak')
            ->where('bak.is_active', true);

        $query->where(function ($outer) use ($linkedBakIds) {
            $outer->where(function ($inner) {
                BakLaporanService::applyInvestigasiBakOptionsScope($inner, 'bak');
            });
            if ($linkedBakIds !== []) {
                $outer->orWhereIn('bak.id', $linkedBakIds);
            }
        });

        return $query
            ->select(
                'bak.id',
                'bak.nomor_surat',
                'bak.perihal',
                'bak.tanggal_kejadian',
                'bak.status',
                'bak.dokumentasi',
                'bak.nama_berkas',
                'bak.file_path',
                'bak.created_by',
                'bak.created_at'
            );
    }

    private function findBakForInvestigasiPreview(int $bakId, int $investigasiId = 0): ?BeritaAcaraKejadianDisipliner
    {
        if ($bakId <= 0) {
            return null;
        }

        if ($investigasiId > 0 && Schema::hasTable('bak_investigasi')) {
            $invActive = DB::table('bak_investigasi')
                ->where('id', $investigasiId)
                ->where('is_active', true)
                ->exists();
            if ($invActive) {
                $linked = $this->bakOptionsQuery($investigasiId)
                    ->where('bak.id', $bakId)
                    ->first();
                if ($linked !== null) {
                    return BeritaAcaraKejadianDisipliner::query()
                        ->where('is_active', true)
                        ->where('id', (int) $linked->id)
                        ->first();
                }
            }
        }

        $query = BeritaAcaraKejadianDisipliner::query()
            ->where('is_active', true)
            ->where('id', $bakId)
            ->whereIn('proses_status', [
                BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES,
                BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES,
                BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES,
            ]);

        MenuDataAccessService::applyKaryawanGradeFilter(
            $query,
            Str::slug('Investigasi Awal'),
            'penandatangan',
        );

        return $query->first();
    }

    private function resolveId(Request $request): int
    {
        return (int) ($request->input('id') ?: $request->input('document_id') ?: 0);
    }

    /**
     * @param  list<int>  $investigasiIds
     * @return array<int, list<string>>
     */
    private function loadBakNomorByInvestigasiIds(array $investigasiIds): array
    {
        $investigasiIds = array_values(array_unique(array_filter(
            array_map('intval', $investigasiIds),
            static fn ($id) => $id > 0
        )));
        if ($investigasiIds === [] || !Schema::hasTable('bak_investigasi_berita_acara')) {
            return [];
        }

        $bakTable = 'berita_acara_kejadian_disipliner';
        if (!Schema::hasTable($bakTable)) {
            return [];
        }

        $rows = DB::table('bak_investigasi_berita_acara as link')
            ->join("{$bakTable} as bak", 'bak.id', '=', 'link.berita_acara_id')
            ->whereIn('link.bak_investigasi_id', $investigasiIds)
            ->orderBy('link.bak_investigasi_id')
            ->orderBy('bak.nomor_surat')
            ->get(['link.bak_investigasi_id', 'bak.nomor_surat']);

        $map = [];
        foreach ($rows as $linkRow) {
            $invId = (int) $linkRow->bak_investigasi_id;
            $nomor = trim((string) ($linkRow->nomor_surat ?? ''));
            if ($nomor === '') {
                continue;
            }
            $map[$invId][] = $nomor;
        }

        return $map;
    }

    /**
     * @param  array<int, list<string>>  $bakNomorByInvestigasi
     */
    private function resolveBakNomorList($row, array $bakNomorByInvestigasi): array
    {
        $id = (int) ($row->id ?? 0);
        $list = $id > 0 ? ($bakNomorByInvestigasi[$id] ?? []) : [];
        if ($list !== []) {
            return array_values(array_unique($list));
        }

        $first = trim((string) ($row->no_bak_pertama ?? ''));
        $last = trim((string) ($row->no_bak_terakhir ?? ''));
        if ($first !== '') {
            $list[] = $first;
        }
        if ($last !== '' && $last !== $first) {
            $list[] = $last;
        }

        return $list;
    }

    private function formatInvestigasiRow($row, array $creatorNamesById = [], array $bakNomorByInvestigasi = []): array
    {
        if ($row === null) {
            return [];
        }

        $id = (int) $row->id;
        $pihakCount = 0;
        if (Schema::hasTable('bak_investigasi_pihak')) {
            $pihakCount = (int) DB::table('bak_investigasi_pihak')->where('bak_investigasi_id', $id)->count();
        }

        $createdBy = trim((string) ($row->created_by ?? ''));
        if ($createdBy === '') {
            $creatorId = (int) ($row->creator_karyawan_id ?? 0);
            if ($creatorId > 0) {
                $createdBy = trim((string) ($creatorNamesById[$creatorId] ?? ''));
                if ($createdBy === '' && Schema::hasTable('master_karyawan')) {
                    $createdBy = trim((string) (MasterKaryawan::query()
                        ->where('id', $creatorId)
                        ->value('nama_lengkap') ?? ''));
                }
            }
        }

        return [
            'id' => $id,
            'no_document' => (string) ($row->no_document ?? ''),
            'status' => (string) ($row->status ?? InvestigasiAwalStatus::DRAFT),
            'status_label' => $this->statusLabel((string) ($row->status ?? '')),
            'status_case' => $row->status_case ?? null,
            'judul_kejadian' => (string) ($row->judul_kejadian ?? ''),
            'tanggal_kejadian' => $row->tanggal_kejadian
                ? Carbon::parse($row->tanggal_kejadian)->format('Y-m-d')
                : null,
            'no_bak_pertama' => (string) ($row->no_bak_pertama ?? ''),
            'bak_nomor_list' => $this->resolveBakNomorList($row, $bakNomorByInvestigasi),
            'rekomendasi_alur' => $row->rekomendasi_alur ?? null,
            'keputusan_sanksi' => $row->keputusan_sanksi ?? null,
            'catatan_tambahan' => $row->catatan_tambahan ?? null,
            'penyebab_label' => BakInvestigasiService::summarizePenyebabLabel($row),
            'dampak_label' => BakInvestigasiService::summarizeDampakLabel($row),
            'pihak_count' => $pihakCount,
            'created_by' => $createdBy,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
            'file_path' => $row->file_path ?? null,
            'can_approve_diketahui' => ((string) ($row->status ?? '')) === InvestigasiAwalStatus::MENUNGGU_PENGESAHAN
                && $this->user_id
                ? app(InvestigasiPengesahanService::class)->viewerCanApproveDiketahui(
                    $id,
                    (int) $this->user_id
                )
                : false,
        ];
    }

    private function statusLabel(string $status): string
    {
        switch ($status) {
            case InvestigasiAwalStatus::MENUNGGU_HASIL:
                return 'Menunggu Hasil (G)';
            case InvestigasiAwalStatus::MENUNGGU_PENGESAHAN:
                return 'Menunggu Diketahui';
            case InvestigasiAwalStatus::ISSUED:
                return 'Terbit';
            case InvestigasiAwalStatus::CANCELLED:
                return 'Dibatalkan';
            default:
                return 'Draft';
        }
    }

    private function resolveActorKaryawan(): ?MasterKaryawan
    {
        if (!$this->user_id) {
            return null;
        }

        return MasterKaryawan::query()
            ->where('id', (int) $this->user_id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Karyawan terlapor (BAK terkait + pihak terlapor) untuk filter opsi ST / SP.
     *
     * @return list<int>
     */
    private function resolveInvestigasiKaryawanIds(int $investigasiId): array
    {
        if ($investigasiId <= 0) {
            return [];
        }

        $ids = [];

        if (
            Schema::hasTable('bak_investigasi_berita_acara')
            && Schema::hasTable('berita_acara_kejadian_disipliner')
        ) {
            $bakIds = DB::table('bak_investigasi_berita_acara')
                ->where('bak_investigasi_id', $investigasiId)
                ->pluck('berita_acara_id')
                ->map(static fn ($v) => (int) $v)
                ->filter(static fn ($v) => $v > 0)
                ->values()
                ->all();

            if ($bakIds !== []) {
                $fromBak = DB::table('berita_acara_kejadian_disipliner')
                    ->whereIn('id', $bakIds)
                    ->where('is_active', true)
                    ->pluck('karyawan_id')
                    ->map(static fn ($v) => (int) $v)
                    ->filter(static fn ($v) => $v > 0)
                    ->all();
                $ids = array_merge($ids, $fromBak);
            }
        }

        if (Schema::hasTable('bak_investigasi_pihak') && Schema::hasTable('master_karyawan')) {
            $names = DB::table('bak_investigasi_pihak')
                ->where('bak_investigasi_id', $investigasiId)
                ->where('peran', 'terlapor')
                ->pluck('nama');

            foreach ($names as $name) {
                $name = trim((string) $name);
                if ($name === '') {
                    continue;
                }

                $found = MasterKaryawan::query()
                    ->where('is_active', true)
                    ->where('nama_lengkap', $name)
                    ->value('id');

                if ($found) {
                    $ids[] = (int) $found;
                }
            }
        }

        return array_values(array_unique(array_filter($ids, static fn ($id) => $id > 0)));
    }

    private function viewerIsLegalManager(Request $request): bool
    {
        $jabatan = null;
        $idJabatan = null;

        if ($request->attributes->has('user')) {
            $karyawan = $request->attributes->get('user')->karyawan ?? null;
            if ($karyawan !== null) {
                $jabatan = $karyawan->jabatan ?? null;
                $idJabatan = $karyawan->id_jabatan ?? null;
            }
        }

        if (($jabatan === null || $jabatan === '') && $this->user_id) {
            $row = MasterKaryawan::query()
                ->where('id', (int) $this->user_id)
                ->first(['jabatan', 'id_jabatan']);
            if ($row !== null) {
                $jabatan = $row->jabatan;
                $idJabatan = $row->id_jabatan;
            }
        }

        return InvestigasiAccessService::viewerCanActAsLegalManager(
            $this->grade,
            $jabatan,
            $idJabatan
        );
    }

    /**
     * BAK terhubung (nomor + lampiran) untuk preview di detail / G.1.
     *
     * @return list<array<string, mixed>>
     */
    private function loadLinkedBakRowsForInvestigasi(int $investigasiId): array
    {
        if ($investigasiId <= 0 || !Schema::hasTable('bak_investigasi_berita_acara')) {
            return [];
        }

        $bakTable = 'berita_acara_kejadian_disipliner';
        if (!Schema::hasTable($bakTable)) {
            return [];
        }

        $rows = DB::table('bak_investigasi_berita_acara as link')
            ->join("{$bakTable} as bak", 'bak.id', '=', 'link.berita_acara_id')
            ->where('link.bak_investigasi_id', $investigasiId)
            ->orderBy('bak.nomor_surat')
            ->get([
                'bak.id',
                'bak.nomor_surat',
                'bak.dokumentasi',
                'bak.perihal',
                'bak.created_by',
                'bak.file_path',
            ]);

        return $rows->map(function ($row) {
            $dokumentasi = $this->decodeDokumentasiList($row->dokumentasi ?? null);

            return [
                'id' => (int) $row->id,
                'nomor_surat' => (string) ($row->nomor_surat ?? ''),
                'perihal' => trim((string) ($row->perihal ?? '')),
                'created_by' => trim((string) ($row->created_by ?? '')),
                'dokumentasi' => $dokumentasi,
                'jumlah_lampiran' => count($dokumentasi),
                'has_document' => !empty($row->file_path),
            ];
        })->values()->all();
    }

    private function decodeDokumentasiList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $decoded = json_decode((string) $value, true);
            $items = is_array($decoded) ? $decoded : [];
        }

        $paths = [];
        foreach ($items as $item) {
            $path = str_replace('\\', '/', trim((string) $item));
            if ($path === '' || strpos($path, '..') !== false) {
                continue;
            }
            $paths[] = $path;
        }

        return array_values(array_unique($paths));
    }
}
