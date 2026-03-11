<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Datatables;

use App\Models\MasterPelanggan;
use App\Models\KontakPelanggan;
use App\Models\AlamatPelanggan;
use App\Models\PicPelanggan;
use App\Models\MasterKaryawan;
use App\Models\HargaTransportasi;
use App\Models\HistoryPerubahanSales;
use App\Models\KontakPelangganBlacklist;
use App\Models\MasterPelangganBlacklist;
use App\Models\MasterRegulasi;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\request_quotationKontrakD;
use Illuminate\Support\Carbon;
use Symfony\Component\VarDumper\Cloner\Data;

date_default_timezone_set('Asia/Jakarta');

class RegulationOrderController extends Controller
{
    public function index(Request $request)
    {
        $tahun = 2025;

        $kontrak = QuotationKontrakH::query()
            ->where('is_active', true)
            ->where('flag_status', 'Ordered')
            ->whereHas('detail', function ($q) use ($tahun) {
                $q->whereYear('periode_kontrak', $tahun);
            })
            ->with(['detail' => function ($q) use ($tahun) {
                $q->whereYear('periode_kontrak', $tahun);
            }])
            ->get();

        $nonkontrak = QuotationNonKontrak::query()
            ->where('is_active', true)
            ->where('flag_status', 'Ordered')
            ->whereYear('created_at', $tahun)
            ->get();

        $kontrakDetails = $kontrak->pluck('detail')->flatten()->values();
        $all = $nonkontrak->concat($kontrakDetails)->values();

        // month keys HARUS string 2 digit
        $monthsTemplate = [
            '01' => 0,
            '02' => 0,
            '03' => 0,
            '04' => 0,
            '05' => 0,
            '06' => 0,
            '07' => 0,
            '08' => 0,
            '09' => 0,
            '10' => 0,
            '11' => 0,
            '12' => 0,
            '13' => 0
        ];

        // bikin format regulasi + bulan (PAKE operator + biar gak reindex key)
        $formattedRegulation = MasterRegulasi::query()->with('bakumutu')
            ->where('is_active', true)
            ->get()
            ->keyBy('id')
            ->map(function ($row) use ($monthsTemplate) {
                return ['regulasi' => $row->peraturan] + ['parameter' => $row->bakumutu] + $monthsTemplate;
            })
            ->toArray();

        foreach ($all as $row) {
            $payload = json_decode($row->data_pendukung_sampling ?? '[]', true);
            if (!is_array($payload)) continue;

            $dateStr = $row->periode_kontrak
                ?? $row->tanggal_penawaran
                ?? $row->created_at
                ?? null;

            if (!$dateStr) continue;

            try {
                $date = Carbon::parse($dateStr);
            } catch (\Throwable $e) {
                continue; // skip kalau tanggal invalid
            }

            // 🔥 kalau tahunnya beda, skip
            if ($date->year != $tahun) continue;

            // ambil bulan 2 digit
            $bulan = $date->format('m');

            foreach ($payload as $item) {
                if (!is_array($item)) continue;

                $jumlah = (int) (count($item['penamaan_titik']) ?? 0);
                $regs = $item['regulasi'] ?? [];
                if (!is_array($regs) || $jumlah == 0) continue;

                foreach ($regs as $regStr) {
                    if (!is_string($regStr)) continue;

                    $regId = (int) explode('-', $regStr)[0];
                    if ($regId <= 0) continue;

                    if (!isset($formattedRegulation[$regId])) continue;

                    // safety: kalau key bulan ga ada, skip (harusnya ga kejadian)
                    if (!array_key_exists($bulan, $formattedRegulation[$regId])) continue;

                    $formattedRegulation[$regId][$bulan] += $jumlah;
                    $formattedRegulation[$regId]['13'] += $jumlah;
                }
            }
        }

        $rows = collect($formattedRegulation)
            ->sortByDesc('13')
            ->values()
            ->toArray();

        return \DataTables::of($rows)->make(true);
    }
}
