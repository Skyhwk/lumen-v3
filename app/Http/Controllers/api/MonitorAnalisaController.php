<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\MasterKategori;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\Ftc;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\Titrimetri;
use App\Models\Subkontrak;
use App\Models\DustFallHeader;
use App\Models\DebuPersonalHeader;
use App\Models\LingkunganHeader;
use App\Models\MicrobioHeader;
use App\Models\DirectLainHeader;
use App\Models\PartikulatHeader;
use App\Models\EmisiCerobongHeader;

class MonitorAnalisaController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->kategori;
        $date     = $request->date;

        // =============================
        // Pengelompokan berdasarkan kategori
        // =============================
        $kategoriPencarian = [
            '4-Udara'  => ['11-Udara Ambient', '27-Udara Lingkungan Kerja', '12-Udara Angka Kuman', '46-Udara Swab Test'],
            '5-Emisi'  => ['34-Emisi Sumber Tidak Bergerak'],
        ];
        $pencarian = $kategoriPencarian[$kategori] ?? null;

        // =============================
        // Ambil no_sampel valid
        // =============================
        $noSampel = OrderDetail::where([
                'kategori_2' => $kategori,
                'order_detail.is_active'  => true,
        ]);
        if($pencarian){
            $noSampel = $noSampel->whereIn('kategori_3', $pencarian);
        }
        $noSampel = $noSampel->join('t_ftc', 't_ftc.no_sample', '=', 'order_detail.no_sampel')
        ->where('t_ftc.ftc_laboratory', 'like', "%{$date}%")
        ->pluck('order_detail.no_sampel')
        ->toArray();

        // =============================
        // Ambil parameter yang sudah diuji
        // =============================
        $subQuery = collect();

        if (in_array($kategori, ['1-Air', '6-Padatan'])) {
            $models = [
                Colorimetri::class,
                Gravimetri::class,
                Titrimetri::class,
                Subkontrak::class,
            ];

            $subQuery = collect($models)
                ->flatMap(fn ($model) =>
                    $model::where('is_active', true)
                        ->whereIn('no_sampel', $noSampel)
                        ->get(['no_sampel', 'parameter'])
                )
                ->groupBy('no_sampel')
                ->map(fn ($items) =>
                    $items->pluck('parameter')->implode(',')
                );
        } else if ($kategori == '4-Udara') {
            $models = [
                DustFallHeader::class,
                DebuPersonalHeader::class,
                LingkunganHeader::class,
                MicrobioHeader::class,
                Subkontrak::class,
                DirectLainHeader::class,
                PartikulatHeader::class,
            ];

            $subQuery = collect($models)
                ->flatMap(fn ($model) =>
                    $model::where('is_active', true)
                        ->whereIn('no_sampel', $noSampel)
                        ->get(['no_sampel', 'parameter'])
                )
                ->groupBy('no_sampel')
                ->map(fn ($items) =>
                    $items->pluck('parameter')->implode(',')
                );
        } else if ($kategori == '5-Emisi') {
            $models = [
                EmisiCerobongHeader::class
            ];
            $subQuery = collect($models)
                ->flatMap(fn ($model) =>
                    $model::where('is_active', true)
                        ->whereIn('no_sampel', $noSampel)
                        ->get(['no_sampel', 'parameter'])
                )
                ->groupBy('no_sampel')
                ->map(fn ($items) =>
                    $items->pluck('parameter')->implode(',')
                );
            
        }

        // =============================
        // Ambil order detail utama
        // =============================
        $parameterExcluded = $this->parameterExcluded($kategori);
        
        $data = OrderDetail::select('no_sampel', 'parameter', 'tanggal_terima', 'kategori_3', 't_ftc.ftc_verifier', 't_ftc.ftc_laboratory')
            ->where([
                'kategori_2' => $kategori,
                'order_detail.is_active'  => true,
            ]);
            if($pencarian){
                $data = $data->whereIn('kategori_3', $pencarian);
            }
            $data = $data->join('t_ftc', 't_ftc.no_sample', '=', 'order_detail.no_sampel')
            ->where('t_ftc.ftc_laboratory', 'like', "%{$date}%")
            ->orderBy('t_ftc.ftc_laboratory', 'ASC')
            ->get()
            ->map(function ($item) use ($subQuery, $parameterExcluded) {
                // Semua parameter order
                $paramAll = collect(json_decode($item->parameter))
                    ->map(fn ($p) => trim(explode(';', $p)[1]));

                // Jika belum ada yang diuji
                if (!$subQuery->has($item->no_sampel)) {
                    $item->parameter_belum_diuji = $paramAll->reject(fn ($p) => in_array(strtolower($p), $parameterExcluded))->values();
                    return $item;
                }

                // Parameter yang sudah diuji
                $paramTested = collect(explode(',', $subQuery[$item->no_sampel]))
                    ->map(fn ($p) => trim($p));

                // Hitung yang belum diuji
                $belumDiuji = $paramAll
                    ->diff($paramTested)
                    ->reject(fn ($p) => in_array(strtolower($p), $parameterExcluded))
                    ->values();

                if ($belumDiuji->isNotEmpty()) {
                    $item->parameter_belum_diuji = $belumDiuji;
                    return $item;
                }

                return null;
            })
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'data'    => $data,
            'message' => 'Data retrieved successfully',
        ]);
    }
    
    private function parameterExcluded($kategori)
    {
        if($kategori == '1-Air'){
            return [
                'ph',
                'suhu',
                'suhu (na)',
                'dhl',
                'debit air',
                'debit air (m3/ton)',
                'debit air (m3/hari)',
                'debit air (l/orang/hari)',
                'debit air (l/kg)',
                'debit air (l/l)',
                'debit air (m3/l)',
                'debit air (l/hari)',
                'debit air (m3/dtk)',
                'debit air (l/dtk)',
                'debit air (l/jam)',
                'debit air (l/hari)',
            ];
        } else if ($kategori == '4-Udara') {
            return [
                'suhu',
                'kelembaban',
                'o2',
                'co2',
                'co2 (24 jam)',
                'co2 (8 jam)',
                'c o',
                'voc',
                'voc (8 jam)',
                'hcho',
                'hcho (8 jam)',
                'h2co',
                'tekanan udara',
                'pertukaran udara',
                'laju ventilasi',
                'laju ventilasi (8 jam)'
            ];
        } else if ($kategori == '5-Emisi') {
            return [
                'suhu',
                'co2 (estb)',
                'o2 (estb)',
                'opasitas (estb)',
                'velocity',
                'co2',
                'o2',
                'opasitas',
                'no2',
                'no-no2',
                'nox',
                'nox-no2',
                'no',
                'so2',
                'co',
                'c o',
                'tekanan udara',
                'so2 (p)',
                'co (p)',
                'o2 (p)',
                'no2-nox (p)',
                'effisiensi pembakaran',
                'eff. pembakaran'
            ];
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Available data category retrieved successfully',
        ], 201);
    }
}