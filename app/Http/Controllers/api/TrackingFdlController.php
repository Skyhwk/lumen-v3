<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;

use App\Models\OrderDetail;
use App\Models\Jadwal;

class TrackingFdlController extends Controller
{   
    public function getInputtedFdl(Request $request) {
        try {
        $data = OrderDetail::select('no_sampel', 'tanggal_sampling', 'kategori_3', 'parameter', 'keterangan_1', 'no_quotation')
            ->withAnyDataLapangan()
            ->whereNotNull('tanggal_terima')
            ->where('is_active', 1)
            ->whereMonth('tanggal_sampling', $request->bulan)
            ->whereYear('tanggal_sampling', $request->tahun)
            ->whereNotIn('kategori_1', ['SD', 'SP'])
            ->get();

        if ($data->isEmpty()) {
            return DataTables::of([])->make(true);
        }

        $rows = [];

        
        foreach ($data as $orderDetail) {
            $namaSampler = null;
            $waktuSubmitFdl = [];

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

                    if ($createdBy !== null) {
                        $namaSampler = $createdBy;
                    }
         
                    if ($createdAt !== null) {
                        $waktuSubmitFdl[] = $createdAt;
                    }
                }
            }

            if (empty($namaSampler) && empty($waktuSubmitFdl)) {
                continue;
            }

            $groupedWaktu = [];

             foreach ($waktuSubmitFdl as $time) {
                if ($time) {
                    $timestamp = strtotime($time);
                    $date = date('Y-m-d', $timestamp);
                    $timeOnly = date('H:i:s', $timestamp);

                    if (!isset($groupedWaktu[$date])) {
                        $groupedWaktu[$date] = [];
                    }
                    if (!in_array($timeOnly, $groupedWaktu[$date])) {
                        $groupedWaktu[$date][] = $timeOnly;
                    }
                }
            }

            ksort($groupedWaktu);
            foreach ($groupedWaktu as &$times) {
                sort($times);
            }
            unset($times);

            $rows[] = [
                'no_sampel' => $orderDetail->no_sampel,
                'tanggal_sampling' => $orderDetail->tanggal_sampling,
                'kategori_3' => $orderDetail->kategori_3,
                'parameter' => count(json_decode($orderDetail->parameter)),
                'keterangan_1' => $orderDetail->keterangan_1,
                'sampler' => $namaSampler,
                'tanggal_input_fdl' => $groupedWaktu,
                'no_quotation' => $orderDetail->no_quotation,
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
            $now = Carbon::now();
            $isCurrentMonth = ((int) $request->bulan === (int) $now->month && (int) $request->tahun === (int) $now->year);

            $query = OrderDetail::with('orderHeader:id,is_revisi')
                ->select('no_sampel', 'tanggal_sampling', 'kategori_3', 'parameter', 'keterangan_1', 'no_quotation', 'nama_perusahaan', 'id_order_header')
                ->whereNull('tanggal_terima')
                ->where('order_detail.is_active', 1)
                ->whereMonth('tanggal_sampling', $request->bulan)
                ->whereYear('tanggal_sampling', $request->tahun)
                ->whereNotIn('kategori_1', ['SD', 'SP'])
                ->whereNotIn('kategori_3', ['118-Psikologi']);

            if ($isCurrentMonth) {
                $query->whereDate('tanggal_sampling', '<', $now->toDateString());
            }

            $data = $query->get();

            if ($data->isEmpty()) {
                return DataTables::of([])->make(true);
            }

            $uniqueQuotations = $data->pluck('no_quotation')->unique()->filter();

            // Fix: group the query by the non-aggregated columns per SQL standard. 
            $jadwalsByQuotation = Jadwal::select(
                    'no_quotation',
                    'kategori',
                    DB::raw('group_concat(sampler) as sampler'),
                    'tanggal',
                    'durasi'
                )
                ->whereIn('no_quotation', $uniqueQuotations)
                ->where('is_active', 1)
                ->groupBy('no_quotation', 'kategori', 'tanggal', 'durasi')
                ->get()
                ->groupBy('no_quotation');
        

            $rows = $data->map(function ($orderDetail) use ($jadwalsByQuotation) {
                $kategoriParts = explode('-', $orderDetail->kategori_3);
                $sampelParts = explode('/', $orderDetail->no_sampel);
                $kategoriNeedle = ($kategoriParts[1] ?? '') . ' - ' . ($sampelParts[1] ?? '');

                $sampler = null;
                $quotationJadwals = $jadwalsByQuotation->get($orderDetail->no_quotation);
                if ($quotationJadwals) {
                    $match = $quotationJadwals->first(function ($jadwal) use ($kategoriNeedle) {
                        return str_contains($jadwal->kategori, $kategoriNeedle);
                    });
                    $sampler = $match ? $match->sampler : null;
                } else {
                    $noQuotation = $orderDetail->no_quotation;

                    if (preg_match('/R(\d+)$/', $noQuotation, $matches)) {
                        $noQuotation = preg_replace(
                            '/R\d+$/',
                            'R' . ((int)$matches[1] + 1),
                            $noQuotation
                        );
                    } else {
                        $noQuotation .= 'R1';
                    }

                    $quotationJadwal = $jadwalsByQuotation->get($noQuotation);

                    if ($quotationJadwal) {
                        $matchs = $quotationJadwal->first(function ($jadwal) use ($kategoriNeedle) {
                            return str_contains($jadwal->kategori, $kategoriNeedle);
                        });
                        $sampler = $matchs ? $matchs->sampler : null;
                    }
                }

                $parameter = json_decode($orderDetail->parameter, true);

                // Fix: Avoid error by ensuring orderHeader exists
                $isRevisi = null;
                if (isset($orderDetail->orderHeader) && is_object($orderDetail->orderHeader)) {
                    $isRevisi = $orderDetail->orderHeader->is_revisi ?? null;
                }

                return [
                    'is_revisi' => $isRevisi,
                    'nama_pelanggan' => $orderDetail->nama_perusahaan,
                    'no_sampel' => $orderDetail->no_sampel,
                    'tanggal_sampling' => $orderDetail->tanggal_sampling,
                    'kategori_3' => $orderDetail->kategori_3,
                    'parameter' => is_array($parameter) ? count($parameter) : 0,
                    'keterangan_1' => $orderDetail->keterangan_1,
                    'sampler' => $sampler,
                    'tanggal_input_fdl' => null,
                    'no_quotation' => $orderDetail->no_quotation,
                ];
            })->all();

            return response()->json([
                'success' => true,
                'data' => $rows
            ]);
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