<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;
use App\Models\MasterBakumutu;
use App\Models\MasterKategori;
use App\Models\MasterRegulasi;
use App\Models\MasterSubKategori;
use App\Models\Parameter;

class PromoPengujianController extends Controller {
    public function index(Request $request) {
        $query = DB::table('promo_pengujian')
            ->select('id', 'kode_promo', 'metode', 'nama_diskon', 'konfigurasi', 'status')
            ->when($request->filled('status'), function ($q) use ($request) {
                $q->where('status', $request->status);
            })
            ->when($request->filled('metode'), function ($q) use ($request) {
                $q->where('metode', $request->metode);
            })
            ->orderBy('id', 'desc');

        return DataTables::of($query)
            ->filterColumn('kode_promo', function ($q, $keyword) {
                $q->where('kode_promo', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_diskon', function ($q, $keyword) {
                $q->where('nama_diskon', 'like', "%{$keyword}%");
            })
            ->filterColumn('metode', function ($q, $keyword) {
                $q->where('metode', 'like', "%{$keyword}%");
            })
            ->filterColumn('status', function ($q, $keyword) {
                $q->where('status', 'like', "%{$keyword}%");
            })
            ->make(true);
    }

    public function create (Request $request) {
        $kodePromo = $request->input('kode_promo');
        $metode = $request->input('metode');
        $namaDiskon = $request->input('nama_diskon');

        $cekKode = DB::table('promo_pengujian')->where('kode_promo', $kodePromo)->first();
        if ($cekKode) {
            return response()->json([
                'message' => 'Kode promo sudah terdaftar'
            ], 422);
        }

        $konfigurasi = [];

        if ($metode === "persentase") {
            $dasarDiskon = $request->input('dasar_diskon');
            $persentase = $request->input('persentase');

            if (empty($kodePromo) || empty($metode) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo, metode, dan nama diskon tidak boleh kosong'
                ]);
            }
            
            if (empty($dasarDiskon) || empty($persentase)) {
                return response()->json([
                    'message' => 'Dasar diskon dan persentase tidak boleh kosong'
                ]);
            }

            $konfigurasi = [
                'dasar_diskon' => $dasarDiskon,
                'persentase'   => is_numeric($persentase) ? +$persentase : $persentase,
            ];
        } else if ($metode === 'paket_pengujian' || ($metode === 'pengujian_gratis')) {
            if (empty($kodePromo)) {
                return response()->json([
                    'message' => 'Kode promo tidak boleh kosong'
                ], 422);
            }
            $dataPendukung = $request->input('data_pendukung_sampling', []);
            if (is_string($dataPendukung)) {
                $dataPendukung = json_decode($dataPendukung, true) ?? [];
            }
            // if (empty($dataPendukung)) {
            //     return response()->json([
            //         'message' => 'Data template pengujian tidak boleh kosong'
            //     ], 422);
            // }
            $konfigurasi = array_values((array) $dataPendukung);
        } else if ($metode === 'labeling') {
            if (empty($kodePromo) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo dan nama promo tidak boleh kosong'
                ], 422);
            }
            $konfigurasi = [];
        } 
        // else if ($metode === 'pengujian_gratis') {
        //     $pemicu = $request->input('pemicu');
        //     if (is_string($pemicu)) {
        //         $pemicu = json_decode($pemicu, true);
        //     }

        //     $hadiah = $request->input('hadiah');
        //     if (is_string($hadiah)) {
        //         $hadiah = json_decode($hadiah, true);
        //     }

        //     $pemicuJumlahTitik = $pemicu['jumlah_titik'] ?? $request->input('pemicu_jumlah_titik') ?? $request->input('pemicu.jumlah_titik');
        //     $pemicuDataPendukung = $pemicu['data_pendukung_sampling'] ?? $request->input('pemicu_data_pendukung_sampling') ?? $request->input('pemicu.data_pendukung_sampling') ?? [];

        //     $hadiahJumlahTitik = $hadiah['jumlah_titik'] ?? $request->input('hadiah_jumlah_titik') ?? $request->input('hadiah.jumlah_titik');
        //     $hadiahDataPendukung = $hadiah['data_pendukung_sampling'] ?? $request->input('hadiah_data_pendukung_sampling') ?? $request->input('hadiah.data_pendukung_sampling') ?? [];

        //     if (empty($pemicuJumlahTitik) || empty($hadiahJumlahTitik)) {
        //         return response()->json([
        //             'message' => 'Jumlah titik pemicu dan hadiah tidak boleh kosong'
        //         ]);
        //     }

        //     if (is_string($pemicuDataPendukung)) {
        //         $pemicuDataPendukung = json_decode($pemicuDataPendukung, true) ?? [];
        //     }
        //     if (is_string($hadiahDataPendukung)) {
        //         $hadiahDataPendukung = json_decode($hadiahDataPendukung, true) ?? [];
        //     }

        //     $konfigurasi = [
        //         'pemicu' => [
        //             'jumlah_titik'            => is_numeric($pemicuJumlahTitik) ? +$pemicuJumlahTitik : $pemicuJumlahTitik,
        //             'data_pendukung_sampling' => (array) $pemicuDataPendukung,
        //         ],
        //         'hadiah' => [
        //             'jumlah_titik'            => is_numeric($hadiahJumlahTitik) ? +$hadiahJumlahTitik : $hadiahJumlahTitik,
        //             'data_pendukung_sampling' => (array) $hadiahDataPendukung,
        //         ],
        //     ];
        // } 
        else if ($metode === 'free_parameter') {
            if (empty($kodePromo) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo dan nama promo tidak boleh kosong'
                ], 422);
            }

            $rawKonfigurasi = $request->input('konfigurasi');
            if (is_string($rawKonfigurasi)) {
                $rawKonfigurasi = json_decode($rawKonfigurasi, true) ?? [];
            }

            $kategoriId = $request->input('kategori_id', $rawKonfigurasi['kategori_id'] ?? null);
            $parameterIds = $request->input('parameter_ids', $rawKonfigurasi['parameter_ids'] ?? []);
            $jumlahTitik = $request->input('jumlah_titik', $rawKonfigurasi['jumlah_titik'] ?? null);

            if (empty($kategoriId) || empty($parameterIds) || empty($jumlahTitik)) {
                return response()->json([
                    'message' => 'Kategori, parameter, dan jumlah titik tidak boleh kosong'
                ], 422);
            }

            if (is_string($parameterIds)) {
                $parameterIds = json_decode($parameterIds, true) ?? explode(',', $parameterIds);
            }

            $parameterIds = array_values(array_filter(array_map(function ($id) {
                return is_numeric($id) ? +$id : trim($id);
            }, (array) $parameterIds)));

            if (empty($parameterIds)) {
                return response()->json([
                    'message' => 'Parameter tidak boleh kosong'
                ], 422);
            }

            $konfigurasi = [
                'kategori_id'   => is_numeric($kategoriId) ? +$kategoriId : $kategoriId,
                'parameter_ids' => $parameterIds,
                'jumlah_titik'  => is_numeric($jumlahTitik) ? +$jumlahTitik : $jumlahTitik,
            ];
        } else {
            return response()->json([
                'message' => 'Metode promo belum didukung'
            ], 400);
        }

        DB::table('promo_pengujian')->insert([
            'kode_promo'   => $kodePromo,
            'metode'       => $metode,
            'nama_diskon'  => $namaDiskon,
            'konfigurasi'  => json_encode($konfigurasi),
            'status'       => $request->input('status', 'draft'),
            'created_by'   => $this->karyawan,
            'created_at'   => Carbon::now(),
            'updated_at'   => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Data promo pengujian berhasil ditambahkan'
        ], 200);
    }

    public function update (Request $request) {
        $id = $request->input('id');

        if (empty($id)) {
            return response()->json([
                'message' => 'ID promo pengujian tidak ditemukan'
            ], 422);
        }

        $data = DB::table('promo_pengujian')->where('id', $id)->first();
        if (!$data) {
            return response()->json([
                'message' => 'Data promo pengujian tidak ditemukan'
            ], 404);
        }

        $kodePromo = $request->input('kode_promo', $data->kode_promo);
        $metode = $request->input('metode', $data->metode);
        $namaDiskon = $request->input('nama_diskon', $data->nama_diskon);
        $status = $request->input('status', $data->status);

        $konfigurasi = json_decode($data->konfigurasi, true) ?? [];

        if ($metode === 'persentase') {
            $dasarDiskon = $request->input('dasar_diskon', $konfigurasi['dasar_diskon'] ?? '');
            $persentase  = $request->input('persentase', $konfigurasi['persentase'] ?? '');

            if (empty($kodePromo) || empty($metode) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo, metode, dan nama diskon tidak boleh kosong'
                ]);
            }
            
            if (empty($dasarDiskon) || empty($persentase)) {
                return response()->json([
                    'message' => 'Dasar diskon dan persentase tidak boleh kosong'
                ]);
            }

            $konfigurasi = [
                'dasar_diskon' => $dasarDiskon,
                'persentase'   => is_numeric($persentase) ? +$persentase : $persentase,
            ];
        } else if ($metode === 'paket_pengujian' || $metode === 'pengujian_gratis') {
            if (empty($kodePromo)) {
                return response()->json([
                    'message' => 'Kode promo tidak boleh kosong'
                ], 422);
            }
            $defaultData = is_array($konfigurasi) ? ($konfigurasi['data_pendukung_sampling'] ?? $konfigurasi) : [];
            $dataPendukung = $request->input('data_pendukung_sampling', $defaultData);
            if (is_string($dataPendukung)) {
                $dataPendukung = json_decode($dataPendukung, true) ?? [];
            }
            $konfigurasi = array_values((array) $dataPendukung);
        } else if ($metode === 'labeling') {
            if (empty($kodePromo) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo dan nama promo tidak boleh kosong'
                ], 422);
            }
            $konfigurasi = [];
        } 
        // else if ($metode === 'pengujian_gratis') {
        //     $pemicu = $request->input('pemicu');
        //     if (is_string($pemicu)) {
        //         $pemicu = json_decode($pemicu, true);
        //     }

        //     $hadiah = $request->input('hadiah');
        //     if (is_string($hadiah)) {
        //         $hadiah = json_decode($hadiah, true);
        //     }

        //     $pemicuJumlahTitik = $pemicu['jumlah_titik'] ?? $request->input('pemicu_jumlah_titik') ?? $request->input('pemicu.jumlah_titik') ?? $konfigurasi['pemicu']['jumlah_titik'] ?? '';
        //     $pemicuDataPendukung = $pemicu['data_pendukung_sampling'] ?? $request->input('pemicu_data_pendukung_sampling') ?? $request->input('pemicu.data_pendukung_sampling') ?? $konfigurasi['pemicu']['data_pendukung_sampling'] ?? [];

        //     $hadiahJumlahTitik = $hadiah['jumlah_titik'] ?? $request->input('hadiah_jumlah_titik') ?? $request->input('hadiah.jumlah_titik') ?? $konfigurasi['hadiah']['jumlah_titik'] ?? '';
        //     $hadiahDataPendukung = $hadiah['data_pendukung_sampling'] ?? $request->input('hadiah_data_pendukung_sampling') ?? $request->input('hadiah.data_pendukung_sampling') ?? $konfigurasi['hadiah']['data_pendukung_sampling'] ?? [];

        //     if (empty($pemicuJumlahTitik) || empty($hadiahJumlahTitik)) {
        //         return response()->json([
        //             'message' => 'Jumlah titik pemicu dan hadiah tidak boleh kosong'
        //         ]);
        //     }

        //     if (is_string($pemicuDataPendukung)) {
        //         $pemicuDataPendukung = json_decode($pemicuDataPendukung, true) ?? [];
        //     }
        //     if (is_string($hadiahDataPendukung)) {
        //         $hadiahDataPendukung = json_decode($hadiahDataPendukung, true) ?? [];
        //     }

        //     $konfigurasi = [
        //         'pemicu' => [
        //             'jumlah_titik'            => is_numeric($pemicuJumlahTitik) ? +$pemicuJumlahTitik : $pemicuJumlahTitik,
        //             'data_pendukung_sampling' => (array) $pemicuDataPendukung,
        //         ],
        //         'hadiah' => [
        //             'jumlah_titik'            => is_numeric($hadiahJumlahTitik) ? +$hadiahJumlahTitik : $hadiahJumlahTitik,
        //             'data_pendukung_sampling' => (array) $hadiahDataPendukung,
        //         ],
        //     ];
        // } 
        else if ($metode === 'free_parameter') {
            if (empty($kodePromo) || empty($namaDiskon)) {
                return response()->json([
                    'message' => 'Kode promo dan nama promo tidak boleh kosong'
                ], 422);
            }

            $rawKonfigurasi = $request->input('konfigurasi');
            if (is_string($rawKonfigurasi)) {
                $rawKonfigurasi = json_decode($rawKonfigurasi, true) ?? [];
            }

            $defaultKategoriId = is_array($konfigurasi) ? ($konfigurasi['kategori_id'] ?? null) : null;
            $defaultParamIds = is_array($konfigurasi) ? ($konfigurasi['parameter_ids'] ?? []) : [];
            $defaultJumlahTitik = is_array($konfigurasi) ? ($konfigurasi['jumlah_titik'] ?? null) : null;

            $kategoriId = $request->input('kategori_id', $rawKonfigurasi['kategori_id'] ?? $defaultKategoriId);
            $parameterIds = $request->input('parameter_ids', $rawKonfigurasi['parameter_ids'] ?? $defaultParamIds);
            $jumlahTitik = $request->input('jumlah_titik', $rawKonfigurasi['jumlah_titik'] ?? $defaultJumlahTitik);

            if (empty($kategoriId) || empty($parameterIds) || empty($jumlahTitik)) {
                return response()->json([
                    'message' => 'Kategori, parameter, dan jumlah titik tidak boleh kosong'
                ], 422);
            }

            if (is_string($parameterIds)) {
                $parameterIds = json_decode($parameterIds, true) ?? explode(',', $parameterIds);
            }

            $parameterIds = array_values(array_filter(array_map(function ($id) {
                return is_numeric($id) ? +$id : trim($id);
            }, (array) $parameterIds)));

            if (empty($parameterIds)) {
                return response()->json([
                    'message' => 'Parameter tidak boleh kosong'
                ], 422);
            }

            $konfigurasi = [
                'kategori_id'   => is_numeric($kategoriId) ? +$kategoriId : $kategoriId,
                'parameter_ids' => $parameterIds,
                'jumlah_titik'  => is_numeric($jumlahTitik) ? +$jumlahTitik : $jumlahTitik,
            ];
        } else {
            return response()->json([
                'message' => 'Metode promo belum didukung'
            ], 400);
        }

        DB::table('promo_pengujian')->where('id', $id)->update([
            'kode_promo'   => $kodePromo,
            'metode'       => $metode,
            'nama_diskon'  => $namaDiskon,
            'konfigurasi'  => json_encode($konfigurasi),
            'status'       => $status,
            'updated_by'   => $this->karyawan,
            'updated_at'   => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Data promo pengujian berhasil diperbarui'
        ], 200);
    }

    public function show(Request $request)
    {
        $id = $request->input('id');
        $kodePromo = $request->input('kode_promo');

        if (empty($id) && empty($kodePromo)) {
            return response()->json([
                'message' => 'ID atau Kode Promo tidak boleh kosong'
            ], 422);
        }

        $query = DB::table('promo_pengujian');
        if (!empty($id)) {
            $query->where('id', $id);
        } else if (!empty($kodePromo)) {
            $query->where('kode_promo', $kodePromo);
        }

        $data = $query->first();

        if (!$data) {
            return response()->json([
                'message' => 'Data promo pengujian tidak ditemukan'
            ], 404);
        }

        $data->konfigurasi = json_decode($data->konfigurasi, true);

        if ($data->metode === 'free_parameter' && is_array($data->konfigurasi)) {
            $katId = $data->konfigurasi['kategori_id'] ?? null;
            $paramIds = $data->konfigurasi['parameter_ids'] ?? [];

            $kategori = MasterKategori::find($katId);
            $parameters = Parameter::whereIn('id', (array) $paramIds)->select('id', 'nama_lab', 'id_kategori')->get();

            $data->kategori_nama = $kategori ? $kategori->nama_kategori : null;
            $data->parameters_detail = $parameters;
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Berhasil mengambil detail promo',
            'data'    => $data,
        ], 200);
    }

    public function detail(Request $request)
    {
        return $this->show($request);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', true)
            ->select('id', 'nama_kategori')
            ->get();

        // Tambahkan Multi Kategori di paling atas
        $data->prepend([
            'id' => 0,
            'nama_kategori' => 'Multi Kategori'
        ]);

        return response()->json($data);
    }

    public function getSubkategori(Request $request)
    {
        $data = MasterSubKategori::where('is_active', true)->select('id', 'nama_sub_kategori', 'id_kategori')->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        try {
            $query = Parameter::with('hargaParameter')
                ->whereHas('hargaParameter')
                ->where('is_active', true);

            if ($request->filled('id_kategori')) {
                $query->where('id_kategori', $request->id_kategori);
            }

            $data = $query->get();
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status'  => '500',
            ], 401);
        }
    }

    public function getParameterRegulasi(Request $request)
    {
        try {
            $idBakumutut  = explode('-', $request->id_regulasi);
            $sub_category = explode('-', $request->sub_category);
            $category     = explode('-', $request->id_category);

            $bakumutu = MasterBakumutu::where('id_regulasi', $idBakumutut[0])->where('is_active', true)->get();
            $param    = [];
            foreach ($bakumutu as $a) {
                array_push($param, $a->id_parameter . ';' . $a->parameter);
            }

            $data = Parameter::where('is_active', true)
                ->where('id_kategori', $category[0])
                ->get();

            return response()->json([
                'data'   => $data,
                'value'  => $param,
                'status' => '200',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Gagal mengambil parameter: ' . $e->getMessage(),
                'status'  => '500',
            ], 500);
        }
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('is_active', true)->get();
        return response()->json($data);
    }

    public function updateStatus(Request $request)
    {
        $id = $request->input('id');
        $status = $request->input('status');

        if (empty($id)) {
            return response()->json([
                'message' => 'ID promo pengujian tidak ditemukan'
            ], 422);
        }

        if (empty($status)) {
            return response()->json([
                'message' => 'Status tidak boleh kosong'
            ], 422);
        }

        $promo = DB::table('promo_pengujian')->where('id', $id)->first();
        if (!$promo) {
            return response()->json([
                'message' => 'Data promo pengujian tidak ditemukan'
            ], 404);
        }

        if ($status === 'aktif' && $promo->metode !== 'labeling') {
            $konfigurasi = json_decode($promo->konfigurasi, true);
            $isEmpty = empty($konfigurasi);
            if (!$isEmpty && is_array($konfigurasi) && count($konfigurasi) === 1 && isset($konfigurasi['data_pendukung_sampling'])) {
                $isEmpty = empty($konfigurasi['data_pendukung_sampling']);
            }
            if ($isEmpty) {
                return response()->json([
                    'message' => 'Konfigurasi promo masih kosong. Silakan lengkapi konfigurasi terlebih dahulu.'
                ], 422);
            }
        }

        DB::table('promo_pengujian')->where('id', $id)->update([
            'status'     => $status,
            'updated_by' => $this->karyawan,
            'updated_at' => Carbon::now(),
        ]);

        $statusText = $status === 'aktif' ? 'diaktifkan' : 'dinonaktifkan';

        return response()->json([
            'status'  => 'success',
            'message' => "Promo pengujian {$promo->kode_promo} berhasil {$statusText}",
        ], 200);
    }
}

