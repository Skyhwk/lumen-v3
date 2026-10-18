<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\GaAssetImportBatch;
use App\Services\GaAsset\GaAssetExcelImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Yajra\DataTables\DataTables;

class GaAssetImportController extends Controller
{
    public function index()
    {
        $query = GaAssetImportBatch::query()
            ->orderByDesc('id');

        return DataTables::of($query)->make(true);
    }

    public function showErrors(Request $request)
    {
        $batchId = (int) $request->input('batch_id');
        $batch = GaAssetImportBatch::with(['errors' => function ($q) {
            $q->orderBy('row_number');
        }])->findOrFail($batchId);

        return response()->json([
            'batch' => $batch,
            'errors' => $batch->errors,
        ]);
    }

    public function preview(Request $request, GaAssetExcelImportService $service)
    {
        return $this->handleUpload($request, $service, false);
    }

    public function commit(Request $request, GaAssetExcelImportService $service)
    {
        return $this->handleUpload($request, $service, true);
    }

    private function handleUpload(Request $request, GaAssetExcelImportService $service, bool $commit)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => 'Format file tidak valid. Gunakan .xlsx atau .xls'], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $mode = strtolower(trim((string) $request->input('mode', 'full')));
        if (!in_array($mode, ['full', 'financial'], true)) {
            return response()->json(['message' => 'Mode import tidak valid. Gunakan full atau financial.'], 422);
        }

        try {
            $result = $service->importFromPath(
                $file->getRealPath(),
                $originalName,
                $commit,
                $this->user_id ? (int) $this->user_id : null,
                is_string($this->karyawan) ? $this->karyawan : null,
                $mode
            );
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gagal memproses Excel: ' . $e->getMessage(),
            ], 500);
        }

        $stats = $result['stats'];
        $failed = (int) ($stats['rows_failed'] ?? 0);

        if ($commit) {
            if ($mode === 'financial') {
                $message = sprintf(
                    'Update harga & umur selesai. Diperbarui: %d, dilewati: %d, tidak ditemukan: %d, gagal: %d. Depresiasi dihitung ulang.',
                    $stats['rows_updated'],
                    $stats['rows_skipped'],
                    (int) ($stats['rows_not_found'] ?? 0),
                    $failed
                );
            } else {
                $message = sprintf(
                    'Import selesai. Baru: %d, diperbarui: %d, dilewati: %d, gagal: %d.',
                    $stats['rows_imported'],
                    $stats['rows_updated'],
                    $stats['rows_skipped'],
                    $failed
                );
            }

            return response()->json(array_merge($result, [
                'message' => $message,
            ]));
        }

        if ($mode === 'financial') {
            $message = sprintf(
                'Preview harga & umur (belum disimpan). Akan diperbarui: %d, dilewati: %d, tidak ditemukan: %d, gagal: %d.',
                $stats['rows_updated'],
                $stats['rows_skipped'],
                (int) ($stats['rows_not_found'] ?? 0),
                $failed
            );
        } else {
            $message = sprintf(
                'Preview selesai (belum disimpan). Akan insert: %d, update: %d, gagal validasi: %d.',
                $stats['rows_imported'],
                $stats['rows_updated'],
                $failed
            );
        }

        return response()->json(array_merge($result, [
            'message' => $message,
        ]));
    }
}
