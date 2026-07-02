<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

class TrackingFdlController extends Controller
{   
    public function getInputtedFdl(Request $request) {
        try {
        $data = OrderDetail::select('no_sampel', 'tanggal_sampling', 'kategori_3', 'parameter', 'keterangan_1')
            ->withAnyDataLapangan()
            ->where('is_active', 1)
            ->whereMonth('tanggal_sampling', $request->bulan)
            ->whereYear('tanggal_sampling', $request->tahun)
            ->get();

        if ($data->isEmpty()) {
            return DataTables::of([])->make(true);
        }

        $rows = [];

        foreach ($data as $orderDetail) {
            $namaSampler = null;
            $waktuSubmitFdl = null;

            foreach ($orderDetail->getAnyDataLapanganRelations() as $relation) {
                if (!$orderDetail->relationLoaded($relation) || !$orderDetail->{$relation}) {
                    continue;
                }

                $relasi = $orderDetail->{$relation};
                $items = $relasi instanceof \Illuminate\Database\Eloquent\Collection
                    ? $relasi
                    : collect([$relasi]);

                foreach ($items as $item) {
                    $createdBy = $item->created_by ?? null;
                    $createdAt = $item->created_at ?? null;

                    if ($createdBy !== null || $createdAt !== null) {
                        $namaSampler = $createdBy;
                        $waktuSubmitFdl = $createdAt;
                        break 2;
                    }
                }
            }

            if ($namaSampler === null && $waktuSubmitFdl === null) {
                continue;
            }

            $rows[] = [
                'no_sampel' => $orderDetail->no_sampel,
                'tanggal_sampling' => $orderDetail->tanggal_sampling,
                'kategori_3' => $orderDetail->kategori_3,
                'parameter' => count(json_decode($orderDetail->parameter)),
                'keterangan_1' => $orderDetail->keterangan_1,
                'sampler' => $namaSampler,
                'tanggal_input_fdl' => $waktuSubmitFdl,
            ];
        }

            return DataTables::of($rows)->make(true);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }
    
    public function getNotInputtedFdl(Request $request) {
        try {
            $data = OrderDetail::select('no_sampel', 'tanggal_sampling', 'kategori_3', 'parameter', 'keterangan_1')
                ->withAnyDataLapangan()
                ->where('is_active', 1)
                ->whereMonth('tanggal_sampling', $request->bulan)
                ->whereYear('tanggal_sampling', $request->tahun)
                ->get();

            if ($data->isEmpty()) {
                return DataTables::of([])->make(true);
            }

            $rows = [];

            foreach ($data as $orderDetail) {
                $namaSampler = null;
                $waktuSubmitFdl = null;

                foreach ($orderDetail->getAnyDataLapanganRelations() as $relation) {
                    if (!$orderDetail->relationLoaded($relation) || !$orderDetail->{$relation}) {
                        continue;
                    }

                    $relasi = $orderDetail->{$relation};
                    $items = $relasi instanceof \Illuminate\Database\Eloquent\Collection
                        ? $relasi
                        : collect([$relasi]);

                    foreach ($items as $item) {
                        $createdBy = $item->created_by ?? null;
                        $createdAt = $item->created_at ?? null;

                        if ($createdBy !== null || $createdAt !== null) {
                            $namaSampler = $createdBy;
                            $waktuSubmitFdl = $createdAt;
                            break 2;
                        }
                    }
                }

                if ($namaSampler !== null || $waktuSubmitFdl !== null) {
                    continue;
                }

                $rows[] = [
                    'no_sampel' => $orderDetail->no_sampel,
                    'tanggal_sampling' => $orderDetail->tanggal_sampling,
                    'kategori_3' => $orderDetail->kategori_3,
                    'parameter' => count(json_decode($orderDetail->parameter)),
                    'keterangan_1' => $orderDetail->keterangan_1,
                    'sampler' => null,
                    'tanggal_input_fdl' => null,
                ];
            }

            return DataTables::of($rows)->make(true);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }

    public function getAvailableYears(Request $request) {
        try {
            $years = OrderDetail::where('is_active', true)
                ->whereNotNull('tanggal_sampling')
                ->selectRaw('DISTINCT YEAR(tanggal_sampling) as tahun')
                ->orderBy('tahun', 'desc')
                ->pluck('tahun');

            return response()->json([
                'success' => true,
                'data' => $years
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage()
            ], 500);
        }
    }
}