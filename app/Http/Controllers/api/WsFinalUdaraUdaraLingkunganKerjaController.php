<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganErgonomi;
use App\Models\DataLapanganMedanLM;
use App\Models\DataLapanganSinarUV;
use App\Models\DebuPersonalHeader;
use App\Models\DetailLingkunganHidup;
use App\Models\DirectLainHeader;
use App\Models\ErgonomiHeader;
use App\Models\HistoryAppReject;
use App\Models\LingkunganHeader;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MedanLmHeader;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\PartikulatHeader;
use App\Models\SinarUvHeader;
use App\Models\Subkontrak;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;
use Carbon\Carbon;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WsFinalUdaraUdaraLingkunganKerjaController extends Controller
{
    private $categoryLingkunganKerja = [11, 27];

    public function index(Request $request)
    {
        $data = OrderDetail::where('is_active', $request->is_active)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["27-Udara Lingkungan Kerja"])
            ->where('status', 0)
            ->whereNotNull('tanggal_terima')
            ->whereJsonDoesntContain('parameter', ["318;Psikologi"])
            ->whereMonth('tanggal_sampling', explode('-', $request->date)[1])
            ->whereYear('tanggal_sampling', explode('-', $request->date)[0])
            ->orderBy('id', "desc");

        return Datatables::of($data)->make(true);
    }

    public function convertHourToMinute($hour)
    {
        $minutes = $hour * 60;
        return $minutes;
    }

    public function detail(Request $request)
    {
        try {
            $parameters     = json_decode(html_entity_decode($request->parameter), true);
            $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];

            // ERGONOMI
            if ($parameterArray[1] == 'Ergonomi') {
                $data = ErgonomiHeader::with('datalapangan')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', true)
                    ->where('is_active', true)
                    ->select('*') // pastikan select ada
                    ->addSelect(DB::raw("'ergonomi' as data_type"));

                return Datatables::of($data)->make(true);
            } else if ($parameterArray[1] == 'Sinar UV') {
                $data = SinarUvHeader::with('datalapangan', 'ws_udara', "order_detail")
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', true)
                    ->where('is_active', true)
                    ->select('*')
                    ->addSelect(DB::raw("'sinar_uv' as data_type"))
                    ->get();

                foreach ($data as $item) {
                    $waktu = $item->datalapangan->waktu_pemaparan ?? null;

                    if ($waktu !== null) {
                        if ($waktu >= 1 && $waktu < 5) {
                            $item->nab = 0.05;
                        } elseif ($waktu >= 5 && $waktu < 10) {
                            $item->nab = 0.01;
                        } elseif ($waktu >= 10 && $waktu < 15) {
                            $item->nab = 0.005;
                        } elseif ($waktu >= 15 && $waktu < 30) {
                            $item->nab = 0.0033;
                        } elseif ($waktu >= 30 && $waktu < 60) {
                            $item->nab = 0.0017;
                        } elseif ($waktu >= 60 && $waktu < 120) {
                            $item->nab = 0.0008;
                        } elseif ($waktu >= 120 && $waktu < 240) {
                            $item->nab = 0.0004;
                        } elseif ($waktu >= 240 && $waktu < 480) {
                            $item->nab = 0.0002;
                        } elseif ($waktu >= 480) {
                            $item->nab = 0.0001;
                        } else {
                            $item->nab = null;
                        }
                    } else {
                        $item->nab = null;
                    }

                    $regulasi     = json_decode($item->order_detail->regulasi);
                    $item->method = $regulasi ? explode('-', $regulasi[0])[1] : null;
                }

                return Datatables::of($data)->make(true);
            } else if ($parameterArray[1] == 'Debu (P8J)') {
                $data = DebuPersonalHeader::with('data_lapangan', 'ws_lingkungan')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', true)
                    ->where('is_active', true)
                    ->select('*')
                    ->addSelect(DB::raw("'debu_personal' as data_type"));

                return Datatables::of($data)->make(true);
            } else if ($parameterArray[1] == 'Medan Magnit Statis' || $parameterArray[1] == 'Medan Listrik' || $parameterArray[1] == 'Power Density') {
                $data = MedanLmHeader::with('datalapangan', 'ws_udara')
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', true)
                    ->where('is_active', true)
                    ->select('*')
                    ->addSelect(DB::raw("'medan_lm' as data_type"))->get();
                foreach ($data as $item) {
                    $regulasi     = json_decode($item->orderDetail->regulasi);
                    $item->method = $regulasi ? explode('-', $regulasi[0])[1] : null;
                }

                return Datatables::of($data)->make(true);
            }

            $directData = DirectLainHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'direct' as data_type"))
                ->get();

            $partikulat = PartikulatHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'partikulat' as data_type"))
                ->get();

            $lingkunganData = LingkunganHeader::with('ws_udara', 'ws_value_linkungan')
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'lingkungan' as data_type"))
                ->get();

            $subkontrak = Subkontrak::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approve', 1)
                ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                ->addSelect(DB::raw("'subKontrak' as data_type"))
                ->get();
            $microbio = MicrobioHeader::with(['ws_udara'])
                ->where('no_sampel', $request->no_sampel)
                ->where('is_approved', 1)
                ->where('status', 0)
                ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                ->addSelect(DB::raw("'microbio' as data_type"))
                ->get();

            $combinedData = collect()
                ->merge($lingkunganData)
                ->merge($subkontrak)
                ->merge($partikulat)
                ->merge($directData)
                ->merge($microbio);

            $processedData = $combinedData->map(function ($item) {
                switch ($item->data_type) {
                    case 'lingkungan':
                        $item->source = 'Lingkungan';
                        break;
                    case 'subKontrak':
                        $item->source = 'Subkontrak';
                        break;
                    case 'direct':
                        $item->source = 'Direct Lain';
                        break;
                    case 'partikulat':
                        $item->source = 'Partikulat';
                        break;
                    case 'microbio':
                        $item->source = 'Mikrobiologi';
                        break;
                }
                return $item;
            });
            // $id_regulasi = explode("-", json_decode($request->regulasi)[0])[0];
            $id_regulasi = $request->regulasi;
            foreach ($processedData as $item) {

                $dataLapangan = DetailLingkunganHidup::where('no_sampel', $item->no_sampel)
                    ->select('durasi_pengambilan')
                    ->where('parameter', $item->parameter)
                    ->first();
                $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                    ->where('id_regulasi', $id_regulasi)
                    ->where('is_active', 1)
                    ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                    ->first();

                $item->durasi      = $dataLapangan->durasi_pengambilan ?? null;
                $item->satuan      = $bakuMutu->satuan ?? null;
                $item->baku_mutu   = $bakuMutu->baku_mutu ?? null;
                $item->method      = $bakuMutu->method ?? null;
                $item->nama_header = $bakuMutu->nama_header ?? null;
            }

            $getSatuan = new HelperSatuan;

            return Datatables::of($processedData)
                ->addColumn('nilai_uji', function ($item) use ($getSatuan) {
                    // ambil satuan dan index (boleh null)
                    $satuan = $item->satuan ?? null;
                    $index  = $getSatuan->udara($satuan);

                    // pilih sumber hasil: ws_udara dulu, kalau ga ada pakai ws_value_linkungan
                    $source = $item->ws_udara ?? $item->ws_value_linkungan ?? null;
                    if (! $source) {
                        return 'noWs';
                    }

                    // pastikan array
                    $hasil = is_array($source) ? $source : $source->toArray();
                    // helper kecil: cek tersedia dan tidak kosong
                    $has = function ($key) use ($hasil) {
                        return isset($hasil[$key]) && $hasil[$key] !== null && $hasil[$key] !== '';
                    };

                    // jika index tidak diketahui, coba serangkaian fallback (dari paling prioritas ke paling umum)
                    if ($index === null) {
                        // 1) f_koreksi_c (tanpa nomor) lalu f_koreksi_c1..f_koreksi_c16
                        if ($has('f_koreksi_c')) {
                            return $hasil['f_koreksi_c'];
                        }

                        for ($i = 1; $i <= 16; $i++) {
                            $k = "f_koreksi_c{$i}";
                            if ($has($k)) {
                                return $hasil[$k];
                            }

                        }

                        // 2) C (tanpa nomor) lalu C1..C16
                        if ($has('C')) {
                            return $hasil['C'];
                        }

                        for ($i = 1; $i <= 16; $i++) {
                            $k = "C{$i}";
                            if ($has($k)) {
                                return $hasil[$k];
                            }

                        }

                        // 3) f_koreksi_1..f_koreksi_17
                        for ($i = 1; $i <= 17; $i++) {
                            $k = "f_koreksi_{$i}";
                            if ($has($k)) {
                                return $hasil[$k];
                            }

                        }

                        // 4) hasil1..hasil17
                        for ($i = 1; $i <= 17; $i++) {
                            $k = "hasil{$i}";
                            if ($has($k)) {
                                return $hasil[$k];
                            }

                        }

                        // kalau semua gagal
                        return '-';
                    }

                    // bila index diketahui, cek urutan preferensi khusus index itu
                    $keysToTry = [
                        "f_koreksi_{$index}",
                        "hasil{$index}",
                        "f_koreksi_c{$index}",
                        "C{$index}",
                    ];

                    if ($index == 17) {
                        foreach ($keysToTry as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                        foreach (['f_koreksi_c2', 'C2', 'f_koreksi_2', 'hasil2'] as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                    }if ($index == 15) {
                        foreach ($keysToTry as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                        foreach (['f_koreksi_c3', 'C3', 'f_koreksi_3', 'hasil3'] as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                    }if ($index == 16) {
                        foreach ($keysToTry as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                        foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                    } else {
                        foreach ($keysToTry as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                        foreach (['f_koreksi_c1', 'C1', 'f_koreksi_1', 'hasil1'] as $k) {
                            if ($has($k)) {
                                if ($hasil[$k] != null) {
                                    return $hasil[$k];
                                }
                            }
                        }
                    }

                    return '-';
                })
                ->make(true);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        $parameterNames = [];

        if (is_array($request->parameter)) {
            foreach ($request->parameter as $param) {
                $paramParts = explode(";", $param);
                if (isset($paramParts[1])) {
                    $parameterNames[] = trim($paramParts[1]);
                }
            }
        }
        if ($request->kategori == 11) {
            $noOrder   = explode('/', $request->no_sampel)[0] ?? null;
            $Lapangan  = OrderDetail::where('no_order', $noOrder)->get();
            $lapangan2 = $Lapangan->map(function ($item) {
                return $item->no_sampel;
            })->unique()->sortBy(function ($item) {
                return (int) explode('/', $item)[1];
            })->values();

            $totLapangan = $lapangan2->count();
            try {
                $data = DetailLingkunganHidup::where('no_sampel', $request->no_sampel)->first();

                // $urutan = $lapangan2->search($data->no_sampel);
                // $urutanDisplay = $urutan + 1;
                // $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['data' => [], 'message' => 'Data tidak ditemukan', 'success' => false, 'status' => 404]);
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else if ($request->kategori == 27) {
            // $parameters = json_decode(html_entity_decode($request->parameter), true);
            try {
                $noOrder   = explode('/', $request->no_sampel)[0] ?? null;
                $Lapangan  = OrderDetail::where('no_order', $noOrder)->get();
                $lapangan2 = $Lapangan->map(function ($item) {
                    return $item->no_sampel;
                })->unique()->sortBy(function ($item) {
                    return (int) explode('/', $item)[1];
                })->values();
                $totLapangan = $lapangan2->count();
                // Cek apakah 'Ergonomi' ada dalam array
                if (in_array("Ergonomi", $parameterNames)) {

                    $data           = DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->first();
                    $urutan         = $lapangan2->search($data->no_sampel);
                    $urutanDisplay  = $urutan + 1;
                    $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
                    if ($data) {
                        $dataArray              = $data->toArray();
                        $dataArray['parameter'] = 'Ergonomi';

                        return response()->json([
                            'data'    => $dataArray,
                            'message' => 'Berhasil mendapatkan data',
                            'success' => true,
                            'status'  => 200,
                        ]);
                    }
                } else if (in_array("Sinar UV", $parameterNames)) {
                    $data           = DataLapanganSinarUV::where('no_sampel', $request->no_sampel)->first();
                    $urutan         = $lapangan2->search($data->no_sampel);
                    $urutanDisplay  = $urutan + 1;
                    $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
                    if ($data) {
                        $dataArray              = $data->toArray();
                        $dataArray['parameter'] = 'Sinar UV';

                        return response()->json([
                            'data'    => $dataArray,
                            'message' => 'Berhasil mendapatkan data',
                            'success' => true,
                            'status'  => 200,
                        ]);
                    }
                } else if (in_array("Debu (P8J)", $parameterNames)) {
                    $data = DataLapanganDebuPersonal::where('no_sampel', $request->no_sampel)->first();

                    if ($data) {
                        $dataArray              = $data->toArray();
                        $dataArray['parameter'] = 'Debu (P8J)';

                        return response()->json([
                            'data'    => $dataArray,
                            'message' => 'Berhasil mendapatkan data',
                            'success' => true,
                            'status'  => 200,
                        ]);
                    }
                } else if (in_array('Medan Magnit Statis', $parameterNames) || in_array('Medan Listrik', $parameterNames) || in_array('Power Density', $parameterNames)) {

                    $data           = DataLapanganMedanLM::where('no_sampel', $request->no_sampel)->first();
                    $urutan         = $lapangan2->search($data->no_sampel);
                    $urutanDisplay  = $urutan + 1;
                    $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";
                    if ($data) {
                        $dataArray = $data->toArray();
                        switch (true) {
                            case in_array('Medan Magnit Statis', $parameterNames):
                                $dataArray['parameter'] = 'Medan Magnit Statis';
                                break;
                            case in_array('Medan Listrik', $parameterNames):
                                $dataArray['parameter'] = 'Medan Listrik';
                                break;
                            case in_array('Power Density', $parameterNames):
                                $dataArray['parameter'] = 'Power Density';
                                break;
                        }

                        return response()->json([
                            'data'    => $dataArray,
                            'message' => 'Berhasil mendapatkan data',
                            'success' => true,
                            'status'  => 200,
                        ]);
                    }
                } else {
                    $data = OrderDetail::withAnyDataLapangan()
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_active', true)
                        ->first();

                    if ($data && $data->anyDataLapangan) {
                        // anyDataLapangan sudah berisi relasi yang ada
                        return response()->json([
                            'data'    => $data->anyDataLapangan,
                            'message' => 'Berhasil mendapatkan data',
                            'success' => true,
                            'status'  => 200,
                        ]);
                    } else {
                        return response()->json([
                            'message' => 'Data lapangan tidak ditemukan',
                            'success' => false,
                            'status'  => 404,
                        ]);
                    }
                }
            } catch (\Exception $ex) {
                dd($ex);
            }
        } else {
            $data = [];
        }
    }

    public function rejectAnalys(Request $request)
    {
        try {
            if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
                if ($request->data_type == 'lingkungan') {
                    // Update data for 'lingkungan'
                    $data = LingkunganHeader::where('id', $request->id)->update([
                        'is_approved'  => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'subKontrak') {
                    $data = Subkontrak::where('id', $request->id)->update([
                        'is_approve'   => 0,
                        'is_active'    => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'direct') {
                    // Update data for 'direct'
                    $data = DirectLainHeader::where('id', $request->id)->update([
                        'is_approve'   => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'medan_lm') {
                    // Update data for 'direct'
                    $data = MedanLmHeader::where('id', $request->id)->update([
                        'is_approve'   => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'debu_personal') {
                    // Update data for 'direct'
                    $data = DebuPersonalHeader::where('id', $request->id)->update([
                        'is_approved'  => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'sinar_uv') {
                    // Update data for 'direct'
                    $data = SinarUvHeader::where('id', $request->id)->update([
                        'is_approve'   => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else if ($request->data_type == 'microbio') {
                    $data = MicrobioHeader::where('id', $request->id)->update([
                        'is_approved'   => 0,
                        'notes_reject' => $request->note,
                        'rejected_by'  => $this->karyawan,
                        'rejected_at'  => Carbon::now(),

                    ]);
                } else {
                    // If neither 'lingkungan' nor 'direct', return an error message
                    return response()->json(['message' => 'Invalid data_type provided.'], 400);
                }

                if ($data) {
                    return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['message' => 'Gagal', 'success' => false, 'status' => 400]);
                }
            } else {
                $data = [];
            }
        } catch (\Exception $ex) {
            dd($ex);
        }
    }

    public function approveWSApi(Request $request)
    {
        if ($request->id) {
            if (in_array($request->kategori, $this->categoryLingkunganKerja)) {
                if ($request->data_type == 'lingkungan') {
                    $data = LingkunganHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek       = LingkunganHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = LingkunganHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'subKontrak') {

                    $data = Subkontrak::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();

                    if ($data) {
                        $cek       = Subkontrak::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = Subkontrak::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'direct') {
                    $data = DirectLainHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    if ($data) {
                        $cek       = DirectLainHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = DirectLainHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'medan_lm') {
                    $data = MedanLmHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek       = MedanLmHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = MedanLmHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'microbio') {
                    $data = MicrobioHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek       = MicrobioHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = MicrobioHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'debu_personal') {
                    $data = DebuPersonalHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek       = DebuPersonalHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = DebuPersonalHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else if ($request->data_type == 'partikulat') {
                    $data = PartikulatHeader::where('parameter', $request->parameter)->where('lhps', 1)->where('no_sampel', $request->no_sampel)->first();
                    // dd($data);
                    if ($data) {
                        $cek       = PartikulatHeader::where('id', $data->id)->first();
                        $cek->lhps = 0;
                        $cek->save();
                        return response()->json([
                            'message' => 'Data has ben Rejected',
                            'success' => true,
                            'status'  => 201,
                        ], 201);
                    } else {
                        $dat       = PartikulatHeader::where('id', $request->id)->first();
                        $dat->lhps = 1;
                        $dat->save();
                        return response()->json([
                            'message' => 'Data has ben Approved',
                            'success' => true,
                            'status'  => 200,
                        ], 200);
                    }
                } else {
                    $data = SinarUvHeader::where('parameter', $request->parameter)
                        ->where('lhps', 1)
                        ->where('no_sampel', $request->no_sampel)
                        ->first();
                    $ws = WsValueUdara::where('no_sampel', $request->no_sampel)
                        ->first();
                    if ($data) {

                        $data->update([
                            'lhps' => 0,
                        ]);
                    } else {
                        $dat = SinarUvHeader::where('id', $request->id)->first()
                            ->update([
                                'lhps' => 1,
                            ]);
                    }
                    if ($ws) {
                        $ws->nab = $request->nab;
                        $ws->save();
                    }
                    return response()->json([
                        'message' => 'Data has ben Updated',
                        'success' => true,
                        'status'  => 201,
                    ], 201);
                }
            } else {
                $data = [];
            }
        } else {
            return response()->json([
                'message' => 'Gagal Approve',
                'status'  => 401,
            ], 401);
        }
    }

    public function AddSubKontrak(Request $request)
    {
        DB::beginTransaction();
        try {
            if ($request->subCategory == 11 || $request->subCategory == 27) {
                $data                  = new Subkontrak();
                $data->no_sampel       = $request->no_sampel;
                $data->category_id     = $request->category;
                $data->parameter       = $request->parameter;
                $data->note            = $request->keterangan;
                $data->jenis_pengujian = $request->jenis_pengujian;
                $data->is_active       = true;
                $data->is_approve      = 1;
                $data->approved_at     = Carbon::now()->format('Y-m-d H:i:s');
                $data->approved_by     = $this->karyawan;
                $data->created_at      = Carbon::now()->format('Y-m-d H:i:s');
                $data->created_by      = $this->karyawan;
                $data->save();

                $ws                = new WsValueLingkungan();
                $ws->no_sampel     = $request->no_sampel;
                $ws->id_subkontrak = $data->id;
                $ws->flow          = $request->flow;
                $ws->durasi        = $request->durasi;
                $ws->C             = $request->C;
                $ws->C1            = $request->C1;
                $ws->C2            = $request->C2;
                $ws->is_active     = true;
                $ws->status        = 0;
                $ws->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data has ben Added',
                'success' => true,
                'status'  => 200,
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 401,
            ], 401);
        }
    }

    public function validasiApproveWSApi(Request $request)
    {
        DB::beginTransaction();
        try {

            if ($request->id) {
                $data               = OrderDetail::where('id', $request->id)->first();
                $data->status       = 1;
                $data->keterangan_1 = $request->keterangan_1;
                $data->save();

                HistoryAppReject::insert([
                    'no_lhp'      => $data->cfr,
                    'no_sampel'   => $data->no_sampel,
                    'kategori_2'  => $data->kategori_2,
                    'kategori_3'  => $data->kategori_3,
                    'menu'        => 'WS Final Udara',
                    'status'      => 'approve',
                    'approved_at' => Carbon::now(),
                    'approved_by' => $this->karyawan,
                ]);

                DB::commit();
                $this->resultx = 'Data hasbeen Approved.!';
                return response()->json([
                    'message' => $this->resultx,
                    'status'  => 200,
                    'success' => true,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Data Not Found.!',
                    'status'  => 401,
                    'success' => false,
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
            ], 401);
        }
    }

    public function KalkulasiKoreksi(Request $request)
    {
        try {
            $type_koreksi   = $request->type;
            $id             = $request->id;
            $no_sampel      = $request->no_sampel;
            $parameter      = $request->parameter;
            $faktor_koreksi = (float) $request->faktor_koreksi;

            // Ambil hasil_c sampai hasil_c16 secara dinamis
            $hasilC = [];
            for ($i = 0; $i <= 16; $i++) {
                $key        = $i === 0 ? 'hasil_c' : 'hasil_c' . $i;
                $hasilC[$i] = html_entity_decode($request->$key ?? '');
            }

            $hasil = $this->hitungKoreksi($request, $type_koreksi, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilC);

            // Format hasil menjadi 4 angka di belakang koma jika numerik
            foreach ($hasil as $key => $val) {
                if (is_numeric($val)) {
                    $hasil[$key] = number_format((float) $val, 4, '.', '');
                }
            }

            return response()->json(['hasil' => $hasil]);
        } catch (\Exception $e) {
            \Log::error('Error dalam KalkulasiKoreksi: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function hitungKoreksi($request, $type_koreksi, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilC)
    {
        try {
            return $this->rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, $hasilC);
        } catch (\Exception $e) {
            \Log::error('Error dalam hitungKoreksi: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, array $hasilC)
    {
        $po = OrderDetail::where('no_sampel', $no_sampel)
            ->where('is_active', 1)
            ->where('parameter', 'like', '%' . $parameter . '%')
            ->first();

        try {
            // Fungsi bantu
            function removeSpecialChars($value)
            {
                return is_string($value) ? str_replace('<', '', $value) : $value;
            }

            function cekSpecialChar($value)
            {
                return is_string($value) && strpos($value, '<') !== false;
            }

            function applyFormula($value, float $factor)
            {
                $cleaned = removeSpecialChars($value);
                if ($cleaned === null || $cleaned === '') {
                    return null;
                }

                $num = floatval($cleaned);
                if (is_nan($num)) {
                    return null;
                }

                if (cekSpecialChar($value)) {
                    $result = (($num / 0.3856) * ($factor / 100)) + ($num / 0.3856);
                } else {
                    $result = ($num * ($factor / 100)) + $num;
                }

                // kalau hasil 0.0, ubah jadi null
                return ($result == 0.0 ? null : $result);
            }

            // Hitung hasil untuk semua C
            $hasil = [];
            foreach ($hasilC as $i => $val) {
                // kalau index 0 -> hasilc, sisanya hasilc1, hasilc2, dst.
                $key         = ($i === 0) ? 'hasilc' : "hasilc{$i}";
                $hasil[$key] = (empty($val)) ? null : applyFormula($val, $faktor_koreksi);
            }

            // Contoh kondisi O3
            if ($parameter == 'O3' || $parameter == 'O3 (8 Jam)') {
                if ($hasil['hasilc'] < 0.1419) {
                    $hasil['hasilc'] = '<0.1419';
                }

                if ($hasil['hasilc1'] < 0.00014) {
                    $hasil['hasilc1'] = '<0.00014';
                }

                if ($hasil['hasilc2'] < 0.00007) {
                    $hasil['hasilc2'] = '<0.00007';
                }

            }

            return $hasil;
        } catch (\Exception $e) {
            \Log::error('Error in rumusUdara: ' . $e->getMessage());
            return ['error' => 'Terjadi kesalahan saat memproses data'];
        }
    }

    // ========================
    // 🔽 SAVE DATA SECTION 🔽
    // ========================

    public function saveData(Request $request)
    {
        $kategori_koreksi = $request->kategori;
        $id               = $request->id;
        $no_sampel        = $request->no_sampel;
        $parameter        = $request->parameter;
        $faktor_koreksi   = (float) $request->faktor_koreksi;

        // Ambil hasil_c sampai hasil_c16
        $hasilC = [];
        for ($i = 0; $i <= 16; $i++) {
            $key        = $i === 0 ? 'hasil_c' : 'hasil_c' . $i;
            $hasilC[$i] = $request->$key ?? null;
        }

        if ($kategori_koreksi) {
            switch ($kategori_koreksi) {
                case '11':
                case '27':
                    $udara = LingkunganHeader::with('ws_value_linkungan')
                        ->where('no_sampel', $no_sampel)
                        ->where('is_active', 1)
                        ->first();

                    return $this->handleLingkungan($request, $no_sampel, $parameter, $hasilC, $udara, $faktor_koreksi);
                default:
                    return response()->json(['message' => 'Type koreksi tidak valid.'], 400);
            }
        } else {
            return response()->json(['message' => 'Type koreksi harus diisi.'], 400);
        }
    }

    private function handleLingkungan($request, $no_sampel, $parameter, $hasilC, $udara, $faktor_koreksi)
    {
        try {
            DB::beginTransaction();

            $po = OrderDetail::where('no_sampel', $no_sampel)
                ->where('is_active', 1)
                ->where('parameter', 'like', '%' . $parameter . '%')
                ->first();

            if (! $po) {
                return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
            }

            $lingkungan = LingkunganHeader::where('no_sampel', $no_sampel)
                ->where('parameter', $parameter)
                ->where('is_active', 1)
                ->first();

            if (! $lingkungan) {
                return response()->json(['message' => 'Data Lingkungan tidak ditemukan.'], 404);
            }

            $valuews = WsValueLingkungan::where('no_sampel', $no_sampel)
                ->where('lingkungan_header_id', $lingkungan->id)
                ->where('is_active', 1)
                ->first();

            $wsUdara = WsValueUdara::where('no_sampel', $no_sampel)
                ->where('id_lingkungan_header', $lingkungan->id)
                ->where('is_active', 1)
                ->first();

            if (! $valuews || ! $wsUdara) {
                return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
            }

            $nomor = $lingkungan->tipe_koreksi ? ($lingkungan->tipe_koreksi < 5 ? $lingkungan->tipe_koreksi + 1 : 5) : 1;
            if ($nomor > 5) {
                return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
            }
            $lingkungan->tipe_koreksi  = $nomor;
            $lingkungan->input_koreksi = $faktor_koreksi;
            $lingkungan->save();

            // Simpan hasil C0–C16 di ws lingkungan
            foreach ($hasilC as $i => $val) {
                // ubah "-" atau string kosong jadi null
                if ($val === '-' || $val === '' || $val === null) {
                    $val = null;
                } elseif (! str_contains((string) $val, '<') && is_numeric($val)) {
                    $val = number_format((float) $val, 4, '.', '');
                }

                $col           = $i === 0 ? 'f_koreksi_c' : 'f_koreksi_c' . $i;
                $valuews->$col = $val;
            }

            // Simpan hasil C0–C16 di ws udara
            foreach ($hasilC as $i => $val) {
                if ($val === '-' || $val === '' || $val === null) {
                    $val = null;
                } elseif (! str_contains((string) $val, '<') && is_numeric($val)) {
                    $val = number_format((float) $val, 4, '.', '');
                }

                $col           = 'f_koreksi_' . ($i + 1); // index 0 → f_koreksi_1
                $wsUdara->$col = $val;
            }

            $valuews->input_koreksi = $faktor_koreksi;

            $valuews->save();
            $wsUdara->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
        } catch (\Exception $ex) {
            DB::rollBack();
            \Log::error('Error dalam handleLingkungan: ' . $ex->getMessage());
            return response()->json(['message' => 'Terjadi kesalahan: ' . $ex->getMessage()], 500);
        }
    }

    public function getKaryawan(Request $request)
    {
        return MasterKaryawan::where('is_active', true)->get();
    }

    public function updateTindakan(Request $request)
    {

        try {

            $data           = WsValueUdara::where('id', $request->id)->first();
            $data->tindakan = $request->tindakan;
            $data->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status'  => 200,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 401,
            ], 401);
        }
    }
    public function updateBagianTubuh(Request $request)
    {
        try {
            $data               = MedanLmHeader::where('id', $request->id)->first();
            $data->bagian_tubuh = $request->bag_tubuh;
            $data->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status'  => 200,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 401,
            ], 401);
        }
    }

    public function updateNab(Request $request)
    {
        try {

            $data = WsValueUdara::where('id', $request->id)->first();

            $data->nab = $request->nab;
            $data->save();

            return response()->json([
                'message' => 'Data berhasil diupdate.',
                'status'  => 200,
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'status'  => 401,
            ], 401);
        }
    }
    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::where('id_kategori', $request->id_kategori)
            ->where('is_active', '1')->get();

        return response()->json([
            'data' => $data,
        ]);
    }
    public function ubahRegulasi(Request $request)
    {
        DB::beginTransaction();
        try {
            $regulasi       = MasterRegulasi::where('id', $request->regulasi)->first();
            $new_regulasi   = [$request->regulasi . '-' . $regulasi->peraturan];
            $data           = OrderDetail::where('id', $request->id)->first();
            $data->regulasi = $new_regulasi;
            $data->save();
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Regulasi berhasil diubah!',
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            throw $th;
        }
    }
}
