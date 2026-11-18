<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\BeritaAcaraKejadianDisipliner;
use App\Services\MenuDataAccessService;
use DataTables;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RekapBAKController extends Controller
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
                Str::slug('Rekap BAK'),
            );

            if ($isProcessed) {
                $query->where('proses_status', 'sudah_diproses');
            } else {
                $query->where(function ($q) {
                    $q->whereNull('proses_status')
                        ->orWhere('proses_status', '')
                        ->orWhere('proses_status', '!=', 'sudah_diproses');
                });
            }

            $data = $query->orderByDesc('created_at')->get();

            return DataTables::of($data)->make(true);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to get data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
