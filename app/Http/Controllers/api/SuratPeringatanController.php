<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\SuratPeringatanDisipliner;
use App\Services\GenerateQrDocumentSuratPeringatan;
use App\Services\DisiplinerPdfFixingService;
use App\Services\SuratPeringatanDocumentService;
use App\Support\QrDocumentsStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class SuratPeringatanController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery();

            $periode = (int) $request->input('periode', date('Y'));
            if ($periode >= 2000) {
                $query->whereYear('spd.tanggal_terbit', $periode);
            }

            $jenis = $this->resolveJenisPeringatan($request);
            if ($jenis !== '') {
                $query->where('spd.jenis_peringatan', $jenis);
            }

            $workflowTab = strtolower(trim((string) $request->input('workflow_tab', 'pembuatan')));
            if ($workflowTab === 'pengesahan') {
                $query->where('spd.status', SuratPeringatanDisipliner::STATUS_DRAFT);
            } else {
                $query->whereIn('spd.status', [
                    SuratPeringatanDisipliner::STATUS_DRAFT,
                    SuratPeringatanDisipliner::STATUS_ISSUED,
                ]);
            }

            if ($request->filled('status')) {
                $query->where('spd.status', strtolower(trim((string) $request->status)));
            }

            $data = $query->get()->map(fn ($row) => $this->decorate($row));

            return Datatables::of($data)->make(true);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->decorate($record),
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function employeeHistory(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawanId = (int) $request->karyawan_id;

        $items = $this->baseQuery()
            ->where('spd.karyawan_id', $karyawanId)
            ->orderByDesc('spd.tanggal_terbit')
            ->orderByDesc('spd.id')
            ->get()
            ->map(fn ($row) => $this->decorate($row));

        return response()->json([
            'success' => true,
            'data' => $items,
            'suggested_type' => app(SuratPeringatanDocumentService::class)->suggestJenisPeringatan($karyawanId),
        ], 200);
    }

    public function tabCounts(Request $request)
    {
        $periode = (int) $request->input('periode', date('Y'));

        $base = SuratPeringatanDisipliner::query()
            ->where('is_active', true);

        if ($periode >= 2000) {
            $base->whereYear('tanggal_terbit', $periode);
        }

        $pembuatan = (clone $base)->whereIn('status', [
            SuratPeringatanDisipliner::STATUS_DRAFT,
            SuratPeringatanDisipliner::STATUS_ISSUED,
        ])->count();

        $pengesahan = (clone $base)
            ->where('status', SuratPeringatanDisipliner::STATUS_DRAFT)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pembuatan' => $pembuatan,
                'pengesahan' => $pengesahan,
            ],
        ], 200);
    }

    public function suggestType(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawanId = (int) $request->karyawan_id;
        $service = app(SuratPeringatanDocumentService::class);
        $suggested = $service->suggestJenisPeringatan($karyawanId);

        return response()->json([
            'success' => true,
            'data' => [
                'suggested_type' => $suggested,
                'id_surat_sebelumnya' => $service->resolveIdSuratSebelumnya($karyawanId, $suggested),
            ],
        ], 200);
    }

    public function store(Request $request, SuratPeringatanDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();
                return $validation;
            }

            $karyawanId = (int) $request->karyawan_id;
            if (!$this->employeeExists($karyawanId)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $jenisPeringatan = $this->resolveJenisPeringatan($request);
            $documentService->assertCanIssue($jenisPeringatan, $karyawanId);

            $tanggalTerbit = Carbon::parse($this->resolveTanggalTerbit($request))->format('Y-m-d');
            $nomorData = $this->resolveNomorSuratData($request, $tanggalTerbit);

            $idSuratSebelumnya = $request->filled('id_surat_sebelumnya')
                ? (int) $request->id_surat_sebelumnya
                : $documentService->resolveIdSuratSebelumnya($karyawanId, $jenisPeringatan);

            $now = Carbon::now();

            $letter = SuratPeringatanDisipliner::create([
                'karyawan_id' => $karyawanId,
                'jenis_peringatan' => $jenisPeringatan,
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'tanggal_terbit' => $tanggalTerbit,
                'tanggal_kejadian' => $request->filled('tanggal_kejadian')
                    ? Carbon::parse($request->tanggal_kejadian)->format('Y-m-d')
                    : null,
                'jenis_pelanggaran' => trim((string) ($request->jenis_pelanggaran ?? $request->violation_category ?? '')) ?: null,
                'uraian_pelanggaran' => trim((string) $this->resolveUraianPelanggaran($request)) ?: null,
                'catatan_hrd' => trim((string) ($request->catatan_hrd ?? '')) ?: null,
                'bentuk_sanksi' => trim((string) ($request->bentuk_sanksi ?? '')) ?: null,
                'masa_berlaku_bulan' => $this->resolveMasaBerlakuBulan($request, $jenisPeringatan),
                'tanggal_berlaku_mulai' => $this->resolveTanggalBerlakuMulai($request, $tanggalTerbit),
                'tanggal_berlaku_selesai' => $this->resolveTanggalBerlakuSelesai(
                    $request,
                    $tanggalTerbit,
                    $jenisPeringatan
                ),
                'id_surat_sebelumnya' => $idSuratSebelumnya,
                'status' => SuratPeringatanDisipliner::STATUS_DRAFT,
                'created_by' => $this->karyawan,
                'is_active' => true,
                'created_at' => $now,
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil dibuat. Menunggu pengesahan.',
                'data' => $this->decorate($this->findActiveRecord($letter->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, SuratPeringatanDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $this->validate($request, [
                'id' => 'required|integer',
            ]);

            $letter = SuratPeringatanDisipliner::query()
                ->where('id', (int) $request->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            if ($letter->status !== SuratPeringatanDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draf yang belum disahkan yang dapat diubah.',
                ], 422);
            }

            $validation = $this->validatePayload($request, (int) $letter->id);
            if ($validation !== true) {
                DB::rollBack();

                return $validation;
            }

            $karyawanId = (int) $request->karyawan_id;
            if (!$this->employeeExists($karyawanId)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $jenisPeringatan = $this->resolveJenisPeringatan($request);
            $documentService->assertCanIssue($jenisPeringatan, $karyawanId, $letter->id);

            $tanggalTerbit = Carbon::parse($this->resolveTanggalTerbit($request))->format('Y-m-d');
            $nomorData = $this->resolveNomorSuratData($request, $tanggalTerbit);

            $idSuratSebelumnya = $request->filled('id_surat_sebelumnya')
                ? (int) $request->id_surat_sebelumnya
                : $documentService->resolveIdSuratSebelumnya($karyawanId, $jenisPeringatan);

            $now = Carbon::now();

            $letter->fill([
                'karyawan_id' => $karyawanId,
                'jenis_peringatan' => $jenisPeringatan,
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'tanggal_terbit' => $tanggalTerbit,
                'tanggal_kejadian' => $request->filled('tanggal_kejadian')
                    ? Carbon::parse($request->tanggal_kejadian)->format('Y-m-d')
                    : null,
                'jenis_pelanggaran' => trim((string) ($request->jenis_pelanggaran ?? $request->violation_category ?? '')) ?: null,
                'uraian_pelanggaran' => trim((string) $this->resolveUraianPelanggaran($request)) ?: null,
                'catatan_hrd' => trim((string) ($request->catatan_hrd ?? '')) ?: null,
                'bentuk_sanksi' => trim((string) ($request->bentuk_sanksi ?? '')) ?: null,
                'masa_berlaku_bulan' => $this->resolveMasaBerlakuBulan($request, $jenisPeringatan),
                'tanggal_berlaku_mulai' => $this->resolveTanggalBerlakuMulai($request, $tanggalTerbit),
                'tanggal_berlaku_selesai' => $this->resolveTanggalBerlakuSelesai(
                    $request,
                    $tanggalTerbit,
                    $jenisPeringatan
                ),
                'id_surat_sebelumnya' => $idSuratSebelumnya,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);
            $letter->save();

            $documentService->invalidateStoredPdf($letter);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil diperbarui.',
                'data' => $this->decorate($this->findActiveRecord($letter->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function approve(
        Request $request,
        SuratPeringatanDocumentService $documentService,
        GenerateQrDocumentSuratPeringatan $qrService
    ) {
        DB::beginTransaction();

        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $letter = SuratPeringatanDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null || $letter->status !== SuratPeringatanDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak dapat disahkan (bukan status draf).',
                ], 422);
            }

            $qrFile = $qrService->sync($letter, $record, $this->karyawan);

            $svgPath = QrDocumentsStorage::svgPath($qrFile);
            if (!is_file($svgPath)) {
                throw new \RuntimeException('QR tidak tersimpan di: ' . $svgPath);
            }

            $letter->file_qr = $qrFile;
            $letter->status = SuratPeringatanDisipliner::STATUS_ISSUED;
            $letter->published_by = $this->karyawan;
            $letter->updated_by = $this->karyawan;
            $letter->updated_at = Carbon::now();
            $letter->save();
            $letter->refresh();

            $documentService->invalidateStoredPdf($letter);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil disahkan.',
                'data' => $this->decorate($this->findActiveRecord($letter->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function repairQr(
        Request $request,
        GenerateQrDocumentSuratPeringatan $qrService,
        SuratPeringatanDocumentService $documentService
    ) {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $letter = SuratPeringatanDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $linked = trim((string) ($letter->file_qr ?? ''));
            if ($linked !== '' && GenerateQrDocumentSuratPeringatan::ensurePublicSvgForFile($linked)) {
                $svgPath = QrDocumentsStorage::svgPath($linked);

                return response()->json([
                    'success' => true,
                    'message' => 'QR sudah ada; tidak dibuat ulang.',
                    'data' => [
                        'file_qr' => $linked,
                        'svg_path' => $svgPath,
                    ],
                ], 200);
            }

            $qrFile = $qrService->sync($letter, $record, $this->karyawan);
            $svgPath = QrDocumentsStorage::svgPath($qrFile);

            if (!is_file($svgPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR gagal disimpan ke: ' . $svgPath,
                ], 422);
            }

            $letter->file_qr = $qrFile;
            $letter->save();
            $documentService->invalidateStoredPdf($letter);

            return response()->json([
                'success' => true,
                'message' => 'QR berhasil disimpan.',
                'data' => [
                    'file_qr' => $qrFile,
                    'svg_path' => $svgPath,
                ],
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function destroy(Request $request)
    {
        DB::beginTransaction();

        try {
            $record = SuratPeringatanDisipliner::query()
                ->where('id', $this->resolveId($request))
                ->where('is_active', true)
                ->first();

            if ($record === null) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            if ($record->status !== SuratPeringatanDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draf yang belum disahkan yang dapat dihapus.',
                ], 422);
            }

            $now = Carbon::now();
            $record->update([
                'is_active' => false,
                'status' => SuratPeringatanDisipliner::STATUS_CANCELLED,
                'updated_by' => $this->karyawan,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat peringatan berhasil dihapus.',
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function rerenderPdf(Request $request, DisiplinerPdfFixingService $fixingService)
    {
        try {
            $this->validate($request, [
                'id' => 'required|integer',
            ]);

            $data = $fixingService->regenerate(DisiplinerPdfFixingService::JENIS_SP, (int) $request->id);

            return response()->json([
                'success' => true,
                'message' => 'PDF surat peringatan berhasil di-render ulang.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function printPdf(Request $request, SuratPeringatanDocumentService $documentService)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat peringatan tidak ditemukan.',
                ], 404);
            }

            $letter = SuratPeringatanDisipliner::with('suratSebelumnya')->find($record->id);

            if (!empty($letter->file_qr)) {
                GenerateQrDocumentSuratPeringatan::ensurePublicSvgForFile($letter->file_qr);
            }

            $letter->load('suratSebelumnya');
            $pdfBinary = $documentService->resolvePdfBinary($letter, $record);
            $letter->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF surat peringatan siap.',
                'data' => base64_encode($pdfBinary),
                'pdf_ready' => true,
                'file_name' => $letter->nama_berkas ?: ($letter->nomor_surat . '.pdf'),
                'file_path' => $letter->file_path,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    private function baseQuery()
    {
        return SuratPeringatanDisipliner::query()
            ->from('surat_peringatan_disipliner as spd')
            ->leftJoin('master_karyawan as mk', 'spd.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('spd.is_active', true)
            ->select(
                'spd.id',
                'spd.karyawan_id',
                'spd.jenis_peringatan',
                'spd.nomor_surat',
                'spd.tanggal_terbit',
                'spd.tanggal_kejadian',
                'spd.jenis_pelanggaran',
                'spd.uraian_pelanggaran',
                'spd.catatan_hrd',
                'spd.bentuk_sanksi',
                'spd.masa_berlaku_bulan',
                'spd.tanggal_berlaku_mulai',
                'spd.tanggal_berlaku_selesai',
                'spd.id_surat_sebelumnya',
                'spd.status',
                'spd.nama_berkas',
                'spd.file_path',
                'spd.file_size',
                'spd.file_qr',
                'spd.created_by',
                'spd.updated_by',
                'spd.published_by',
                'spd.created_at',
                'spd.updated_at',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.department',
                'mk.jabatan',
                'md.nama_divisi'
            );
    }

    private function findActiveRecord($id)
    {
        if ((int) $id <= 0) {
            return null;
        }

        return $this->baseQuery()->where('spd.id', (int) $id)->first();
    }

    private function decorate($item)
    {
        if ($item === null) {
            return null;
        }

        $item->nama_karyawan = $item->nama_lengkap ?: '-';
        $item->divisi = $item->nama_divisi ?: ($item->department ?: '-');
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->tanggal_terbit_label = $item->tanggal_terbit
            ? Carbon::parse($item->tanggal_terbit)->format('d-m-Y')
            : '-';
        $item->tanggal_kejadian_label = $item->tanggal_kejadian
            ? Carbon::parse($item->tanggal_kejadian)->format('d-m-Y')
            : '-';
        $item->tanggal_berlaku_mulai_label = !empty($item->tanggal_berlaku_mulai)
            ? Carbon::parse($item->tanggal_berlaku_mulai)->format('d-m-Y')
            : '-';
        $item->tanggal_berlaku_selesai_label = !empty($item->tanggal_berlaku_selesai)
            ? Carbon::parse($item->tanggal_berlaku_selesai)->format('d-m-Y')
            : '-';
        $item->has_document = !empty($item->file_path);
        $item->has_qr = !empty($item->file_qr);
        $item->can_approve = $item->status === SuratPeringatanDisipliner::STATUS_DRAFT;
        $item->jenis_peringatan_label = $this->mapJenisPeringatanLabel($item->jenis_peringatan);
        $item->dibuat_oleh_label = trim((string) ($item->created_by ?? '')) ?: '-';
        $updatedBy = trim((string) ($item->updated_by ?? ''));
        $item->was_edited = $updatedBy !== '';
        $item->diubah_oleh_label = $item->was_edited ? $updatedBy : '-';
        $item->diterbitkan_oleh_label = trim((string) ($item->published_by ?? '')) ?: '-';
        $item->created_at_label = $item->created_at
            ? Carbon::parse($item->created_at)->format('d-m-Y H:i')
            : '-';
        $item->updated_at_label = $item->was_edited && $item->updated_at
            ? Carbon::parse($item->updated_at)->format('d-m-Y H:i')
            : '-';

        return $item;
    }

    private function mapJenisPeringatanLabel(?string $type): string
    {
        $map = [
            SuratPeringatanDisipliner::TYPE_SP1 => 'Surat Peringatan 1 (SP1)',
            SuratPeringatanDisipliner::TYPE_SP2 => 'Surat Peringatan 2 (SP2)',
            SuratPeringatanDisipliner::TYPE_SP3 => 'Surat Peringatan 3 (SP3)',
        ];

        return $map[strtoupper(trim((string) $type))] ?? (string) $type;
    }

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            SuratPeringatanDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            SuratPeringatanDisipliner::STATUS_ISSUED => 'Terbit',
            SuratPeringatanDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function validatePayload(Request $request, ?int $excludeLetterId = null)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
            'issue_date' => 'nullable|date',
            'tanggal_terbit' => 'nullable|date',
            'tanggal_kejadian' => 'nullable|date',
            'incident_date' => 'nullable|date',
            'jenis_pelanggaran' => 'nullable|string|max:150',
            'violation_category' => 'nullable|string|max:150',
            'uraian_pelanggaran' => 'nullable|string',
            'violation_description' => 'nullable|string',
            'catatan_hrd' => 'nullable|string',
            'bentuk_sanksi' => 'nullable|string|max:2000',
            'masa_berlaku_bulan' => 'nullable|integer|min:1|max:60',
            'tanggal_berlaku_mulai' => 'nullable|date',
            'tanggal_berlaku_selesai' => 'nullable|date',
            'nomor_surat' => 'nullable|string|max:100',
            'document_number' => 'nullable|string|max:100',
        ]);

        $jenis = $this->resolveJenisPeringatan($request);
        if (!in_array($jenis, [
            SuratPeringatanDisipliner::TYPE_SP1,
            SuratPeringatanDisipliner::TYPE_SP2,
            SuratPeringatanDisipliner::TYPE_SP3,
        ], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Jenis peringatan wajib dipilih (SP1, SP2, atau SP3).',
            ], 422);
        }

        $plainDescription = trim(strip_tags((string) $this->resolveUraianPelanggaran($request)));
        if ($plainDescription === '') {
            return response()->json([
                'success' => false,
                'message' => 'Uraian pelanggaran wajib diisi.',
            ], 422);
        }

        if ($this->resolveTanggalTerbit($request) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal terbit wajib diisi.',
            ], 422);
        }

        $sanksi = trim((string) ($request->bentuk_sanksi ?? ''));
        if ($sanksi === '') {
            return response()->json([
                'success' => false,
                'message' => 'Bentuk sanksi wajib diisi.',
            ], 422);
        }

        $nomorSurat = trim((string) ($request->nomor_surat ?? $request->document_number ?? ''));
        if ($nomorSurat === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor surat wajib diisi.',
            ], 422);
        }

        $duplicateQuery = SuratPeringatanDisipliner::query()
            ->where('is_active', true)
            ->where('nomor_surat', $nomorSurat);
        if ($excludeLetterId !== null) {
            $duplicateQuery->where('id', '!=', $excludeLetterId);
        }
        if ($duplicateQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor surat sudah digunakan. Gunakan nomor lain.',
            ], 422);
        }

        return true;
    }

    private function resolveJenisPeringatan(Request $request): string
    {
        $value = $request->jenis_peringatan ?? $request->warning_type ?? '';

        return strtoupper(trim((string) $value));
    }

    private function resolveTanggalTerbit(Request $request): string
    {
        return trim((string) ($request->tanggal_terbit ?? $request->issue_date ?? ''));
    }

    private function resolveUraianPelanggaran(Request $request): string
    {
        return (string) ($request->uraian_pelanggaran ?? $request->violation_description ?? '');
    }

    private function resolveNomorSuratData(Request $request, string $tanggalTerbit): array
    {
        $nomorSurat = trim((string) ($request->nomor_surat ?? $request->document_number ?? ''));

        $year = (int) date('Y', strtotime($tanggalTerbit));
        $urutan = 0;
        if (preg_match('/(\d{1,6})$/', $nomorSurat, $matches)) {
            $urutan = (int) $matches[1];
        }

        return [
            'nomor_surat' => $nomorSurat,
            'tahun_nomor' => $year,
            'urutan_nomor' => max(0, $urutan),
        ];
    }

    private function resolveMasaBerlakuBulan(Request $request, string $jenisPeringatan): int
    {
        if ($request->filled('masa_berlaku_bulan')) {
            return (int) $request->masa_berlaku_bulan;
        }

        return SuratPeringatanDocumentService::defaultMasaBerlakuBulan($jenisPeringatan);
    }

    private function resolveTanggalBerlakuMulai(Request $request, string $tanggalTerbit): string
    {
        if ($request->filled('tanggal_berlaku_mulai')) {
            return Carbon::parse($request->tanggal_berlaku_mulai)->format('Y-m-d');
        }

        return $tanggalTerbit;
    }

    private function resolveTanggalBerlakuSelesai(
        Request $request,
        string $tanggalTerbit,
        string $jenisPeringatan
    ): string {
        if ($request->filled('tanggal_berlaku_selesai')) {
            return Carbon::parse($request->tanggal_berlaku_selesai)->format('Y-m-d');
        }

        $months = $this->resolveMasaBerlakuBulan($request, $jenisPeringatan);
        $start = $request->filled('tanggal_berlaku_mulai')
            ? Carbon::parse($request->tanggal_berlaku_mulai)
            : Carbon::parse($tanggalTerbit);

        return $start->copy()->addMonthsNoOverflow($months)->format('Y-m-d');
    }

    private function employeeExists(int $karyawanId): bool
    {
        return MasterKaryawan::query()
            ->where('id', $karyawanId)
            ->where('is_active', true)
            ->exists();
    }

    private function resolveId(Request $request): int
    {
        return (int) ($request->input('id') ?: $request->input('letter_id') ?: 0);
    }
}
