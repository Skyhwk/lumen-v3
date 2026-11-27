<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\BakAtasanWorkflowService;
use App\Services\BakDocumentVisibilityService;
use App\Services\BakKejadianTimelineService;
use App\Services\BakLaporanService;
use App\Services\BeritaAcaraKejadianDocumentService;
use App\Services\BeritaAcaraKejadianLampiranConvertService;
use App\Services\DisiplinerPdfFixingService;
use App\Services\GenerateQrDocumentBeritaAcaraKejadian;
use App\Support\BeritaAcaraKejadianDampak;
use App\Support\BeritaAcaraKejadianDokumentasiStorage;
use App\Support\QrDocumentsStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class BeritaAcaraKejadianController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery();
            $this->applyVisibilityScope($query);

            $periode = (int) $request->input('periode', date('Y'));
            if ($periode >= 2000) {
                $query->whereYear('bak.tanggal_kejadian', $periode);
            }

            $workflowTab = strtolower(trim((string) $request->input('workflow_tab', 'permintaan_dokumen')));
            $this->applyWorkflowTabFilter($query, $workflowTab);

            if ($request->filled('status')) {
                $query->where('bak.status', strtolower(trim((string) $request->status)));
            }

            if ($request->filled('proses_status')) {
                $query->where('bak.proses_status', strtolower(trim((string) $request->proses_status)));
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
            $record = $this->findVisibleRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Anda tidak memiliki akses untuk melihat BAK ini.',
                ], 403);
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

    public function tabCounts(Request $request)
    {
        $periode = (int) $request->input('periode', date('Y'));

        $base = BeritaAcaraKejadianDisipliner::query()
            ->from('berita_acara_kejadian_disipliner as bak')
            ->where('bak.is_active', true);

        if ($periode >= 2000) {
            $base->whereYear('bak.tanggal_kejadian', $periode);
        }

        $visibility = app(BakDocumentVisibilityService::class);
        $visibility->applyListScope($base, (int) $this->user_id);

        $showTabCounts = $visibility->viewerShowsTabCounts((int) $this->user_id);

        return response()->json([
            'success' => true,
            'data' => [
                'show_tab_counts' => $showTabCounts,
                'permintaan_dokumen' => (clone $base)
                    ->where('bak.proses_status', BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN)
                    ->count(),
                'verifikasi_atasan' => (clone $base)
                    ->where('bak.proses_status', BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN)
                    ->count(),
                'menunggu_diproses' => (clone $base)
                    ->where('bak.proses_status', BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES)
                    ->count(),
                'sedang_diproses' => (clone $base)
                    ->where('bak.proses_status', BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES)
                    ->count(),
                'sudah_diproses' => (clone $base)
                    ->where('bak.proses_status', BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES)
                    ->count(),
            ],
        ], 200);
    }

    public function ajukan(Request $request, BakAtasanWorkflowService $workflow)
    {
        DB::beginTransaction();

        try {
            $document = $this->findDocumentForViewer($this->resolveId($request));

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            if ($document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya permintaan dokumen yang dapat diajukan ke verifikasi atasan.',
                ], 422);
            }

            $workflow->seedVerification($document);
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            $record = $this->findVisibleRecord($document->id);
            if (
                $record !== null
                && $document->proses_status === BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES
            ) {
                app(BeritaAcaraKejadianDocumentService::class)->syncBakQrFromRecord(
                    $document,
                    $record,
                    $this->karyawan
                );
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $workflow->buildAjukanSuccessMessage($document),
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function rejectAtasan(Request $request, BakAtasanWorkflowService $workflow, BeritaAcaraKejadianDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $document = $this->findDocumentForViewer($this->resolveId($request));

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            $approver = MasterKaryawan::query()
                ->where('id', (int) $this->user_id)
                ->where('is_active', true)
                ->first();

            if (!$approver) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data verifikator tidak ditemukan.',
                ], 404);
            }

            $reason = trim((string) $request->input('keterangan', $request->input('reason', '')));

            $document = $workflow->markAtasanRejected($document, $approver, $reason);
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            $record = $this->findVisibleRecord($document->id);
            if ($record !== null) {
                $documentService->invalidateStoredPdf($document->fresh());
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'BAK ditolak. Dokumen kembali ke permintaan dokumen dan dapat diperbaiki lalu diajukan ulang.',
                'data' => $record ? $this->decorate($record) : null,
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function approveAtasan(
        Request $request,
        BakAtasanWorkflowService $workflow,
        BeritaAcaraKejadianDocumentService $documentService
    ) {
        DB::beginTransaction();

        try {
            $document = $this->findDocumentForViewer($this->resolveId($request));

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            $approver = MasterKaryawan::query()
                ->where('id', (int) $this->user_id)
                ->where('is_active', true)
                ->first();

            if (!$approver) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data verifikator tidak ditemukan.',
                ], 404);
            }

            $result = $workflow->markAtasanApproved($document, $approver);
            $document = $result['document'];
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            if ($result['done']) {
                $record = $this->findVisibleRecord($document->id);
                if ($record !== null) {
                    $documentService->syncBakQrFromRecord($document, $record, $this->karyawan);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $result['done']
                    ? 'Verifikasi atasan selesai. BAK menunggu diproses.'
                    : 'Verifikasi disimpan. Menunggu verifikator berikutnya.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function mulaiProses(Request $request)
    {
        DB::beginTransaction();

        try {
            $document = $this->findDocumentForViewer($this->resolveId($request));

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            if ($document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'BAK tidak dalam status menunggu diproses.',
                ], 422);
            }

            $document->proses_status = BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'BAK sedang diproses.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function selesaiProses(Request $request)
    {
        DB::beginTransaction();

        try {
            $document = $this->findDocumentForViewer($this->resolveId($request));

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            if ($document->proses_status !== BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'BAK tidak dalam status sedang diproses.',
                ], 422);
            }

            $document->proses_status = BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'BAK sudah diproses.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function employeeMeta(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
        ]);

        $karyawanId = (int) $request->karyawan_id;
        $row = $this->fetchEmployeeMetaRow($karyawanId);

        if ($row === null) {
            return response()->json([
                'success' => false,
                'message' => 'Data karyawan tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatEmployeeMeta($row),
        ], 200);
    }

    public function store(Request $request)
    {
        $writtenDokumentasi = [];
        DB::beginTransaction();

        try {
            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();

                return $validation;
            }

            $createdBy = trim((string) $this->karyawan);
            $penandatanganId = $this->resolveKaryawanIdFromCreatedBy($createdBy);
            $karyawanId = $this->resolveKaryawanIdForDocument($request, $penandatanganId);
            $dampakPayload = $this->encodeDampakAsetOrError($request);
            if ($dampakPayload instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();

                return $dampakPayload;
            }

            if (!$this->employeeExists($karyawanId) || !$this->employeeExists($penandatanganId)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $uploads = $this->collectDokumentasiUploads($request);
            if ($uploads instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();

                return $uploads;
            }
            if (count($uploads) < 1) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Minimal 1 lampiran wajib diunggah.',
                ], 422);
            }

            $tanggalKejadian = Carbon::parse($request->tanggal_kejadian)->format('Y-m-d');
            $nomorData = $this->generateNomorSurat($tanggalKejadian);
            $penandatanganSnapshot = $this->buildPenandatanganSnapshot($penandatanganId);
            $now = Carbon::now();

            $document = new BeritaAcaraKejadianDisipliner();
            $document->fill([
                'karyawan_id' => $karyawanId,
                'penandatangan_karyawan_id' => $penandatanganId,
                'penandatangan_nama' => $penandatanganSnapshot['nama'],
                'penandatangan_tempat_lahir' => $penandatanganSnapshot['tempat_lahir'],
                'penandatangan_tanggal_lahir' => $penandatanganSnapshot['tanggal_lahir'],
                'penandatangan_nomor_hp' => $penandatanganSnapshot['nomor_hp'],
                'penandatangan_alamat' => $penandatanganSnapshot['alamat'],
                'penandatangan_nik' => $penandatanganSnapshot['nik'],
                'penandatangan_jabatan' => $penandatanganSnapshot['jabatan'],
                'penandatangan_divisi' => $penandatanganSnapshot['divisi'],
                'penandatangan_penempatan' => $penandatanganSnapshot['penempatan'],
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'tanggal_kejadian' => $tanggalKejadian,
                'waktu_kejadian' => $this->normalizeWaktu($request->waktu_kejadian),
                'lokasi_kejadian' => trim((string) $request->lokasi_kejadian),
                'telah_terjadi_kejadian' => trim((string) ($request->telah_terjadi_kejadian ?? '')) ?: null,
                'dampak_aset_perusahaan' => $dampakPayload,
                'lampiran' => null,
                'perihal' => trim((string) ($request->perihal ?? '')) ?: null,
                'uraian_kejadian' => trim((string) ($request->uraian_kejadian ?? '')) ?: null,
                'status' => BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                'proses_status' => BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN,
                'approval_step' => 0,
                'approval_chain' => null,
                'approval_log' => null,
                'created_by' => $createdBy !== '' ? $createdBy : $this->karyawan,
                'is_active' => true,
                'created_at' => $now,
            ]);
            $document->timestamps = false;
            $document->save();
            $document->timestamps = true;

            $writtenGroups = $this->storeDokumentasiFileGroups((string) $document->nomor_surat, $uploads);
            $writtenDokumentasi = $this->flattenDokumentasiGroups($writtenGroups);
            $document->dokumentasi = json_encode($writtenDokumentasi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $document->lampiran = $this->formatLampiranCount($writtenDokumentasi);
            $document->timestamps = false;
            $document->save();
            $document->timestamps = true;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil dibuat. Menunggu pengesahan.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();
            if (isset($document) && (int) $document->id > 0 && $writtenDokumentasi !== []) {
                $this->deleteDokumentasiFiles((string) $document->nomor_surat, $writtenDokumentasi);
            }

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        $writtenDokumentasi = [];
        DB::beginTransaction();

        try {
            $this->validate($request, [
                'id' => 'required|integer',
            ]);

            $document = $this->findDocumentForViewer((int) $request->id);

            if ($document === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            $workflow = app(BakAtasanWorkflowService::class);
            if (!$workflow->canEditProses($document)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'BAK hanya dapat diubah pada tahap permintaan dokumen (sebelum diajukan ke verifikasi atasan).',
                ], 422);
            }

            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();

                return $validation;
            }

            $penandatanganId = (int) $document->penandatangan_karyawan_id;
            if ($penandatanganId <= 0) {
                $penandatanganId = $this->resolveKaryawanIdFromCreatedBy($document->created_by);
            }
            $karyawanId = $this->resolveKaryawanIdForDocument($request, $penandatanganId);
            $dampakPayload = $this->encodeDampakAsetOrError($request);
            if ($dampakPayload instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();

                return $dampakPayload;
            }

            if (!$this->employeeExists($karyawanId) || !$this->employeeExists($penandatanganId)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $uploads = $this->collectDokumentasiUploads($request);
            if ($uploads instanceof \Illuminate\Http\JsonResponse) {
                DB::rollBack();

                return $uploads;
            }

            $existingDokumentasi = $this->decodeDokumentasiList($document->dokumentasi);
            $keptDokumentasi = $this->resolveKeptDokumentasi($request, $existingDokumentasi);
            if (count($keptDokumentasi) + count($uploads) > 10) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Lampiran maksimal 10 file.',
                ], 422);
            }

            if (count($keptDokumentasi) + count($uploads) < 1) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Minimal 1 lampiran wajib ada.',
                ], 422);
            }

            $writtenGroups = $this->storeDokumentasiFileGroups((string) $document->nomor_surat, $uploads);
            $writtenDokumentasi = $this->flattenDokumentasiGroups($writtenGroups);
            $finalDokumentasi = $this->mergeDokumentasiInOrder($request, $keptDokumentasi, $writtenGroups);

            $tanggalKejadian = Carbon::parse($request->tanggal_kejadian)->format('Y-m-d');
            $penandatanganSnapshot = $this->buildPenandatanganSnapshot($penandatanganId);
            $now = Carbon::now();

            $document->fill([
                'karyawan_id' => $karyawanId,
                'penandatangan_karyawan_id' => $penandatanganId,
                'penandatangan_nama' => $penandatanganSnapshot['nama'],
                'penandatangan_tempat_lahir' => $penandatanganSnapshot['tempat_lahir'],
                'penandatangan_tanggal_lahir' => $penandatanganSnapshot['tanggal_lahir'],
                'penandatangan_nomor_hp' => $penandatanganSnapshot['nomor_hp'],
                'penandatangan_alamat' => $penandatanganSnapshot['alamat'],
                'penandatangan_nik' => $penandatanganSnapshot['nik'],
                'penandatangan_jabatan' => $penandatanganSnapshot['jabatan'],
                'penandatangan_divisi' => $penandatanganSnapshot['divisi'],
                'penandatangan_penempatan' => $penandatanganSnapshot['penempatan'],
                'tanggal_kejadian' => $tanggalKejadian,
                'waktu_kejadian' => $this->normalizeWaktu($request->waktu_kejadian),
                'lokasi_kejadian' => trim((string) $request->lokasi_kejadian),
                'telah_terjadi_kejadian' => trim((string) ($request->telah_terjadi_kejadian ?? '')) ?: null,
                'dampak_aset_perusahaan' => $dampakPayload,
                'lampiran' => $this->formatLampiranCount($finalDokumentasi),
                'perihal' => trim((string) ($request->perihal ?? '')) ?: null,
                'uraian_kejadian' => trim((string) ($request->uraian_kejadian ?? '')) ?: null,
                'dokumentasi' => json_encode($finalDokumentasi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);
            $document->save();

            $documentService->invalidateStoredPdf($document);

            DB::commit();
            $this->deleteDokumentasiFiles(
                (string) $document->nomor_surat,
                array_values(array_diff($existingDokumentasi, $finalDokumentasi))
            );

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil diperbarui.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();
            $this->deleteDokumentasiFiles((string) $document->nomor_surat, $writtenDokumentasi);

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function approve(
        Request $request,
        BeritaAcaraKejadianDocumentService $documentService
    ) {
        DB::beginTransaction();

        try {
            $record = $this->findVisibleRecord($this->resolveId($request));
            if ($record === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            $document = BeritaAcaraKejadianDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($document === null || $document->status !== BeritaAcaraKejadianDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Dokumen tidak dapat disahkan (bukan status draf).',
                ], 422);
            }

            if (trim((string) ($document->file_qr ?? '')) === '') {
                $documentService->syncBakQrFromRecord($document, $record, $this->karyawan);
            } else {
                GenerateQrDocumentBeritaAcaraKejadian::ensurePublicSvgForFile($document->file_qr);
            }

            $document->status = BeritaAcaraKejadianDisipliner::STATUS_ISSUED;
            $document->published_by = $this->karyawan;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil disahkan.',
                'data' => $this->decorate($this->findVisibleRecord($document->id)),
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
        GenerateQrDocumentBeritaAcaraKejadian $qrService,
        BeritaAcaraKejadianDocumentService $documentService
    )
    {
        try {
            $record = $this->findVisibleRecord($this->resolveId($request));
            if ($record === null) {
                return $this->notFoundOrForbiddenResponse();
            }

            $document = BeritaAcaraKejadianDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($document === null) {
                return $this->notFoundOrForbiddenResponse();
            }

            $linked = trim((string) ($document->file_qr ?? ''));
            if ($linked !== '' && GenerateQrDocumentBeritaAcaraKejadian::ensurePublicSvgForFile($linked)) {
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

            $qrFile = $qrService->sync($document, $record, $this->karyawan);
            $svgPath = QrDocumentsStorage::svgPath($qrFile);

            if (!is_file($svgPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR gagal disimpan ke: ' . $svgPath,
                ], 422);
            }

            $document->file_qr = $qrFile;
            $document->save();
            $documentService->invalidateStoredPdf($document);

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
            $record = $this->findDocumentForViewer($this->resolveId($request));

            if ($record === null) {
                DB::rollBack();

                return $this->notFoundOrForbiddenResponse();
            }

            if ($record->status !== BeritaAcaraKejadianDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draf yang belum disahkan yang dapat dihapus.',
                ], 422);
            }

            $now = Carbon::now();
            $record->update([
                'is_active' => false,
                'status' => BeritaAcaraKejadianDisipliner::STATUS_CANCELLED,
                'updated_by' => $this->karyawan,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil dihapus.',
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

            $data = $fixingService->regenerate(DisiplinerPdfFixingService::JENIS_BAK, (int) $request->id);

            return response()->json([
                'success' => true,
                'message' => 'PDF berita acara kejadian berhasil di-render ulang.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    /**
     * Riwayat status BAK (dan investigasi terkait) untuk modal view PDF.
     */
    public function timeline(Request $request)
    {
        $id = (int) $request->input('id');
        if ($id <= 0) {
            return response()->json(['success' => false, 'message' => 'ID tidak valid.'], 422);
        }

        $document = $this->findDocumentForViewer($id);
        if ($document === null) {
            return $this->notFoundOrForbiddenResponse();
        }

        $logs = app(BakKejadianTimelineService::class)->buildMergedForBakView($id);

        return response()->json([
            'success' => true,
            'data' => ['logs' => $logs],
        ], 200);
    }

    public function printPdf(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        try {
            $record = $this->findVisibleRecord($this->resolveId($request));
            if ($record === null) {
                return $this->notFoundOrForbiddenResponse();
            }

            $document = BeritaAcaraKejadianDisipliner::find($record->id);

            if (!empty($document->file_qr)) {
                GenerateQrDocumentBeritaAcaraKejadian::ensurePublicSvgForFile($document->file_qr);
            }

            $pdfBinary = $documentService->resolvePdfBinary($document, $record);
            $document->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF berita acara kejadian siap.',
                'data' => base64_encode($pdfBinary),
                'pdf_ready' => true,
                'file_name' => $document->nama_berkas ?: ($document->nomor_surat . '.pdf'),
                'file_path' => $document->file_path,
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
        return BeritaAcaraKejadianDisipliner::query()
            ->from('berita_acara_kejadian_disipliner as bak')
            ->leftJoin('master_karyawan as mk', 'bak.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('bak.is_active', true)
            ->select(
                'bak.id',
                'bak.karyawan_id',
                'bak.penandatangan_karyawan_id',
                'bak.penandatangan_nama',
                'bak.penandatangan_tempat_lahir',
                'bak.penandatangan_tanggal_lahir',
                'bak.penandatangan_nomor_hp',
                'bak.penandatangan_alamat',
                'bak.penandatangan_nik',
                'bak.penandatangan_jabatan',
                'bak.penandatangan_divisi',
                'bak.penandatangan_penempatan',
                'bak.nomor_surat',
                'bak.tanggal_kejadian',
                'bak.waktu_kejadian',
                'bak.lokasi_kejadian',
                'bak.telah_terjadi_kejadian',
                'bak.dampak_aset_perusahaan',
                'bak.lampiran',
                'bak.perihal',
                'bak.uraian_kejadian',
                'bak.dokumentasi',
                'bak.catatan',
                'bak.status',
                'bak.proses_status',
                'bak.approval_step',
                'bak.approval_chain',
                'bak.approval_log',
                'bak.reject_atasan_reason',
                'bak.rejected_atasan_at',
                'bak.rejected_atasan_by',
                'bak.rejected_atasan_karyawan_id',
                'bak.nama_berkas',
                'bak.file_path',
                'bak.file_size',
                'bak.file_qr',
                'bak.created_by',
                'bak.updated_by',
                'bak.published_by',
                'bak.created_at',
                'bak.updated_at',
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

        return $this->baseQuery()->where('bak.id', (int) $id)->first();
    }

    private function findVisibleRecord($id): ?object
    {
        $record = $this->findActiveRecord($id);
        if ($record === null) {
            return null;
        }

        $visibility = app(BakDocumentVisibilityService::class);
        if (!$visibility->canViewRow($record, (int) $this->user_id)) {
            return null;
        }

        return $record;
    }

    private function applyVisibilityScope($query): void
    {
        app(BakDocumentVisibilityService::class)->applyListScope(
            $query,
            (int) $this->user_id
        );
    }

    private function assertDocumentVisible(BeritaAcaraKejadianDisipliner $document): void
    {
        app(BakDocumentVisibilityService::class)->assertCanViewRow($document, (int) $this->user_id);
    }

    private function findDocumentForViewer(int $id): ?BeritaAcaraKejadianDisipliner
    {
        if ($this->findVisibleRecord($id) === null) {
            return null;
        }

        return BeritaAcaraKejadianDisipliner::query()
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }

    private function notFoundOrForbiddenResponse()
    {
        return response()->json([
            'success' => false,
            'message' => 'Anda tidak memiliki akses untuk melihat BAK ini.',
        ], 403);
    }

    private function fetchEmployeeMetaRow(int $karyawanId)
    {
        return MasterKaryawan::query()
            ->from('master_karyawan as mk')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->leftJoin('master_cabang as mc', 'mk.id_cabang', '=', 'mc.id')
            ->where('mk.id', $karyawanId)
            ->where('mk.is_active', true)
            ->select(
                'mk.id',
                'mk.nik_karyawan',
                'mk.nama_lengkap',
                'mk.tempat_lahir',
                'mk.tanggal_lahir',
                'mk.alamat',
                'mk.no_telpon',
                'mk.no_telpon_perusahaan',
                'mk.jabatan',
                'mk.department',
                'md.nama_divisi',
                'mc.nama_cabang'
            )
            ->first();
    }

    private function resolveNomorHpKaryawan($row): string
    {
        $company = trim((string) ($row->no_telpon_perusahaan ?? ''));
        if ($company !== '') {
            return $company;
        }

        return trim((string) ($row->no_telpon ?? ''));
    }

    private function formatTanggalLahirLabel($value): string
    {
        if ($value === null || trim((string) $value) === '') {
            return '-';
        }

        try {
            return Carbon::parse($value)->locale('id')->translatedFormat('d F Y');
        } catch (\Throwable $ex) {
            return trim((string) $value) ?: '-';
        }
    }

    private function formatEmployeeMeta($row): array
    {
        $divisi = trim((string) ($row->nama_divisi ?? ''));
        if ($divisi === '') {
            $divisi = trim((string) ($row->department ?? ''));
        }

        $posisi = trim((string) ($row->jabatan ?? ''));

        return [
            'karyawan_id' => (int) $row->id,
            'nama_lengkap' => trim((string) ($row->nama_lengkap ?? '')) ?: '-',
            'tempat_lahir' => trim((string) ($row->tempat_lahir ?? '')) ?: '-',
            'tanggal_lahir' => $this->formatTanggalLahirLabel($row->tanggal_lahir ?? null),
            'nomor_hp' => $this->resolveNomorHpKaryawan($row) ?: '-',
            'alamat' => trim((string) ($row->alamat ?? '')) ?: '-',
            'nik_karyawan' => trim((string) ($row->nik_karyawan ?? '')),
            'divisi' => $divisi !== '' ? $divisi : '-',
            'posisi' => $posisi !== '' ? $posisi : '-',
            'jabatan' => $posisi !== '' ? $posisi : '-',
            'penempatan' => trim((string) ($row->nama_cabang ?? '')) ?: '-',
        ];
    }

    private function buildPenandatanganSnapshot(int $karyawanId): array
    {
        $row = $this->fetchEmployeeMetaRow($karyawanId);
        if ($row === null) {
            throw new \RuntimeException('Data penandatangan tidak ditemukan.');
        }

        $meta = $this->formatEmployeeMeta($row);

        return [
            'nama' => $meta['nama_lengkap'],
            'tempat_lahir' => $meta['tempat_lahir'],
            'tanggal_lahir' => $meta['tanggal_lahir'],
            'nomor_hp' => $meta['nomor_hp'],
            'alamat' => $meta['alamat'],
            'nik' => $meta['nik_karyawan'],
            'jabatan' => $meta['posisi'],
            'divisi' => $meta['divisi'],
            'penempatan' => $meta['penempatan'],
        ];
    }

    private function decorate($item)
    {
        if ($item === null) {
            return null;
        }

        $storedDokumentasi = BeritaAcaraKejadianDokumentasiStorage::decodeList($item->dokumentasi ?? null);
        $item->dokumentasi = BeritaAcaraKejadianDokumentasiStorage::expandForApi(
            (string) ($item->nomor_surat ?? ''),
            $storedDokumentasi
        );
        $dampakParsed = BeritaAcaraKejadianDampak::parse($item->dampak_aset_perusahaan ?? null);
        $item->berdampak_pada_aset = trim((string) ($dampakParsed['nama_jenis_aset'] ?? '')) !== '' ? 'ya' : 'tidak';
        $item->nama_karyawan = $item->nama_lengkap ?: '-';
        $item->divisi = $item->nama_divisi ?: ($item->department ?: '-');
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->proses_status_label = $this->mapProsesStatusLabel($item->proses_status ?? BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN);
        $item->tanggal_kejadian_label = $item->tanggal_kejadian
            ? Carbon::parse($item->tanggal_kejadian)->format('d-m-Y')
            : '-';
        $item->has_document = !empty($item->file_path);
        $item->has_qr = !empty($item->file_qr);

        $workflow = app(BakAtasanWorkflowService::class);
        $documentModel = new BeritaAcaraKejadianDisipliner();
        $documentModel->exists = true;
        $documentModel->forceFill([
            'id' => $item->id,
            'proses_status' => $item->proses_status ?? BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN,
            'approval_step' => (int) ($item->approval_step ?? 0),
            'approval_chain' => $item->approval_chain,
            'is_active' => true,
        ]);
        $proses = $documentModel->proses_status;

        $item->can_edit = $documentModel && $workflow->canEditProses($documentModel);
        $item->can_ajukan = $documentModel
            && $proses === BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN;
        $item->can_approve_atasan = $documentModel
            && $workflow->viewerCanApproveAtasan($documentModel, (int) $this->user_id);
        $item->can_reject_atasan = $item->can_approve_atasan;
        $linkedInvestigasi = BakLaporanService::isBeritaAcaraLinkedToInvestigasi((int) $item->id);
        $item->proses_sinkron_investigasi = $linkedInvestigasi;
        $item->can_mulai_proses = !$linkedInvestigasi
            && $proses === BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES;
        $item->can_selesai_proses = !$linkedInvestigasi
            && $proses === BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES;
        $item->can_approve = $item->status === BeritaAcaraKejadianDisipliner::STATUS_DRAFT
            && $proses === BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES;

        $chain = $workflow->decodeChain($item->approval_chain ?? null);
        $pendingId = $documentModel ? $workflow->currentPendingApproverId($documentModel) : null;
        $item->verifikator_saat_ini = '-';
        if ($pendingId) {
            $pending = collect($chain)->first(fn ($c) => (int) ($c['karyawan_id'] ?? 0) === $pendingId);
            $item->verifikator_saat_ini = $pending['nama'] ?? '-';
        }
        $item->jumlah_verifikasi_atasan = count($chain);
        $item->verifikasi_atasan_ke = $proses === BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN
            ? ((int) ($item->approval_step ?? 0) + 1)
            : null;
        $item->verifikator_tampilan = $this->buildVerifikatorTampilan(
            $proses,
            $chain,
            $pendingId,
            $item
        );
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

    /**
     * @param list<array<string, mixed>> $chain
     */
    private function buildVerifikatorTampilan(string $proses, array $chain, ?int $pendingId, object $item): string
    {
        $total = count($chain);

        if ($proses === BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN && $pendingId) {
            $pending = collect($chain)->first(fn ($c) => (int) ($c['karyawan_id'] ?? 0) === $pendingId);
            $nama = trim((string) ($pending['nama'] ?? '')) ?: '-';
            $step = (int) ($item->approval_step ?? 0) + 1;

            return $total > 0 ? $nama . ' (' . $step . '/' . $total . ')' : $nama;
        }

        if ($chain !== []) {
            $labels = [];
            foreach ($chain as $index => $stepRow) {
                $nama = trim((string) ($stepRow['nama'] ?? '')) ?: '-';
                $state = strtolower(trim((string) ($stepRow['state'] ?? 'pending')));
                if ($state === 'approved') {
                    $labels[] = $nama . ' ✓';
                } elseif ($state === 'rejected') {
                    $labels[] = $nama . ' (tolak)';
                } else {
                    $labels[] = $nama;
                }
            }

            return implode(', ', $labels);
        }

        $rejectedBy = trim((string) ($item->rejected_atasan_by ?? ''));
        if ($rejectedBy !== '') {
            return 'Ditolak: ' . $rejectedBy;
        }

        return '-';
    }

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED => 'Terbit',
            BeritaAcaraKejadianDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function mapProsesStatusLabel(?string $status): string
    {
        $map = [
            BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN => 'Permintaan dokumen',
            BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN => 'Verifikasi atasan',
            BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES => 'Menunggu diproses',
            BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES => 'Sedang diproses',
            BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES => 'Sudah diproses',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function applyWorkflowTabFilter($query, string $workflowTab): void
    {
        $map = [
            'permintaan_dokumen' => BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN,
            'verifikasi_atasan' => BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN,
            'menunggu_diproses' => BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES,
            'sedang_diproses' => BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES,
            'sudah_diproses' => BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES,
            // legacy tab keys
            'pembuatan' => BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN,
            'pengesahan' => BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN,
        ];

        $proses = $map[$workflowTab] ?? BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN;
        $query->where('bak.proses_status', $proses);
    }

    private function validatePayload(Request $request)
    {
        $this->validate($request, [
            'karyawan_id' => 'nullable|integer',
            'penandatangan_karyawan_id' => 'nullable|integer',
            'berdampak_pada_aset' => 'required|in:ya,tidak',
            'dampak_aset_perusahaan' => 'nullable|array',
            'tanggal_kejadian' => 'required|date',
            'waktu_kejadian' => 'required|string|max:10',
            'lokasi_kejadian' => 'required|string|max:255',
            'telah_terjadi_kejadian' => 'nullable|string|max:500',
            'uraian_kejadian' => 'nullable|string',
            'perihal' => 'nullable|string|max:200',
        ]);

        $plainUraian = trim(strip_tags((string) ($request->uraian_kejadian ?? '')));
        if ($plainUraian === '') {
            return response()->json([
                'success' => false,
                'message' => 'Uraian kejadian wajib diisi.',
            ], 422);
        }

        return true;
    }

    private function generateNomorSurat(string $tanggalReferensi): array
    {
        $month = date('m', strtotime($tanggalReferensi));
        $year = (int) date('Y', strtotime($tanggalReferensi));

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $hex = strtoupper(bin2hex(random_bytes(3)));
            $nomorSurat = 'ISL-' . $month . '-BAK-' . $hex;
            $exists = BeritaAcaraKejadianDisipliner::query()
                ->where('nomor_surat', $nomorSurat)
                ->exists();
            if (!$exists) {
                return [
                    'nomor_surat' => $nomorSurat,
                    'tahun_nomor' => $year,
                    'urutan_nomor' => hexdec($hex),
                ];
            }
        }

        throw new \RuntimeException('Gagal membuat nomor surat. Coba lagi.');
    }

    private function resolvePenandatanganId(Request $request): int
    {
        $fromRequest = (int) $request->input('penandatangan_karyawan_id', 0);
        if ($fromRequest > 0) {
            return $fromRequest;
        }

        return (int) $this->user_id;
    }

    /**
     * Penandatangan di PDF = pembuat dokumen; nama di created_by di-resolve ke master_karyawan.
     */
    private function resolveKaryawanIdFromCreatedBy(?string $createdBy): int
    {
        $name = trim((string) $createdBy);
        if ($name !== '') {
            $id = MasterKaryawan::query()
                ->where('is_active', true)
                ->where('nama_lengkap', $name)
                ->value('id');
            if ($id) {
                return (int) $id;
            }
        }

        return (int) $this->user_id;
    }

  /** @param array<int, string> $paths */
    private function formatLampiranCount(array $paths): ?string
    {
        $count = count($paths);

        return $count > 0 ? (string) $count : null;
    }

    private function normalizeWaktu($value): ?string
    {
        $waktu = trim((string) $value);
        if ($waktu === '') {
            return null;
        }

        if (preg_match('/^\d{1,2}:\d{2}$/', $waktu)) {
            [$h, $m] = array_map('intval', explode(':', $waktu));

            return sprintf('%02d:%02d', $h, $m);
        }

        return substr($waktu, 0, 10);
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
        return (int) ($request->input('id') ?: $request->input('document_id') ?: 0);
    }

    private function resolveKaryawanIdForDocument(Request $request, int $penandatanganId): int
    {
        $fromRequest = (int) $request->input('karyawan_id', 0);

        return $fromRequest > 0 ? $fromRequest : $penandatanganId;
    }

    /**
     * @return array<int, \Illuminate\Http\UploadedFile>|\Illuminate\Http\JsonResponse
     */
    private function collectDokumentasiUploads(Request $request)
    {
        $files = $request->file('dokumentasi', []);
        if ($files instanceof \Illuminate\Http\UploadedFile) {
            $files = [$files];
        }
        if (!is_array($files)) {
            $files = [];
        }

        $allowedImageMime = ['image/jpeg', 'image/png', 'image/webp', 'image/pjpeg'];
        $allowedImageExt = ['jpg', 'jpeg', 'png', 'webp'];
        $valid = [];

        foreach ($files as $file) {
            if (!$file instanceof \Illuminate\Http\UploadedFile) {
                continue;
            }
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'File lampiran gagal diunggah.',
                ], 422);
            }

            $mime = strtolower((string) $file->getMimeType());
            $ext = strtolower((string) ($file->getClientOriginalExtension() ?: $file->extension()));
            $isImage = in_array($mime, $allowedImageMime, true) && in_array($ext, $allowedImageExt, true);
            $isPdf = $ext === 'pdf' && in_array($mime, ['application/pdf', 'application/octet-stream', 'application/x-pdf'], true);
            if (!$isImage && !$isPdf) {
                return response()->json([
                    'success' => false,
                    'message' => 'Lampiran harus berupa gambar (JPG, PNG, WEBP) atau PDF.',
                ], 422);
            }

            if ((int) $file->getSize() > 2 * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ukuran tiap lampiran maksimal 2 MB.',
                ], 422);
            }

            $valid[] = $file;
        }

        if (count($valid) > 10) {
            return response()->json([
                'success' => false,
                'message' => 'Lampiran maksimal 10 file.',
            ], 422);
        }

        return $valid;
    }

    private function decodeDokumentasiList($value): array
    {
        return BeritaAcaraKejadianDokumentasiStorage::decodeList($value);
    }

    private function resolveKeptDokumentasi(Request $request, array $existing): array
    {
        if (!$request->exists('dokumentasi_tersimpan_json')) {
            return $existing;
        }

        $decoded = json_decode((string) $request->input('dokumentasi_tersimpan_json', '[]'), true);
        if (!is_array($decoded)) {
            return $existing;
        }

        $allowed = array_fill_keys($existing, true);
        $kept = [];
        foreach ($decoded as $path) {
            $name = BeritaAcaraKejadianDokumentasiStorage::normalizeStoredName((string) $path);
            if ($name !== '' && isset($allowed[$name])) {
                $kept[] = $name;
            }
        }

        return array_values(array_unique($kept));
    }

    /**
     * Gabungkan lampiran tersimpan + file baru sesuai urutan UI.
     *
     * @param array<int, string> $kept
     * @param array<int, array<int, string>> $writtenGroups satu grup = satu file upload (PDF bisa banyak halaman JPG)
     * @return array<int, string>
     */
    private function mergeDokumentasiInOrder(Request $request, array $kept, array $writtenGroups): array
    {
        $writtenFlat = $this->flattenDokumentasiGroups($writtenGroups);
        $raw = json_decode((string) $request->input('dokumentasi_urutan_json', '[]'), true);
        if (!is_array($raw) || $raw === []) {
            return array_values(array_merge($kept, $writtenFlat));
        }

        $keptQueue = $kept;
        $writtenQueue = $writtenGroups;
        $result = [];

        foreach ($raw as $entry) {
            $kind = is_array($entry) ? (string) ($entry['kind'] ?? $entry['type'] ?? '') : '';
            if ($kind === 'saved') {
                $path = BeritaAcaraKejadianDokumentasiStorage::normalizeStoredName((string) ($entry['path'] ?? ''));
                $idx = array_search($path, $keptQueue, true);
                if ($idx === false) {
                    continue;
                }
                $result[] = $keptQueue[$idx];
                unset($keptQueue[$idx]);
                $keptQueue = array_values($keptQueue);
            } elseif ($kind === 'new' && $writtenQueue !== []) {
                $group = array_shift($writtenQueue);
                foreach ($group as $path) {
                    $result[] = $path;
                }
            }
        }

        $tail = [];
        foreach ($writtenQueue as $group) {
            foreach ($group as $path) {
                $tail[] = $path;
            }
        }

        return array_values(array_merge($result, $keptQueue, $tail));
    }

    /**
     * @param array<int, array<int, string>> $groups
     * @return array<int, string>
     */
    private function flattenDokumentasiGroups(array $groups): array
    {
        $flat = [];
        foreach ($groups as $group) {
            if (!is_array($group)) {
                continue;
            }
            foreach ($group as $path) {
                $flat[] = $path;
            }
        }

        return $flat;
    }

    /**
     * PDF → JPG per halaman (Imagick) saat simpan; file PDF tidak disimpan.
     *
     * @param array<int, \Illuminate\Http\UploadedFile> $files
     * @return array<int, array<int, string>>
     */
    private function storeDokumentasiFileGroups(string $nomorSurat, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $directory = BeritaAcaraKejadianDokumentasiStorage::publicDirectory($nomorSurat);

        $converter = app(BeritaAcaraKejadianLampiranConvertService::class);
        $groups = [];

        foreach ($files as $file) {
            $ext = strtolower((string) ($file->getClientOriginalExtension() ?: 'jpg'));
            $mime = strtolower((string) $file->getMimeType());
            $isPdf = $ext === 'pdf'
                && in_array($mime, ['application/pdf', 'application/octet-stream', 'application/x-pdf'], true);

            if ($isPdf) {
                $base = uniqid('dok_', true);
                $tempPdfName = $base . '.pdf';
                $file->move($directory, $tempPdfName);
                $fullPdf = $directory . DIRECTORY_SEPARATOR . $tempPdfName;

                try {
                    $jpegFullPaths = $converter->convertPdfToJpegFiles($fullPdf, $directory, $base);
                } finally {
                    if (is_file($fullPdf)) {
                        @unlink($fullPdf);
                    }
                }

                $group = [];
                foreach ($jpegFullPaths as $jpegFull) {
                    $group[] = basename($jpegFull);
                }
                $groups[] = $group;
                continue;
            }

            $name = uniqid('dok_', true) . '.' . $ext;
            $file->move($directory, $name);
            $groups[] = [$name];
        }

        return $groups;
    }

    /**
     * @param array<int, string> $storedNames nama file saja (atau path lama)
     */
    private function deleteDokumentasiFiles(string $nomorSurat, array $storedNames): void
    {
        foreach ($storedNames as $stored) {
            $full = BeritaAcaraKejadianDokumentasiStorage::fullPath($nomorSurat, (string) $stored);
            if ($full !== '' && is_file($full)) {
                @unlink($full);
            }
        }
    }

    /**
     * @return string|\Illuminate\Http\JsonResponse
     */
    private function encodeDampakAsetOrError(Request $request)
    {
        $flag = strtolower(trim((string) $request->input('berdampak_pada_aset', '')));
        if ($flag === 'tidak') {
            return null;
        }
        if ($flag !== 'ya') {
            return response()->json([
                'success' => false,
                'message' => 'Pilih apakah berdampak pada aset (Ya atau Tidak).',
            ], 422);
        }

        $encoded = BeritaAcaraKejadianDampak::encodeFromRequest($request->input('dampak_aset_perusahaan'));
        if (!$encoded['ok']) {
            return response()->json([
                'success' => false,
                'message' => $encoded['message'],
            ], 422);
        }

        return $encoded['json'];
    }
}
