<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganErgonomi;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

// SERVICE
use App\Services\SendTelegram;
use App\Services\GetAtasan;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlMethodRebaController extends Controller
{
    public function getSample(Request $request)
    {
        // dd($request->all());
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('method', 1)->first();

        if(!$fdl){
            return response()->json(['message' => 'Tidak ada data berdasarkan No. Sample tersebut.'], 401);
        }
        $method = 2;
        if ($method >= 1 && $method <= 10) {
            return $this->processMethod($request, $fdl, $method);
        }

        return response()->json(['message' => 'Method not found.'], 400);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $rawData = $request->all();

            $parsedData = [];
            foreach ($rawData as $key => $value) {
                parse_str($key . '=' . $value, $output);
                $parsedData = array_merge_recursive($parsedData, $output);
            }

            $data = [
                'skor_A' => $parsedData['skor_A'] ?? [],
                'skor_B' => $parsedData['skor_B'] ?? [],
                'skor_C' => $parsedData['skor_C'] ?? [],
            ];


            function extractScore($value)
            {
                if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                    return (int) $matches[1];
                }
                return (int) $value; // karena kamu kirim int, bukan string "1-..."
            }


            $tableA = [
                1 => [ // badan 1
                    1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
                    2 => [1 => 1, 2 => 2, 3 => 3, 4 => 4],
                    3 => [1 => 3, 2 => 3, 3 => 5, 4 => 6],
                ],
                2 => [ // badan 2
                    1 => [1 => 2, 2 => 3, 3 => 4, 4 => 5],
                    2 => [1 => 3, 2 => 4, 3 => 5, 4 => 6],
                    3 => [1 => 4, 2 => 5, 3 => 6, 4 => 7],
                ],
                3 => [ // badan 3
                    1 => [1 => 2, 2 => 4, 3 => 5, 4 => 6],
                    2 => [1 => 4, 2 => 5, 3 => 6, 4 => 7],
                    3 => [1 => 5, 2 => 6, 3 => 7, 4 => 8],
                ],
                4 => [ // badan 4
                    1 => [1 => 3, 2 => 5, 3 => 6, 4 => 7],
                    2 => [1 => 5, 2 => 6, 3 => 7, 4 => 8],
                    3 => [1 => 6, 2 => 7, 3 => 8, 4 => 9],
                ],
                5 => [ // badan 5
                    1 => [1 => 4, 2 => 6, 3 => 7, 4 => 8],
                    2 => [1 => 6, 2 => 7, 3 => 8, 4 => 9],
                    3 => [1 => 7, 2 => 8, 3 => 9, 4 => 9],
                ],
            ];


            $tableB = [
                1 => [ // lengan_atas = 1
                    1 => [1 => 1, 2 => 2, 3 => 2],
                    2 => [1 => 1, 2 => 2, 3 => 3],
                ],
                2 => [
                    1 => [1 => 1, 2 => 2, 3 => 3],
                    2 => [1 => 2, 2 => 3, 3 => 4],
                ],
                3 => [
                    1 => [1 => 3, 2 => 4, 3 => 5],
                    2 => [1 => 4, 2 => 5, 3 => 5],
                ],
                4 => [
                    1 => [1 => 4, 2 => 5, 3 => 5],
                    2 => [1 => 5, 2 => 6, 3 => 7],
                ],
                5 => [
                    1 => [1 => 6, 2 => 7, 3 => 8],
                    2 => [1 => 7, 2 => 8, 3 => 8],
                ],
                6 => [
                    1 => [1 => 7, 2 => 8, 3 => 8],
                    2 => [1 => 8, 2 => 9, 3 => 9],
                ],
            ];

            $tableC = [
                1 => [1 => 1, 2 => 1, 3 => 1, 4 => 2, 5 => 3, 6 => 3, 7 => 4, 8 => 5, 9 => 6, 10 => 7, 11 => 7, 12 => 7],
                2 => [1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 4, 7 => 5, 8 => 6, 9 => 6, 10 => 7, 11 => 7, 12 => 8],
                3 => [1 => 2, 2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 7, 10 => 8, 11 => 8, 12 => 8],
                4 => [1 => 3, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 8, 10 => 9, 11 => 9, 12 => 9],
                5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8, 8 => 8, 9 => 9, 10 => 9, 11 => 9, 12 => 9],
                6 => [1 => 6, 2 => 6, 3 => 6, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 10, 10 => 10, 11 => 10, 12 => 10],
                7 => [1 => 7, 2 => 7, 3 => 7, 4 => 8, 5 => 9, 6 => 9, 7 => 9, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
                8 => [1 => 8, 2 => 8, 3 => 8, 4 => 9, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 11, 11 => 11, 12 => 11],
                9 => [1 => 9, 2 => 9, 3 => 9, 4 => 10, 5 => 10, 6 => 10, 7 => 11, 8 => 11, 9 => 11, 10 => 12, 11 => 12, 12 => 12],
                10 => [1 => 10, 2 => 10, 3 => 10, 4 => 11, 5 => 11, 6 => 11, 7 => 11, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
                11 => [1 => 11, 2 => 11, 3 => 11, 4 => 11, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
                12 => [1 => 12, 2 => 12, 3 => 12, 4 => 12, 5 => 12, 6 => 12, 7 => 12, 8 => 12, 9 => 12, 10 => 12, 11 => 12, 12 => 12],
            ];


            $hasilPerBagian = [];

            foreach ($data as $kategori => $bagian) {
                foreach ($bagian as $key => $item) {
                    $hasilPerBagian[$kategori][$key] = 0;

                    if (is_array($item)) {
                        foreach ($item as $subKey => $val) {
                            if ($kategori == 'skor_B' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
                                $hasilPerBagian[$kategori][$key] -= extractScore($val); // dikurang
                            } else {
                                $hasilPerBagian[$kategori][$key] += extractScore($val);
                            }
                        }
                    } else {
                        $hasilPerBagian[$kategori][$key] += extractScore($item);
                    }
                }
            }

            // dd($hasilPerBagian);


            // ✅ Ambil nilai skor_A dari tabel
            $leher = $hasilPerBagian['skor_A']['leher'] ?? 0;
            $badan = $hasilPerBagian['skor_A']['badan'] ?? 0;
            $kaki  = $hasilPerBagian['skor_A']['kaki'] ?? 0;
            $beban = $hasilPerBagian['skor_A']['beban'] ?? 0;

            $skorA_dari_tabel = $tableA[$badan][$leher][$kaki] ?? 0;
            $totalSkorA = $skorA_dari_tabel + $beban;

            // ✅ Ambil nilai skor_B dari tabel
            $lengan_atas  = $hasilPerBagian['skor_B']['lengan_atas'] ?? 0;
            $lengan_bawah = $hasilPerBagian['skor_B']['lengan_bawah'] ?? 0;
            $pergelangan  = $hasilPerBagian['skor_B']['pergelangan_tangan'] ?? 0;
            $pegangan     = $hasilPerBagian['skor_B']['pegangan'] ?? 0;

            $skorB_dari_tabel = $tableB[$lengan_atas][$lengan_bawah][$pergelangan] ?? 0;
            $totalSkorB = $skorB_dari_tabel + $pegangan;

            // ✅ Ambil nilai skor_C dari tabel
            $aktivitasi_otot = $hasilPerBagian['skor_C']['aktivitas_otot'] ?? 0;
            $skorC_dari_tabel = $tableC[$totalSkorA][$totalSkorB] ?? 0;
            $totalSkorC = $aktivitasi_otot + $skorC_dari_tabel;

            $pengukuran = [
                "skor_A" => $request->skor_A,
                'skor_leher' => $leher,
                'skor_badan' => $badan,
                'skor_kaki' => $kaki,
                'skor_beban' => $beban,
                'nilai_tabel_a' => $skorA_dari_tabel,
                'total_skor_a' => $totalSkorA,
                "skor_B" => $request->skor_B,
                'skor_lengan_atas' => $lengan_atas,
                'skor_lengan_bawah' => $lengan_bawah,
                'skor_pergelangan_tangan' => $pergelangan,
                'skor_pegangan' => $pegangan,
                'nilai_tabel_b' => $skorB_dari_tabel,
                'total_skor_b' => $totalSkorB,
                "skor_C" => $request->skor_C,
                'skor_aktivitas_otot' => $aktivitasi_otot,
                'nilai_tabel_c' => $skorC_dari_tabel,
                'final_skor_reba' => $totalSkorC
            ];
            // dd($pengukuran);

            $data = new DataLapanganErgonomi();
            if ($request->no_order != '')
                $data->no_order = $request->no_order;

            if (strtoupper(trim($request->no_sample)) != '')
                $data->no_sampel = strtoupper(trim($request->no_sample));
            if ($request->pekerja != '')
                $data->nama_pekerja = $request->pekerja;
            if ($request->divisi != '')
                $data->divisi = $request->divisi;
            if ($request->usia != '')
                $data->usia = $request->usia;
            $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
            if ($request->kelamin != '')
                $data->jenis_kelamin = $request->kelamin;
            if ($request->waktu_bekerja != '')
                $data->waktu_bekerja = $request->waktu_bekerja;
            if ($request->aktivitas != '')
                $data->aktivitas = $request->aktivitas;
            $data->method = 3;

            $data->pengukuran = json_encode($pengukuran);

            if ($request->foto_samping_kiri != '')
                $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
            if ($request->foto_samping_kanan != '')
                $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
            if ($request->foto_depan != '')
                $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
            if ($request->foto_belakang != '')
                $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
            $data->aktivitas_ukur = $request->aktivitas_ukur;
            $data->permission = $request->permis;
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // UPDATE ORDER DETAIL
            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->update([
                    'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil disimpan.'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 401);
        }
    }

    // public function index(Request $request)
    // {
    //     try {
    //         $data = array();
    //         if ($request->tipe != '') {
    //             $data = DataLapanganErgonomi::with('detail')->orderBy('id', 'desc');
    //         } else {
    //             if ($request->method == 1) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 1)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 2) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 2)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 3) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 3)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 4) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 4)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 5) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 5)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 6) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 6)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');;
    //             } else if ($request->method == 7) {
    //                 $data = DataLapanganErgonomi::with('detail')
    //                     ->where('method', 7)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 8) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 8)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 9) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 9)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             } else if ($request->method == 10) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 10)
    //                     ->whereDate('created_at', '>=', Carbon::now()->subDays(3))
    //                     ->orderBy('id', 'desc');
    //             }
    //         }
    //         return Datatables::of($data)->make(true);
    //     } catch (Exception $e) {
    //         dd($e);
    //     }
    // }
    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganErgonomi::with('detail')
            ->where('created_by', $this->karyawan)->where('method', 2)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(3));

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('no_sampel', 'like', "%$search%")
                    ->orWhereHas('detail', function ($q2) use ($search) {
                        $q2->where('nama_perusahaan', 'like', "%$search%");
                    });
            });
        }

        $data = $query->orderBy('id', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);

        return response()->json($data);
    }

    public function approve(Request $request)
    {
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganErgonomi::where('id', $request->id)->first();
                $data->is_approve = true;
                $data->approved_by = $this->karyawan;
                $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Approved',
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Gagal Approve'
                ], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    public function detail(Request $request)
    {
        if ($request->tipe != '') {
            $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
            $po = OrderDetail::where('no_sampel', $data->no_sampel)->where('is_active', true)->first();
            $this->resultx = 'get Detail ergonomi success';
            return response()->json([
                'data_lapangan' => $data,
                'data_po' => $po,
            ], 200);
        } else {
            if ($request->method == 1) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'sebelum_kerja' => $data->sebelum_kerja,
                    'setelah_kerja' => $data->setelah_kerja,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                ], 200);
            } else if ($request->method == 2) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';
                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 3) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 4) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 5) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'berat_beban' => $data->berat_beban,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'frek_jml_angkatan' => $data->frekuensi_jumlah_angkatan,
                    'kopling_tangan' => $data->kopling_tangan,
                    'jarak_vertikal' => $data->jarak_vertikal,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 6) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'durasi_jam_kerja' => $data->durasi_jam_kerja,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 7) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 8) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 9) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            } else if ($request->method == 10) {
                $data = DataLapanganErgonomi::with('detail')->where('id', $request->id)->first();
                // dd($data);
                $this->resultx = 'get Detail ergonomi success';

                return response()->json([
                    'id' => $data->id,
                    'sampler' => $data->created_by,
                    'nama_perusahaan' => $data->detail->nama_perusahaan,
                    'no_sample' => $data->no_sampel,
                    'no_order' => $data->no_order,
                    'pekerja' => $data->nama_pekerja,
                    'divisi' => $data->divisi,
                    'usia' => $data->usia,
                    'lama_kerja' => $data->lama_kerja,
                    'kelamin' => $data->jenis_kelamin,
                    'waktu_bekerja' => $data->waktu_bekerja,
                    'aktivitas' => $data->aktivitas,
                    'pengukuran' => $data->pengukuran,
                    'add_at' => $data->created_at,
                    'foto_samping_kiri' => $data->foto_samping_kiri,
                    'foto_samping_kanan' => $data->foto_samping_kanan,
                    'foto_depan' => $data->foto_depan,
                    'foto_belakang' => $data->foto_belakang,
                    'catatan_reject' => $data->catatan_reject_fdl,
                    'aktivitas_ukur' => $data->aktivitas_ukur,
                ], 200);
            }
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $cek = DataLapanganErgonomi::where('id', $request->id)->first();
            $foto_samping_kiri = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kiri;
            $foto_samping_kanan = public_path() . '/dokumentasi/sampling/' . $cek->foto_samping_kanan;
            $foto_depan = public_path() . '/dokumentasi/sampling/' . $cek->foto_depan;
            $foto_belakang = public_path() . '/dokumentasi/sampling/' . $cek->foto_belakang;
            if (is_file($foto_samping_kiri)) {
                unlink($foto_samping_kiri);
            }
            if (is_file($foto_samping_kanan)) {
                unlink($foto_samping_kanan);
            }
            if (is_file($foto_depan)) {
                unlink($foto_depan);
            }
            if (is_file($foto_belakang)) {
                unlink($foto_belakang);
            }
            $cek->delete();
            return response()->json([
                'message' => 'Data has ben Deleted',
                'cat' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    private function processMethod($request, $fdl, $method)
    {
        try {
            // Check for the existence of the sample with the appropriate category and parameter
            $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->whereIn('kategori_3', ['27-Udara Lingkungan Kerja', '11-Udara Ambient', '53-Ergonomi'])
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->where('is_active', true)
                ->first();

            // Check if the data for the given method already exists
            $data = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('method', $method)
                ->first();

            // Respond based on whether the data already exists
            if ($check) {
                if ($data) {
                    return response()->json(['message' => 'No. Sample sudah di input.'], 401);
                } else {
                    return response()->json([
                        'message' => 'Successful.',
                        'data' => $fdl
                    ], 200);
                }
            } else {
                return response()->json(['message' => 'Tidak ada parameter Ergonomi berdasarkan No. Sample tersebut.'], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    // JUMLAH SKOR POSTUR TUBUH
    private function calculateTotalDurasi($data)
    {
        $totalDurasi = 0;

        // Periksa apakah data adalah array
        if (is_array($data)) {
            foreach ($data as $section => $values) {
                // Periksa apakah $values adalah array
                if (is_array($values)) {
                    foreach ($values as $subSection => $details) {
                        // Periksa apakah detail memiliki 'Durasi Gerakan'
                        if (isset($details['Durasi Gerakan']) && $details['Durasi Gerakan'] !== 'Tidak') {
                            // Cek apakah nilai 'Durasi Gerakan' dapat dipisah dengan benar
                            $durasi = explode(';', $details['Durasi Gerakan'])[0];

                            // Pastikan durasi adalah angka
                            if (is_numeric($durasi)) {
                                $totalDurasi += (int)$durasi;
                            } else {
                                // Tambahkan log untuk kasus durasi yang tidak valid
                                // Misalnya, jika nilai 'Durasi Gerakan' tidak bisa diproses
                                Log::warning("Durasi Gerakan tidak valid: {$details['Durasi Gerakan']}");
                            }
                        }
                    }
                }
            }
        }

        return $totalDurasi;
    }

    public function convertImg($foto = '', $type = '', $user = '')
    {
        $img = str_replace('data:image/jpeg;base64,', '', $foto);
        $file = base64_decode($img);
        $safeName = DATE('YmdHis') . '_' . $user . $type . '.jpeg';
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        $success = file_put_contents($destinationPath . $safeName, $file);
        return $safeName;
    }
}
