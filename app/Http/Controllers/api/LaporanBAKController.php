<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Services\BakLaporanService;
use App\Services\BeritaAcaraKejadianDocumentService;
use App\Services\GenerateQrDocumentBeritaAcaraKejadian;
use App\Services\MenuDataAccessService;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LaporanBAKController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->has('is_processed')) {
            return response()->json([
                'message' => 'Status (processed/unprocessed) is required',
            ], 400);
        }

        $isProcessed = filter_var($request->input('is_processed'), FILTER_VALIDATE_BOOLEAN)
            || (string) $request->input('is_processed') === '1';

        try {
            $query = BeritaAcaraKejadianDisipliner::query()
                ->where('berita_acara_kejadian_disipliner.is_active', true);

            MenuDataAccessService::applyKaryawanGradeFilter(
                $query,
                Str::slug('Laporan BAK'),
                'penandatangan',
            );

            BakLaporanService::applyLaporanTabFilter($query, $isProcessed);

            $data = $query->orderByDesc('created_at')->get();

            return DataTables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function detail(Request $request)
    {
        try {
            $record = $this->findAccessibleRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau Anda tidak memiliki akses.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->toDetailPayload($record),
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    public function printPdf(Request $request, BeritaAcaraKejadianDocumentService $documentService)
    {
        try {
            $this->validate($request, ['id' => 'required|integer']);

            $record = $this->findAccessibleRecord($this->resolveId($request));
            if ($record === null) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan atau Anda tidak memiliki akses.',
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
            ], 200);
        } catch (\Throwable $ex) {
            return response()->json([
                'success' => false,
                'message' => $ex->getMessage(),
            ], 500);
        }
    }

    private function findAccessibleRecord(int $id): ?BeritaAcaraKejadianDisipliner
    {
        if ($id <= 0) {
            return null;
        }

        $query = BeritaAcaraKejadianDisipliner::query()
            ->where('is_active', true)
            ->where('id', $id);

        MenuDataAccessService::applyKaryawanGradeFilter(
            $query,
            Str::slug('Laporan BAK'),
            'penandatangan',
        );

        return $query->first();
    }

    private function toDetailPayload(BeritaAcaraKejadianDisipliner $record): array
    {
        $status = strtolower(trim((string) ($record->status ?? '')));

        return [
            'id' => (int) $record->id,
            'nomor_surat' => (string) ($record->nomor_surat ?? ''),
            'status' => $status,
            'status_label' => $this->mapStatusLabel($status),
            'proses_status' => (string) ($record->proses_status ?? ''),
            'proses_status_label' => $this->mapProsesStatusLabel($record->proses_status ?? ''),
            'penandatangan_nama' => (string) ($record->penandatangan_nama ?? ''),
            'penandatangan_divisi' => (string) ($record->penandatangan_divisi ?? ''),
            'perihal' => (string) ($record->perihal ?? ''),
            'tanggal_kejadian' => $record->tanggal_kejadian,
            'nama_berkas' => (string) ($record->nama_berkas ?? ''),
            'file_path' => (string) ($record->file_path ?? ''),
            'created_by' => (string) ($record->created_by ?? ''),
            'created_at' => $record->created_at,
            'updated_at' => $record->updated_at,
        ];
    }

    private function mapStatusLabel(string $status): string
    {
        $map = [
            BeritaAcaraKejadianDisipliner::STATUS_DRAFT => 'Menunggu pengesahan',
            BeritaAcaraKejadianDisipliner::STATUS_ISSUED => 'Terbit',
            BeritaAcaraKejadianDisipliner::STATUS_CANCELLED => 'Dibatalkan',
        ];

        return $map[$status] ?? ($status !== '' ? $status : '-');
    }

    private function mapProsesStatusLabel(?string $prosesStatus): string
    {
        $key = strtolower(trim((string) $prosesStatus));
        $map = [
            BeritaAcaraKejadianDisipliner::PROSES_PERMINTAAN => 'Permintaan dokumen',
            BeritaAcaraKejadianDisipliner::PROSES_VERIFIKASI_ATASAN => 'Verifikasi atasan',
            BeritaAcaraKejadianDisipliner::PROSES_MENUNGGU_DIPROSES => 'Menunggu diproses',
            BeritaAcaraKejadianDisipliner::PROSES_SEDANG_DIPROSES => 'Sedang diproses',
            BeritaAcaraKejadianDisipliner::PROSES_SUDAH_DIPROSES => 'Sudah diproses',
        ];

        return $map[$key] ?? ($key !== '' ? $key : '-');
    }

    private function resolveId(Request $request): int
    {
        return (int) ($request->input('id') ?: $request->input('document_id') ?: 0);
    }
}