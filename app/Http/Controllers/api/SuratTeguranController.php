<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\SuratTeguranDisipliner;
use App\Services\DisiplinerPdfFixingService;
use App\Services\GenerateQrDocumentSuratTeguran;
use App\Services\SuratTeguranDocumentService;
use App\Support\QrDocumentsStorage;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Yajra\Datatables\Datatables;

class SuratTeguranController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = $this->baseQuery();

            $periode = (int) $request->input('periode', date('Y'));
            if ($periode >= 2000) {
                $query->whereYear('std.created_at', $periode);
            }

            $workflowTab = strtolower(trim((string) $request->input('workflow_tab', 'pembuatan')));
            if ($workflowTab === 'pengesahan') {
                $query->where('std.status', SuratTeguranDisipliner::STATUS_DRAFT);
            } else {
                $query->whereIn('std.status', [
                    SuratTeguranDisipliner::STATUS_DRAFT,
                    SuratTeguranDisipliner::STATUS_ISSUED,
                ]);
            }

            if ($request->filled('status')) {
                $query->where('std.status', strtolower(trim((string) $request->status)));
            }

            $data = $query->get()->map(fn ($row) => $this->toApiPayload($row));

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
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->toApiPayload($record),
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

        $base = SuratTeguranDisipliner::query()
            ->where('is_active', true);

        if ($periode >= 2000) {
            $base->whereYear('created_at', $periode);
        }

        $pembuatan = (clone $base)->whereIn('status', [
            SuratTeguranDisipliner::STATUS_DRAFT,
            SuratTeguranDisipliner::STATUS_ISSUED,
        ])->count();

        $pengesahan = (clone $base)
            ->where('status', SuratTeguranDisipliner::STATUS_DRAFT)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'pembuatan' => $pembuatan,
                'pengesahan' => $pengesahan,
            ],
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

            $karyawanId = (int) $request->karyawan_id;
            if (!$this->employeeExists($karyawanId)) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Data karyawan tidak ditemukan.',
                ], 404);
            }

            $nomorData = $this->resolveNomorSuratData($request);
            $now = Carbon::now('Asia/Jakarta');

            $letter = new SuratTeguranDisipliner();
            $letter->fill([
                'karyawan_id' => $karyawanId,
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'perihal' => trim((string) $request->perihal),
                'tanggal_terbit' => $now->format('Y-m-d'),
                'tanggal_kejadian' => $this->parseDateOnlyInput($request->input('tanggal_kejadian')),
                'uraian_pelanggaran' => trim((string) $request->uraian_pelanggaran) ?: null,
                'status' => SuratTeguranDisipliner::STATUS_DRAFT,
                'created_by' => $this->karyawan,
                'is_active' => true,
                'created_at' => $now,
            ]);
            $letter->timestamps = false;
            $letter->save();
            $letter->timestamps = true;

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat teguran berhasil dibuat. Menunggu pengesahan.',
                'data' => $this->toApiPayload($this->findActiveRecord($letter->id)),
            ], 200);
        } catch (\Throwable $ex) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function update(Request $request, SuratTeguranDocumentService $documentService)
    {
        DB::beginTransaction();

        try {
            $this->validate($request, [
                'id' => 'required|integer',
            ]);

            $letter = SuratTeguranDisipliner::query()
                ->where('id', (int) $request->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            if ($letter->status !== SuratTeguranDisipliner::STATUS_DRAFT) {
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

            $nomorData = $this->resolveNomorSuratData($request);
            $now = Carbon::now('Asia/Jakarta');

            $letter->fill([
                'karyawan_id' => $karyawanId,
                'nomor_surat' => $nomorData['nomor_surat'],
                'tahun_nomor' => $nomorData['tahun_nomor'],
                'urutan_nomor' => $nomorData['urutan_nomor'],
                'perihal' => trim((string) $request->perihal),
                'tanggal_kejadian' => $this->parseDateOnlyInput($request->input('tanggal_kejadian')),
                'uraian_pelanggaran' => trim((string) $request->uraian_pelanggaran) ?: null,
                'updated_by' => $this->karyawan,
                'updated_at' => $now,
            ]);
            $letter->save();

            $documentService->invalidateStoredPdf($letter);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat teguran berhasil diperbarui.',
                'data' => $this->toApiPayload($this->findActiveRecord($letter->id)),
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
        SuratTeguranDocumentService $documentService,
        GenerateQrDocumentSuratTeguran $qrService
    ) {
        DB::beginTransaction();

        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            $letter = SuratTeguranDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null || $letter->status !== SuratTeguranDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak dapat disahkan (bukan status draf).',
                ], 422);
            }

            $qrFile = $qrService->sync($letter, $record, $this->karyawan);

            $svgPath = QrDocumentsStorage::svgPath($qrFile);
            if (!is_file($svgPath)) {
                throw new \RuntimeException('QR tidak tersimpan di: ' . $svgPath);
            }

            $signer = $this->resolveSignerFromCurrentUser();

            $letter->file_qr = $qrFile;
            $letter->status = SuratTeguranDisipliner::STATUS_ISSUED;
            $letter->published_by = $this->karyawan;
            $letter->nama_penandatangan = $signer['nama'] ?: null;
            $letter->jabatan_penandatangan = $signer['jabatan'] ?: null;
            $letter->updated_by = $this->karyawan;
            $letter->updated_at = Carbon::now('Asia/Jakarta');
            $letter->save();
            $letter->refresh();

            $documentService->invalidateStoredPdf($letter);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat teguran berhasil disahkan.',
                'data' => $this->toApiPayload($this->findActiveRecord($letter->id)),
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
        GenerateQrDocumentSuratTeguran $qrService,
        SuratTeguranDocumentService $documentService
    ) {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            $letter = SuratTeguranDisipliner::query()
                ->where('id', $record->id)
                ->where('is_active', true)
                ->first();

            if ($letter === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            $linked = trim((string) ($letter->file_qr ?? ''));
            if ($linked !== '' && GenerateQrDocumentSuratTeguran::ensurePublicSvgForFile($linked)) {
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
            $record = SuratTeguranDisipliner::query()
                ->where('id', $this->resolveId($request))
                ->where('is_active', true)
                ->first();

            if ($record === null) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            if ($record->status !== SuratTeguranDisipliner::STATUS_DRAFT) {
                DB::rollBack();

                return response()->json([
                    'success' => false,
                    'message' => 'Hanya draf yang belum disahkan yang dapat dihapus.',
                ], 422);
            }

            $now = Carbon::now();
            $record->update([
                'is_active' => false,
                'status' => SuratTeguranDisipliner::STATUS_CANCELLED,
                'updated_by' => $this->karyawan,
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Surat teguran berhasil dihapus.',
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

            $data = $fixingService->regenerate(DisiplinerPdfFixingService::JENIS_ST, (int) $request->id);

            return response()->json([
                'success' => true,
                'message' => 'PDF surat teguran berhasil di-render ulang.',
                'data' => $data,
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 422);
        }
    }

    public function printPdf(Request $request, SuratTeguranDocumentService $documentService)
    {
        try {
            $record = $this->findActiveRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Surat teguran tidak ditemukan.',
                ], 404);
            }

            $letter = SuratTeguranDisipliner::query()->find($record->id);

            if (!empty($letter->file_qr)) {
                GenerateQrDocumentSuratTeguran::ensurePublicSvgForFile($letter->file_qr);
            }

            $pdfBinary = $documentService->resolvePdfBinary($letter, $record);
            $letter->refresh();

            return response()->json([
                'success' => true,
                'message' => 'PDF surat teguran siap.',
                'data' => base64_encode($pdfBinary),
                'pdf_ready' => true,
                'file_name' => $letter->nama_berkas ?: ($letter->nomor_surat . '.pdf'),
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
        return SuratTeguranDisipliner::query()
            ->from('surat_teguran_disipliner as std')
            ->leftJoin('master_karyawan as mk', 'std.karyawan_id', '=', 'mk.id')
            ->leftJoin('master_divisi as md', 'mk.id_department', '=', 'md.id')
            ->where('std.is_active', true)
            ->select(
                'std.id',
                'std.karyawan_id',
                'std.nomor_surat',
                'std.perihal',
                'std.tanggal_kejadian',
                'std.uraian_pelanggaran',
                'std.status',
                'std.nama_berkas',
                'std.file_path',
                'std.file_size',
                'std.file_qr',
                'std.nama_penandatangan',
                'std.jabatan_penandatangan',
                'std.created_by',
                'std.updated_by',
                'std.published_by',
                'std.created_at',
                'std.updated_at',
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

        return $this->baseQuery()->where('std.id', (int) $id)->first();
    }

    private function decorate($item)
    {
        if ($item === null) {
            return null;
        }

        $item->nama_karyawan = $item->nama_lengkap ?: '-';
        $item->divisi = $item->nama_divisi ?: ($item->department ?: '-');
        $item->status_label = $this->mapStatusLabel($item->status);
        $tanggalKejadian = $this->formatDateOnlyForApi(
            $item->getRawOriginal('tanggal_kejadian') ?? $item->tanggal_kejadian
        );
        $item->tanggal_kejadian_label = $tanggalKejadian
            ? $this->formatIndoLongDateLabel($tanggalKejadian)
            : '-';
        $item->tanggal_kejadian = $tanggalKejadian;
        $item->has_document = !empty($item->file_path);
        $item->has_qr = !empty($item->file_qr);
        $item->can_approve = $item->status === SuratTeguranDisipliner::STATUS_DRAFT;
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

    /** Normalisasi baris untuk API / DataTables (tanggal tanpa ISO UTC yang geser hari). */
    private function toApiPayload($item): ?array
    {
        if ($item === null) {
            return null;
        }

        $this->decorate($item);
        $tanggalKejadian = $this->formatDateOnlyForApi($item->getRawOriginal('tanggal_kejadian'));

        $payload = $item->toArray();
        $payload['tanggal_kejadian'] = $tanggalKejadian;
        unset($payload['tanggal_terbit']);

        return $payload;
    }

    private function mapStatusLabel(?string $status): string
    {
        $map = [
            SuratTeguranDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            SuratTeguranDisipliner::STATUS_ISSUED => 'Terbit',
            SuratTeguranDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? (string) $status;
    }

    private function validatePayload(Request $request, ?int $excludeLetterId = null)
    {
        $this->validate($request, [
            'karyawan_id' => 'required|integer',
            'tanggal_kejadian' => 'nullable|date',
            'perihal' => 'nullable|string|max:255',
            'uraian_pelanggaran' => 'nullable|string',
            'nomor_surat' => 'nullable|string|max:100',
            'document_number' => 'nullable|string|max:100',
        ]);

        $perihal = trim((string) ($request->perihal ?? ''));
        if ($perihal === '') {
            return response()->json([
                'success' => false,
                'message' => 'Perihal wajib diisi.',
            ], 422);
        }

        $plainDescription = trim(strip_tags((string) ($request->uraian_pelanggaran ?? '')));
        if ($plainDescription === '') {
            return response()->json([
                'success' => false,
                'message' => 'Uraian pelanggaran wajib diisi.',
            ], 422);
        }

        $tanggalKejadian = trim((string) ($request->tanggal_kejadian ?? $request->incident_date ?? ''));
        if ($tanggalKejadian === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tanggal kejadian wajib diisi (dipakai di poin 1 surat).',
            ], 422);
        }

        $nomorSurat = trim((string) ($request->nomor_surat ?? $request->document_number ?? ''));
        if ($nomorSurat === '') {
            return response()->json([
                'success' => false,
                'message' => 'Nomor surat wajib diisi.',
            ], 422);
        }

        $duplicateQuery = SuratTeguranDisipliner::query()
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

    private function parseDateOnlyInput($value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }

        return Carbon::parse($raw)->timezone('Asia/Jakarta')->format('Y-m-d');
    }

    private function formatDateOnlyForApi($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof Carbon) {
            return $value->copy()->timezone('Asia/Jakarta')->format('Y-m-d');
        }

        $raw = trim((string) $value);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return $m[1];
        }

        return Carbon::parse($raw)->timezone('Asia/Jakarta')->format('Y-m-d');
    }

    private function formatIndoLongDateLabel(string $ymd): string
    {
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $ymd, $m)) {
            $ymd = $m[1];
        }

        return Carbon::createFromFormat('Y-m-d', $ymd, 'Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('j F Y');
    }

    private function resolveNomorSuratData(Request $request): array
    {
        $nomorSurat = trim((string) ($request->nomor_surat ?? $request->document_number ?? ''));

        $year = (int) Carbon::now('Asia/Jakarta')->format('Y');
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

    private function resolveSignerFromCurrentUser(): array
    {
        if (!$this->user_id) {
            return ['nama' => '', 'jabatan' => ''];
        }

        $signer = MasterKaryawan::query()
            ->where('id', (int) $this->user_id)
            ->first();

        if ($signer === null) {
            return ['nama' => '', 'jabatan' => ''];
        }

        $namaRaw = trim((string) ($signer->nama_lengkap ?? ''));
        $nama = $namaRaw !== ''
            ? Str::title(mb_strtolower($namaRaw, 'UTF-8'))
            : '';

        return [
            'nama' => $nama,
            'jabatan' => trim((string) ($signer->jabatan ?? '')),
        ];
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
