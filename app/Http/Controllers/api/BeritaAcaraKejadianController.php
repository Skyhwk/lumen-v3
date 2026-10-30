<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Models\MasterKaryawan;
use App\Services\BeritaAcaraKejadianDocumentService;
use App\Services\DisiplinerPdfFixingService;
use App\Services\GenerateQrDocumentBeritaAcaraKejadian;
use App\Support\BeritaAcaraKejadianDampak;
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

            $periode = (int) $request->input('periode', date('Y'));
            if ($periode >= 2000) {
                $query->whereYear('bak.tanggal_kejadian', $periode);
            }

            $workflowTab = strtolower(trim((string) $request->input('workflow_tab', 'pembuatan')));
            if ($workflowTab === 'pengesahan') {
                $query->where('bak.status', BeritaAcaraKejadianDisipliner::STATUS_DRAFT);
            } else {
                $query->whereIn('bak.status', [
                    BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                    BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
                ]);
            }

            if ($request->filled('status')) {
                $query->where('bak.status', strtolower(trim((string) $request->status)));
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
                    'message' => 'Berita acara kejadian tidak ditemukan.',
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

    public function tabCounts(Request $request)
    {
        $periode = (int) $request->input('periode', date('Y'));

        $base = BeritaAcaraKejadianDisipliner::query()
            ->where('is_active', true);

        if ($periode >= 2000) {
            $base->whereYear('tanggal_kejadian', $periode);
        }

        $pembuatan = (clone $base)->whereIn('status', [
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED,
        ])->count();

        $pengesahan = (clone $base)
            ->where('status', BeritaAcaraKejadianDisipliner::STATUS_DRAFT)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pembuatan' => $pembuatan,
                'pengesahan' => $pengesahan,
            ],
        ], 200);
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
        DB::beginTransaction();

        try {
            $validation = $this->validatePayload($request);
            if ($validation !== true) {
                DB::rollBack();

                return $validation;
            }

            $penandatanganId = (int) $request->penandatangan_karyawan_id;
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

            $tanggalKejadian = Carbon::parse($request->tanggal_kejadian)->format('Y-m-d');
            $nomorData = $this->resolveNomorSuratData($request, $tanggalKejadian);
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
                'lampiran' => trim((string) ($request->lampiran ?? '')) ?: null,
                'perihal' => trim((string) ($request->perihal ?? '')) ?: null,
                'uraian_kejadian' => trim((string) ($request->uraian_kejadian ?? '')) ?: null,
                'status' => BeritaAcaraKejadianDisipliner::STATUS_DRAFT,
                'created_by' => $this->karyawan,
                'is_active' => true,
                'created_at' => $now,
            ]);
            $document->timestamps = false;
            $document->save();
            $document->timestamps = true;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil dibuat. Menunggu pengesahan.',
                'data' => $this->decorate($this->findActiveRecord($document->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $this->validate($request, [
                'id' => 'required|integer',
            ]);

            $document = BeritaAcaraKejadianDisipliner::query()
                ->where('id', (int) $request->id)
                ->where('is_active', true)
                ->first();

            if ($document === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
            }

            if ($document->status !== BeritaAcaraKejadianDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draf yang belum disahkan yang dapat diubah.',
                ], 422);
            }

            $validation = $this->validatePayload($request, (int) $document->id);
            if ($validation !== true) {
                DB::rollBack();

                return $validation;
            }

            $penandatanganId = (int) $request->penandatangan_karyawan_id;
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

            $tanggalKejadian = Carbon::parse($request->tanggal_kejadian)->format('Y-m-d');
            $nomorData = $this->resolveNomorSuratData($request, $tanggalKejadian);
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
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'tanggal_kejadian' => $tanggalKejadian,
                'waktu_kejadian' => $this->normalizeWaktu($request->waktu_kejadian),
                'lokasi_kejadian' => trim((string) $request->lokasi_kejadian),
                'telah_terjadi_kejadian' => trim((string) ($request->telah_terjadi_kejadian ?? '')) ?: null,
                'dampak_aset_perusahaan' => $dampakPayload,
                'lampiran' => trim((string) ($request->lampiran ?? '')) ?: null,
                'perihal' => trim((string) ($request->perihal ?? '')) ?: null,
                'uraian_kejadian' => trim((string) ($request->uraian_kejadian ?? '')) ?: null,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);
            $document->save();

            $documentService->invalidateStoredPdf($document);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil diperbarui.',
                'data' => $this->decorate($this->findActiveRecord($document->id)),
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
        BeritaAcaraKejadianDocumentService $documentService,
        GenerateQrDocumentBeritaAcaraKejadian $qrService
    ) {
        DB::beginTransaction();

        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
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

            $qrFile = $qrService->sync($document, $record, $this->karyawan);

            $svgPath = QrDocumentsStorage::svgPath($qrFile);
            if (!is_file($svgPath)) {
                throw new \RuntimeException('QR tidak tersimpan di: ' . $svgPath);
            }

            $document->file_qr = $qrFile;
            $document->status = BeritaAcaraKejadianDisipliner::STATUS_ISSUED;
            $document->published_by = $this->karyawan;
            $document->updated_by = $this->karyawan;
            $document->updated_at = Carbon::now();
            $document->save();
            $document->refresh();

            $documentService->invalidateStoredPdf($document);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Berita acara kejadian berhasil disahkan.',
                'data' => $this->decorate($this->findActiveRecord($document->id)),
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
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
            }

            $document = BeritaAcaraKejadianDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($document === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
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
            $record = BeritaAcaraKejadianDisipliner::query()
                ->where('id', $this->resolveId($request))
                ->where('is_active', true)
                ->first();

            if ($record === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
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

    public function printPdf(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Berita acara kejadian tidak ditemukan.',
                ], 404);
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
                'bak.catatan',
                'bak.status',
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

        $item->nama_karyawan = $item->nama_lengkap ?: '-';
        $item->divisi = $item->nama_divisi ?: ($item->department ?: '-');
        $item->status_label = $this->mapStatusLabel($item->status);
        $item->tanggal_kejadian_label = $item->tanggal_kejadian
            ? Carbon::parse($item->tanggal_kejadian)->format('d-m-Y')
            : '-';
        $item->has_document = !empty($item->file_path);
        $item->has_qr = !empty($item->file_qr);
        $item->can_approve = $item->status === BeritaAcaraKejadianDisipliner::STATUS_DRAFT;
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

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED => 'Terbit',
            BeritaAcaraKejadianDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function validatePayload(Request $request, ?int $excludeId = null)
    {
        $this->validate($request, [
            'karyawan_id' => 'nullable|integer',
            'penandatangan_karyawan_id' => 'required|integer',
            'dampak_aset_perusahaan' => 'nullable|array',
            'tanggal_kejadian' => 'required|date',
            'waktu_kejadian' => 'required|string|max:10',
            'lokasi_kejadian' => 'required|string|max:255',
            'telah_terjadi_kejadian' => 'nullable|string|max:500',
            'uraian_kejadian' => 'nullable|string',
            'lampiran' => 'nullable|string|max:100',
            'perihal' => 'nullable|string|max:200',
            'nomor_surat' => 'nullable|string|max:100',
        ]);

        $plainUraian = trim(strip_tags((string) ($request->uraian_kejadian ?? '')));
        if ($plainUraian === '') {
            return response()->json([
                'success' => false,
                'message' => 'Uraian kejadian wajib diisi.',
            ], 422);
        }

        $nomorSurat = trim((string) ($request->nomor_surat ?? ''));
        if ($nomorSurat === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor surat wajib diisi.',
            ], 422);
        }

        $duplicateQuery = BeritaAcaraKejadianDisipliner::query()
            ->where('is_active', true)
            ->where('nomor_surat', $nomorSurat);
        if ($excludeId !== null) {
            $duplicateQuery->where('id', '!=', $excludeId);
        }
        if ($duplicateQuery->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Nomor surat sudah digunakan. Gunakan nomor lain.',
            ], 422);
        }

        return true;
    }

    private function resolveNomorSuratData(Request $request, string $tanggalReferensi): array
    {
        $nomorSurat = trim((string) ($request->nomor_surat ?? ''));
        $year = (int) date('Y', strtotime($tanggalReferensi));
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
     * @return string|\Illuminate\Http\JsonResponse
     */
    private function encodeDampakAsetOrError(Request $request)
    {
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
