<?php

namespace App\Http\Controllers\api;

use App\Models\OrderDetail;
use App\Models\{DataLapanganEmisiCerobong, DataLapanganEmisiKendaraan, DataLapanganIsokinetikBeratMolekul, DataLapanganIsokinetikHasil, DataLapanganIsokinetikKadarAir, DataLapanganIsokinetikPenentuanKecepatanLinier, DataLapanganIsokinetikPenentuanPartikulat, DataLapanganIsokinetikSurveiLapangan}; //Emisi Model

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DokumentasiSamplingEmisiController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::whereDate('tanggal_sampling', '<=', Carbon::now()->format('Y-m-d'))
            ->where('kategori_2', '5-Emisi')
            ->where('order_detail.is_active', true)
            ->whereNotNull('tanggal_terima')
            ->orderBy('tanggal_sampling', 'desc');
        ;

        return Datatables::of($data)
            ->orderColumn('tanggal_terima', function ($query, $order) {
                $query->orderBy('tanggal_terima', $order);
            })
            ->orderColumn('created_at', function ($query, $order) {
                $query->orderBy('created_at', $order);
            })
            ->orderColumn('no_sampel', function ($query, $order) {
                $query->orderBy('no_sampel', $order);
            })
            ->filter(function ($query) use ($request) {
                if ($request->has('columns')) {
                    $columns = $request->get('columns');
                    foreach ($columns as $column) {
                        if (isset($column['search']) && !empty($column['search']['value'])) {
                            $columnName = $column['name'] ?: $column['data'];
                            $searchValue = $column['search']['value'];

                            // Skip columns that aren't searchable
                            if (isset($column['searchable']) && $column['searchable'] === 'false') {
                                continue;
                            }

                            $query->where($columnName, 'like', '%' . $searchValue . '%');
                        }
                    }
                }
            })
            ->make(true);
    }

    public function showDokumentasi(Request $request)
    {
        $data = [];
        $dlp_emisi_cerobong = DataLapanganEmisiCerobong::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_emisi_cerobong as $d) {
            $data['data_lapangan_emisi_cerobong'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_struk' => $d->foto_struk,
                    'foto_lain_2' => $d->foto_lain2,
                    'foto_asap' => $d->foto_asap,
                    'foto_lain_3' => $d->foto_lain3,
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_emisi_kendaraan = DataLapanganEmisiKendaraan::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_emisi_kendaraan as $d) {
            $data['data_lapangan_emisi_kendaraan'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_depan' => $d->foto_depan,
                    'foto_belakang' => $d->foto_belakang,
                    'foto_sampling' => $d->foto_sampling
                ]
            ];
        }

        $dlp_isokinetik_berat_molekul = DataLapanganIsokinetikBeratMolekul::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_isokinetik_berat_molekul as $d) {
            $data['data_lapangan_isokinetik_berat_molekul'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_isokinetik_hasil = DataLapanganIsokinetikHasil::where('no_sampel', $request->no_sampel)
            ->select('id_lapangan', 'foto_lokasi_sampel', 'foto_kondisi_sampel', 'foto_lain')
            ->get();

        $hasil_ids = $dlp_isokinetik_hasil->pluck('id_lapangan');

        $dlp_isokinetik_survei_lapangan = DataLapanganIsokinetikSurveiLapangan::whereIn('id', $hasil_ids)
            ->get();


        foreach ($dlp_isokinetik_hasil as $d) {
            $data['data_lapangan_isokinetik_hasil'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }


        foreach ($dlp_isokinetik_survei_lapangan as $d) {
            $data['data_lapangan_isokinetik_survei_lapangan'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_isokinetik_kadar_air = DataLapanganIsokinetikKadarAir::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_isokinetik_kadar_air as $d) {
            $data['data_lapangan_isokinetik_kadar_air'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_isokinetik_penentuan_kecepatan_linier = DataLapanganIsokinetikPenentuanKecepatanLinier::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_isokinetik_penentuan_kecepatan_linier as $d) {
            $data['data_lapangan_isokinetik_penentuan_kecepatan_linier'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_isokinetik_penentuan_partikulat = DataLapanganIsokinetikPenentuanPartikulat::where('no_sampel', $request->no_sampel)
            ->select('foto_lokasi_sampel', 'foto_kondisi_sampel', 'foto_lain')
            ->get();

        foreach ($dlp_isokinetik_penentuan_partikulat as $d) {
            $data['data_lapangan_isokinetik_penentuan_partikulat'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }



        return response()->json([
            'data' => $data
        ], 200);
    }
}
