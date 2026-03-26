<?php
namespace App\Http\Controllers\api;

use App\Helpers\HelperSatuan;
use App\Http\Controllers\Controller;
use App\Models\DetailMicrobiologi;
use App\Models\HistoryAppReject;
use App\Models\LingkunganHeader;
use App\Models\MasterBakumutu;
use App\Models\MasterKaryawan;
use App\Models\MasterRegulasi;
use App\Models\MicrobioHeader;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\ParameterFdl;
use App\Models\Subkontrak;
use App\Models\WsValueLingkungan;
use Carbon\Carbon;
use Datatables;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WsFinalUdaraMikrobiologiUdaraController extends Controller
{
    private $categoryMicrobio = [12, 33, 27, 26];

    public function index(Request $request)
    {

        $parameterAllowed = ParameterFdl::where('nama_fdl', 'microbiologi')->first();
        $parameterAllowed = json_decode($parameterAllowed->parameters, true);

        $data = OrderDetail::select(
            DB::raw("MAX(id) as max_id"),
            DB::raw("GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ', ') as tanggal_sampling"),
            DB::raw("GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ', ') as tanggal_terima"),
            'no_order',
            'nama_perusahaan',
            'cfr',
            'kategori_2',
            'kategori_3',
        )
            ->where('is_active', $request->is_active)
            ->where('kategori_2', '4-Udara')
            ->whereIn('kategori_3', ["33-Mikrobiologi Udara", "12-Udara Angka Kuman", "27-Udara Lingkungan Kerja", "26-Kualitas Udara Dalam Ruang"])
            ->where('status', 0)
            ->whereNotNull('tanggal_terima')
            ->when($request->date, fn($q) => $q->whereYear('tanggal_sampling', explode('-', $request->date)[0])->whereMonth('tanggal_sampling', explode('-', $request->date)[1]))
            ->where(function ($query) use ($parameterAllowed) {
                foreach ($parameterAllowed as $param) {
                    $query->orWhere('parameter', 'LIKE', "%;$param%");
                }
            })
            ->groupBy('cfr', 'kategori_2', 'kategori_3', 'nama_perusahaan', 'no_order')
            ->orderBy('tanggal_sampling');

        return Datatables::of($data)->make(true);

        // $data = OrderDetail::where('is_active', $request->is_active)
        // 	->where('kategori_2', '4-Udara')
        // 	->whereIn('kategori_3', ['33-Mikrobiologi Udara', "12-Udara Angka Kuman"])
        // 	->where('status', 0)
        // 	->whereNotNull('tanggal_terima')
        // 	->whereJsonDoesntContain('parameter', ["318;Psikologi"])
        // 	->whereMonth('tanggal_sampling', explode('-', $request->date)[1])
        // 	->whereYear('tanggal_sampling', explode('-', $request->date)[0])
        // 	->orderBy('id', "desc");

        // return Datatables::of($data)->make(true);
    }

    public function getDetailCfr(Request $request)
    {
        $data = OrderDetail::with(['detailMicrobiologi', 'udaraMicrobio', 'udaraSubKontrak'])->where('cfr', $request->cfr)
            ->where('status', 0)
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                $item->getAnyHeaderUdara();
                return $item;
            })->values()
            ->map(function ($item) {
                $item->getAnyDataLapanganUdara();
                return $item;
            });

        return response()->json([
            'data'    => $data,
            'message' => 'Data retrieved successfully',
        ], 200);
    }

    public function convertHourToMinute($hour)
    {
        $minutes = $hour * 60;
        return $minutes;
    }

    private function getNabKebisingan($menit)
    {
        if ($menit >= 0.94 && $menit < 1.88) {
            return 112;
        } elseif ($menit >= 1.88 && $menit < 3.75) {
            return 109;
        } elseif ($menit >= 3.75 && $menit < 7.5) {
            return 106;
        } elseif ($menit >= 7.5 && $menit < 15) {
            return 103;
        } elseif ($menit >= 15 && $menit < 30) {
            return 100;
        } elseif ($menit >= 30 && $menit < 60) {
            return 97;
        } elseif ($menit >= 60 && $menit < 120) {
            return 94;
        } elseif ($menit >= 120 && $menit < 240) {
            return 91;
        } elseif ($menit >= 240 && $menit < 480) {
            return 88;
        } elseif ($menit >= 480) {
            return 85;
        }
        return null;
    }

    public function detail(Request $request)
    {
        try {
            $parameters     = json_decode(html_entity_decode($request->parameter), true);
            $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
            if (in_array($request->kategori, $this->categoryMicrobio)) {

                $microbio = MicrobioHeader::with(['ws_value'])->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    ->where('is_active', 1)
                    ->where('status', 0)->get();

                $subkontrak = Subkontrak::with(['ws_udara'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                    ->addSelect(DB::raw("'subKontrak' as data_type"))
                    ->get();

                $combinedData = collect()
                    ->merge($subkontrak)
                    ->merge($microbio);

                $processedData = $combinedData->map(function ($item) {
                    switch ($item->data_type) {
                        case 'subKontrak':
                            $item->source = 'Subkontrak';
                            break;
                        case 'microbio':
                            $item->source = 'Mikrobiologi';
                            break;
                    }
                    return $item;
                });

                $idRegulasi = $request->regulasi;

                foreach ($processedData as $item) {

                    $bakuMutu = MasterBakumutu::where("id_parameter", $item->id_parameter)
                        ->where('id_regulasi', $idRegulasi)
                        ->where('is_active', 1)
                        ->select('baku_mutu', 'satuan', 'method', 'nama_header')
                        ->first();

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
            } else {
                return response()->json([
                    'message' => 'Kategori tidak sesuai',
                    'status'  => 404,
                ], 404);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => $th->getMessage(),
                'line'    => $th->getLine(),
            ], 401);
        }
    }

    public function detailLapangan(Request $request)
    {
        if (in_array($request->kategori, $this->categoryMicrobio)) {
            $getNoOrder = explode('/', $request->no_sampel)[0] ?? null;

            $orderDetail = OrderDetail::where('no_order', $getNoOrder)->get();

            $getNoSampel = $orderDetail->map(function ($item) {
                return $item->no_sampel;
            })->unique()->sortBy(function ($item) {
                return (int) explode('/', $item)[1];
            })->values();

            $totLapangan = $getNoSampel->count();

            try {
                $data  = [];
                $model = DetailMicrobiologi::class;

                $data = $model::where('no_sampel', $request->no_sampel)->first();
                if (! $data) {
                    return response()->json(['message' => 'Data Lapangan Tidak Ditemukan'], 401);
                }

                $urutan         = $getNoSampel->search($data->no_sampel);
                $urutanDisplay  = $urutan + 1;
                $data['urutan'] = "{$urutanDisplay}/{$totLapangan}";

                if ($data) {
                    return response()->json(['data' => $data, 'message' => 'Berhasil mendapatkan data', 'success' => true, 'status' => 200]);
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
            if (in_array($request->kategori, $this->categoryMicrobio)) {
                $data = MicrobioHeader::where('id', $request->id)->update([
                    'is_approved'  => 0,
                    'notes_reject' => $request->note,
                    'rejected_by'  => $this->karyawan,
                    'rejected_at'  => Carbon::now(),
                    'approved_by'  => null,
                    'approved_at'  => null,
                ]);

                if ($data) {
                    return response()->json(['message' => 'Berhasil, Silahkan Cek di Analys!', 'success' => true, 'status' => 200]);
                } else {
                    return response()->json(['message' => 'Gagal']);
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

            if ($request->kategori == 12 || $request->kategori == 46 || $request->kategori == 33) {
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
            $type_koreksi = $request->type;

            $id        = $request->id;
            $no_sampel = $request->no_sampel;
            $parameter = $request->parameter;

            $faktor_koreksi = (float) $request->faktor_koreksi;
            $hasilPengujian = html_entity_decode($request->hasil_pengujian);
            $hasilujic      = html_entity_decode($request->hasil_c);
            $hasilujic1     = html_entity_decode($request->hasil_c1);
            $hasilujic2     = html_entity_decode($request->hasil_c2);
            // dd($request->all());

            $hasil = $this->hitungKoreksi($request, $type_koreksi, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilPengujian, $hasilujic, $hasilujic1, $hasilujic2);

            // Format hasil menjadi 4 angka di belakang koma jika numerik
            if (is_numeric($hasil)) {
                $hasil = number_format((float) $hasil, 4, '.', '');
            }

            return response()->json(['hasil' => $hasil]);
        } catch (\Exception $e) {
            dd($e);
            \Log::error('Error dalam KalkulasiKoreksi: ' . $e->getMessage());
            return response()->json(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    private function hitungKoreksi($request, $type_koreksi, $id, $no_sampel, $faktor_koreksi, $parameter, $hasilPengujian, $hasilujic, $hasilujic1, $hasilujic2)
    {
        try {
            $hasil = 0;
            $hasil = $this->rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $hasilujic1, $hasilujic2);
            return $hasil;
        } catch (\Exception $e) {
            dd($e);
            \Log::error('Error dalam hitungKoreksi: ' . $e->getMessage());
            throw $e;
        }
    }

    public function rumusUdara($request, $no_sampel, $faktor_koreksi, $parameter, $hasilujic, $hasilujic1, $hasilujic2)
    {

        $po = OrderDetail::where('no_sampel', $no_sampel)
            ->where('is_active', 1)
            ->where('parameter', 'like', '%' . $parameter . '%') // Menambahkan kondisi where dengan like
            ->first();
        try {
            // Fungsi untuk menghapus karakter spesial dari nilai
            function removeSpecialChars($value)
            {
                return is_string($value) ? str_replace('<', '', $value) : $value;
            }

            // Fungsi untuk memeriksa apakah nilai mengandung karakter spesial '<'
            function cekSpecialChar($value)
            {
                return is_string($value) && strpos($value, '<') !== false;
            }

            // Fungsi untuk menerapkan rumus dengan cek terhadap nilai null
            function applyFormula($value, float $factor, $parameter)
            {
                $cleanedValue = removeSpecialChars($value); // Hapus karakter spesial saat perhitungan
                if ($cleanedValue == null || $cleanedValue === '') {
                    return '';
                }
                $hasil = '';
                $MDL   = floatval($cleanedValue);
                if (! is_nan($MDL)) {
                    if (cekSpecialChar($value)) { // Cek karakter spesial sebelum perhitungan
                        $hasil = (($MDL / 0.072) * ($factor / 100)) + ($MDL / 0.072);
                        return $hasil; // Tambahkan karakter spesial di depan hasil
                    } else {
                        $hasil = ($MDL * ($factor / 100)) + $MDL;
                        return $hasil;
                    }
                }
                return '';
            }

            $hasil = ['hasilc' => '', 'hasilc1' => '', 'hasilc2' => '']; // Default hasil

            $cases = [
                'SO2',
                "SO2 (6 Jam)",
                "SO2 (8 Jam)",
                "SO2 (24 Jam)",
                'NO2',
                "NO2 (6 Jam)",
                "NO2 (8 Jam)",
                "NO2 (24 Jam)",
                'O3',
                "O3 (8 Jam)",
                'TSP',
                "TSP (6 Jam)",
                "TSP (8 Jam)",
                "TSP (24 Jam)",
                'PM 2.5',
                "PM 2.5 (8 Jam)",
                "PM 2.5 (24 Jam)",
                'PM 10',
                "PM 10 (8 Jam)",
                "PM 10 (24 Jam)",
            ];

            foreach ($cases as $case) {
                // Cek apakah $case ada di dalam $parameter, memastikan perbandingan yang lebih spesifik
                if ($case == $parameter) {
                    // Jika ditemukan, lakukan perhitungan
                    $hasil['hasilc']  = (empty($hasilujic)) ? null : applyFormula($hasilujic, $faktor_koreksi, $parameter);
                    $hasil['hasilc1'] = (empty($hasilujic1)) ? null : applyFormula($hasilujic1, $faktor_koreksi, $parameter);
                    $hasil['hasilc2'] = (empty($hasilujic2)) ? null : applyFormula($hasilujic2, $faktor_koreksi, $parameter);

                    break;
                }

                if ($parameter == 'NO2' || $parameter == 'NO2 (24 Jam)' || $parameter == 'NO2 (8 Jam)' || $parameter == 'NO2 (6 Jam)') {
                    if ($hasil['hasilc'] < 0.4623) {
                        $hasil['hasilc'] = '<0.4623';
                    }

                    if ($hasil['hasilc1'] < 0.00046) {
                        $hasil['hasilc1'] = '<0.00046';
                    }

                    if ($hasil['hasilc2'] < 0.00025) {
                        $hasil['hasilc2'] = '<0.00025';
                    }
                }

                if ($parameter == 'SO2' || $parameter == 'SO2 (24 Jam)' || $parameter == 'SO2 (8 Jam)' || $parameter == 'SO2 (6 Jam)') {
                    // Pastikan hasil dari rumusUdara valid

                    if ($hasil['hasilc'] < 2.1531) {
                        $hasil['hasilc'] = '<2.1531';
                    }

                    if ($hasil['hasilc1'] < 0.0022) {
                        $hasil['hasilc1'] = '<0.0022';
                    }

                    if ($hasil['hasilc2'] < 0.00082) {
                        $hasil['hasilc2'] = '<0.00082';
                    }
                }

                if ($parameter == 'O3' || $parameter == 'O3 (8 Jam)') {
                    // Pastikan hasil dari rumusUdara valid

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

                if ($po->kategori_3 == 27) {
                    if ($parameter == 'TSP') {
                        // Pastikan hasil dari rumusUdara valid
                        if (! isset($hasil['hasilc'], $hasil['hasilc1'], $hasil['hasilc2'])) {
                            return response()->json(['message' => 'Hasil dari rumus tidak valid.'], 400);
                        }
                        // if($hasil['hasilc'] < 16.7){
                        //     $hasil['hasilc'] = '<16.7';
                        // }

                        // if($hasil['hasilc1'] < 0.0167){
                        //     $hasil['hasilc1'] = '<0.0167';
                        // }
                        if ($hasil['hasilc'] < 0.001) {
                            $hasil['hasilc'] = '<0.001';
                        }

                        if ($hasil['hasilc1'] < 0.001) {
                            $hasil['hasilc1'] = '<0.001';
                        }

                        $hasil['hasilc2'] = null;
                    } else if ($parameter == 'TSP (8 Jam)') {
                        if (! isset($hasil['hasilc'], $hasil['hasilc1'], $hasil['hasilc2'])) {
                            return response()->json(['message' => 'Hasil dari rumus tidak valid.'], 400);
                        }
                        // if($hasil['hasilc'] < 0.0021){
                        //     $hasil['hasilc'] = '<0.0021';
                        // }

                        // if($hasil['hasilc1'] < 2.1000){
                        //     $hasil['hasilc1'] = '<2.1000';
                        // }
                        if ($hasil['hasilc'] < 0.001) {
                            $hasil['hasilc'] = '<0.001';
                        }

                        if ($hasil['hasilc1'] < 0.001) {
                            $hasil['hasilc1'] = '<0.001';
                        }

                        $hasil['hasilc2'] = null;
                    }
                } else if ($po->kategori_3 == 11) {
                    if ($parameter == 'TSP') {
                        // Pastikan hasil dari rumusUdara valid
                        if (! isset($hasil['hasilc'], $hasil['hasilc1'], $hasil['hasilc2'])) {
                            return response()->json(['message' => 'Hasil dari rumus tidak valid.'], 400);
                        }
                        if ($hasil['hasilc'] < 1.5151) {
                            $hasil['hasilc'] = '<1.5151';
                        }

                        if ($hasil['hasilc1'] < 0.0015) {
                            $hasil['hasilc1'] = '<0.0015';
                        }
                        if ($hasil['hasilc2'] == 0.0) {
                            $hasil['hasilc2'] = '';
                        }
                    }

                    if ($parameter == 'TSP (24 Jam)') {
                        // Pastikan hasil dari rumusUdara valid
                        if (! isset($hasil['hasilc'], $hasil['hasilc1'], $hasil['hasilc2'])) {
                            return response()->json(['message' => 'Hasil dari rumus tidak valid.'], 400);
                        }
                        if ($hasil['hasilc'] < 0.0631) {
                            $hasil['hasilc'] = '<0.0631';
                        }

                        if ($hasil['hasilc1'] < 0.000063) {
                            $hasil['hasilc1'] = '<0.000063';
                        }
                        if ($hasil['hasilc2'] == 0.0) {
                            $hasil['hasilc2'] = '';
                        }
                    }
                }

                if ($parameter == 'PM 10' || $parameter == 'PM 10 (8 Jam)' || $parameter == 'PM 10 (24 Jam)') {
                    if ($hasil['hasilc'] < 0.56) {
                        $hasil['hasilc'] = '<0.56';
                    }

                    if ($hasil['hasilc1'] < 0.00056) {
                        $hasil['hasilc1'] = '<0.00056';
                    }
                    if ($hasil['hasilc2'] == 0.0) {
                        $hasil['hasilc2'] = '';
                    }
                }

                if ($parameter == 'PM 2.5' || $parameter == 'PM 2.5 (8 Jam)' || $parameter == 'PM 2.5 (24 Jam)') {

                    if ($hasil['hasilc'] < 0.58) {
                        $hasil['hasilc'] = '<0.58';
                    }

                    if ($hasil['hasilc1'] < 0.00058) {
                        $hasil['hasilc1'] = '<0.00058';
                    }
                    if ($hasil['hasilc2'] == 0.0) {
                        $hasil['hasilc2'] = '';
                    }
                }
            }
            return $hasil; // Mengembalikan array hasil
        } catch (\Exception $e) {
            // Log error atau lakukan penanganan kesalahan yang sesuai
            \Log::error('Error in rumusUdara: ' . $e->getMessage());
            return ['error' => 'Terjadi kesalahan saat memproses data'];
        }
    }

    public function saveData(Request $request)
    {
        $kategori_koreksi = $request->kategori;

        $id        = $request->id;
        $no_sampel = $request->no_sampel;
        $parameter = $request->parameter;

        $faktor_koreksi = (float) $request->faktor_koreksi;
        $hasil_c        = $request->hasil_c;
        $hasil_c1       = $request->hasil_c1;
        $hasil_c2       = $request->hasil_c2;

        if ($kategori_koreksi) {
            switch ($kategori_koreksi) {
                //AIR
                case '11':
                    $udara = LingkunganHeader::with('ws_value_linkungan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_active', 1)->first();

                    return $this->handleLingkungan($request, $no_sampel, $parameter, $hasil_c, $hasil_c1, $hasil_c2, $udara, $faktor_koreksi);
                case '27':
                    $udara = LingkunganHeader::with('ws_value_linkungan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_active', 1)->first();

                    return $this->handleLingkungan($request, $no_sampel, $parameter, $hasil_c, $hasil_c1, $hasil_c2, $udara, $faktor_koreksi);

                default:
                    return response()->json(['message' => 'Type koreksi tidak valid.'], 400);
            }
        } else {
            return response()->json(['message' => 'Type koreksi harus diisi.'], 400);
        }
    }

    private function handleLingkungan($request, $no_sampel, $parameter, $hasil_c, $hasil_c1, $hasil_c2, $udara, $faktor_koreksi)
    {
        try {
            // dd($faktor_koreksi, $udara);
            DB::beginTransaction();
            $po = OrderDetail::where('no_sampel', $no_sampel)
                ->where('is_active', 1)
                ->where('parameter', 'like', '%' . $parameter . '%')
                ->first();
            if ($po) {
                $lingkungan = LingkunganHeader::where('no_sampel', $no_sampel)
                    ->where('parameter', $parameter)
                    ->where('is_active', 1)
                    ->first();

                if ($lingkungan) {
                    $valuews = WsValueLingkungan::where('no_sampel', $no_sampel)
                        ->where('lingkungan_header_id', $lingkungan->id)
                        ->where('is_active', 1)
                        ->first();

                    if ($lingkungan->tipe_koreksi == null) {
                        $nomor = 1;
                    } else {
                        if ($lingkungan->tipe_koreksi < 3) {
                            $nomor = $lingkungan->tipe_koreksi + 1;
                        } else {
                            return response()->json(['message' => 'Koreksi tidak bisa dilakukan lagi.'], 400);
                        }
                    }
                    $lingkungan->tipe_koreksi = $nomor;
                    $lingkungan->save();

                    if (! str_contains((string) $hasil_c, '<')) {
                        $hasil_c = number_format((float) $hasil_c, 4, '.', '');
                    }
                    if (! str_contains((string) $hasil_c1, '<')) {
                        $hasil_c1 = number_format((float) $hasil_c1, 4, '.', '');
                    }
                    if (! str_contains((string) $hasil_c2, '<')) {
                        $hasil_c2 = number_format((float) $hasil_c2, 4, '.', '');
                    }

                    if ($valuews) {
                        $valuews->f_koreksi_c   = $hasil_c;
                        $valuews->f_koreksi_c1  = $hasil_c1;
                        $valuews->f_koreksi_c2  = $hasil_c2;
                        $valuews->input_koreksi = $faktor_koreksi;
                        $valuews->save();
                    } else {
                        return response()->json(['message' => 'Data Valuews tidak ditemukan.'], 404);
                    }

                    DB::commit();
                    return response()->json(['message' => 'Data berhasil diupdate.', 'status' => 200, "success" => true], 200);
                }
            } else {
                return response()->json(['message' => 'Data tidak ditemukan di kategori AIR.'], 404);
            }
        } catch (\Exception $ex) {
            DB::rollBack();
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
        $data = MasterRegulasi::where('id_kategori', 4)
            ->where('is_active', true)
            ->get();
        return response()->json([
            'data' => $data,
        ], 200);
    }

    public function getTableRegulasi(Request $request)
    {
        $data = DB::table('tabel_regulasi')
            ->whereJsonContains('id_regulasi', (string) $request->id)
            ->first();

        return response()->json([
            'data' => $data,
        ], 200);
    }

    public function handleReject(Request $request)
    {
        DB::beginTransaction();
        try {
            MicrobioHeader::where('no_sampel', $request->no_sampel)
                ->update([
                    'is_active'   => 0,
                    'rejected_by' => $this->karyawan,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil direject.',
                'success' => true,
                'status'  => 200,
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal mereject data: ' . $th->getMessage(),
                'success' => false,
                'status'  => 500,
            ], 500);
        }
    }

    public function handleApproveSelected(Request $request)
    {
        DB::beginTransaction();
        try {
            OrderDetail::whereIn('no_sampel', $request->no_sampel_list)->update(['status' => 1]);

            MicrobioHeader::whereIn('no_sampel', $request->no_sampel_list)
                ->update([
                    'lhps' => 1,
                ]);

            DB::commit();

            return response()->json([
                'message' => 'Data berhasil diapprove.',
                'success' => true,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal mengapprove data: ' . $th->getMessage(),
            ]);
        }
    }
}
