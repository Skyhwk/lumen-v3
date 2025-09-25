<?php

namespace App\Http\Controllers\api;

use App\Models\LingkunganHeader;
use App\Models\OrderDetail;
use App\Models\{DataLapanganDebuPersonal, DatalapanganCahaya, DataLapanganDirectLain, DataLapanganErgonomi, DataLapanganGetaran, DataLapanganGetaranPersonal, DataLapanganIklimPanas, DataLapanganIklimDingin, DetailLingkunganHidup, DetailLingkunganKerja, DataLapanganKebisingan, DataLapanganKebisinganPersonal, DataLapanganMedanLM, DetailMicrobiologi, DetailSenyawaVolatile, DataLapanganSinarUV, DataLapanganSwab}; // Udara Model

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class DokumentasiSamplingUdaraController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::whereDate('tanggal_sampling', '<=', Carbon::now()->format('Y-m-d'))
            ->where('kategori_2', '4-Udara')
            ->where('order_detail.is_active', true)
            ->whereNotNull('tanggal_terima')
            ->orderBy('tanggal_sampling', 'desc');



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
        $dlp_debu_personal = DataLapanganDebuPersonal::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_debu_personal as $d) {
            $data['data_lapangan_debu_personal'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_alat' => $d->foto_alat,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_cahaya = DataLapanganCahaya::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_cahaya as $d) {
            $data['data_lapangan_cahaya'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_direct_lain = DataLapanganDirectLain::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_direct_lain as $d) {
            $data['data_lapangan_direct_lain'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_ergonomi = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_ergonomi as $d) {
            $data['data_lapangan_ergonomi'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_depan' => $d->foto_depan,
                    'foto_belakangan' => $d->foto_belakangan,
                    'foto_samping_kiri' => $d->foto_samping_kiri,
                    'foto_samping_kanan' => $d->foto_samping_kanan
                ]
            ];
        }

        $dlp_getaran = DataLapanganGetaran::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_getaran as $d) {
            $data['data_lapangan_getaran'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_getaran_personal = DataLapanganGetaranPersonal::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_getaran_personal as $d) {
            $data['data_lapangan_getaran_personal'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_iklim_panas = DataLapanganIklimPanas::where('no_sampel', $request->no_sampel)
            ->select('foto_lokasi_sampel', 'foto_lain')
            ->get();

        foreach ($dlp_iklim_panas as $d) {
            $data['data_lapangan_iklim_panas'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_iklim_dingin = DataLapanganIklimDingin::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_iklim_dingin as $d) {
            $data['data_lapangan_iklim_dingin'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dl_lingkungan_hidup = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dl_lingkungan_hidup as $d) {
            $data['data_lapangan_lingkungan_hidup'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dl_lingkungan_kerja = DetailLingkunganKerja::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dl_lingkungan_kerja as $d) {
            $data['data_lapangan_lingkungan_kerja'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_kebisingan = DataLapanganKebisingan::where('no_sampel', $request->no_sampel)
            ->select('foto_lokasi_sampel', 'foto_lain')
            ->get();

        foreach ($dlp_kebisingan as $d) {
            $data['data_lapangan_kebisingan'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_kebisingan_personal = DataLapanganKebisinganPersonal::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_kebisingan_personal as $d) {
            $data['data_lapangan_kebisingan_personal'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_medan_lm = DataLapanganMedanLM::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_medan_lm as $d) {
            $data['data_lapangan_medan_lm'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dl_microbiologi = DetailMicrobiologi::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dl_microbiologi as $d) {
            $data['data_lapangan_microbiologi'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dl_senyawa_volatile = DetailSenyawaVolatile::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dl_senyawa_volatile as $d) {
            $data['data_lapangan_senyawa_volatile'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_kondisi_sampel' => $d->foto_kondisi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_sinar_uv = DataLapanganSinarUV::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_sinar_uv as $d) {
            $data['data_lapangan_sinar_uv'][] = (object) [
                'dokumentasi' => (object) [
                    'foto_lokasi_sampel' => $d->foto_lokasi_sampel,
                    'foto_lain' => $d->foto_lain
                ]
            ];
        }

        $dlp_swab = DataLapanganSwab::where('no_sampel', $request->no_sampel)
            ->get();

        foreach ($dlp_swab as $d) {
            $data['data_lapangan_swab'][] = (object) [
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
