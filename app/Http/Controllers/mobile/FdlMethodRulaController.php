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
use App\Services\InsertActivityFdl;
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

class FdlMethodRulaController extends Controller
{
    public function getSample(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

        // Check if method is valid and execute accordingly
        $method = 3;
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
                if (is_array($value)) {
                    // langsung merge karena sudah array
                    $parsedData[$key] = $value;
                } else {
                    // kalau string query, baru diparse
                    parse_str($key . '=' . $value, $output);
                    $parsedData = array_merge_recursive($parsedData, $output);
                }
            }
            $data = [
                'skor_A' => $parsedData['skor_A'] ?? [],
                'skor_B' => $parsedData['skor_B'] ?? [],
            ];

            function extractScore($value)
            {
                if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                    return (int) $matches[1];
                }
                return (int) $value; // karena kamu kirim int, bukan string "1-..."
            }

            // Matchup Tabel A
            $tabelA = [
                1 => [
                    1 => [1 => [1 => 1, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 2, 2 => 3], 4 => [1 => 3, 2 => 3]],
                    2 => [1 => [1 => 2, 2 => 2], 2 => [1 => 2, 2 => 2], 3 => [1 => 3, 2 => 3], 4 => [1 => 3, 2 => 3]],
                    3 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 3], 4 => [1 => 4, 2 => 4]],
                ],
                2 => [
                    1 => [1 => [1 => 2, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]],
                    2 => [1 => [1 => 3, 2 => 3], 2 => [1 => 3, 2 => 3], 3 => [1 => 3, 2 => 4], 4 => [1 => 4, 2 => 4]],
                    3 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                ],
                3 => [
                    1 => [1 => [1 => 3, 2 => 3], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                    2 => [1 => [1 => 3, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 4], 4 => [1 => 5, 2 => 5]],
                    3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]],
                ],
                4 => [
                    1 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 4, 2 => 5], 4 => [1 => 5, 2 => 5]],
                    2 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 4], 3 => [1 => 5, 2 => 5], 4 => [1 => 5, 2 => 5]],
                    3 => [1 => [1 => 4, 2 => 4], 2 => [1 => 4, 2 => 5], 3 => [1 => 5, 2 => 5], 4 => [1 => 6, 2 => 6]],
                ],
                5 => [
                    1 => [1 => [1 => 5, 2 => 5], 2 => [1 => 5, 2 => 5], 3 => [1 => 5, 2 => 6], 4 => [1 => 6, 2 => 7]],
                    2 => [1 => [1 => 5, 2 => 6], 2 => [1 => 6, 2 => 6], 3 => [1 => 6, 2 => 7], 4 => [1 => 7, 2 => 7]],
                    3 => [1 => [1 => 6, 2 => 6], 2 => [1 => 6, 2 => 7], 3 => [1 => 7, 2 => 7], 4 => [1 => 7, 2 => 8]],
                ],
                6 => [
                    1 => [1 => [1 => 7, 2 => 7], 2 => [1 => 7, 2 => 7], 3 => [1 => 7, 2 => 8], 4 => [1 => 8, 2 => 9]],
                    2 => [1 => [1 => 8, 2 => 8], 2 => [1 => 8, 2 => 8], 3 => [1 => 8, 2 => 9], 4 => [1 => 9, 2 => 9]],
                    3 => [1 => [1 => 9, 2 => 9], 2 => [1 => 9, 2 => 9], 3 => [1 => 9, 2 => 9], 4 => [1 => 9, 2 => 9]],
                ],
            ];

            // Matchup tabel B dari gambar
            $tabelB = [
                1 => [ // leher = 1
                    1 => [1 => 1, 2 => 3],
                    2 => [1 => 2, 2 => 3],
                    3 => [1 => 3, 2 => 4],
                    4 => [1 => 5, 2 => 5],
                    5 => [1 => 6, 2 => 6],
                    6 => [1 => 7, 2 => 7],
                ],
                2 => [ // leher = 2
                    1 => [1 => 2, 2 => 3],
                    2 => [1 => 2, 2 => 3],
                    3 => [1 => 4, 2 => 5],
                    4 => [1 => 5, 2 => 5],
                    5 => [1 => 6, 2 => 7],
                    6 => [1 => 7, 2 => 7],
                ],
                3 => [
                    1 => [1 => 3, 2 => 3],
                    2 => [1 => 3, 2 => 4],
                    3 => [1 => 4, 2 => 5],
                    4 => [1 => 5, 2 => 6],
                    5 => [1 => 6, 2 => 7],
                    6 => [1 => 7, 2 => 7],
                ],
                4 => [
                    1 => [1 => 5, 2 => 5],
                    2 => [1 => 5, 2 => 6],
                    3 => [1 => 6, 2 => 7],
                    4 => [1 => 7, 2 => 7],
                    5 => [1 => 7, 2 => 7],
                    6 => [1 => 8, 2 => 8],
                ],
                5 => [
                    1 => [1 => 7, 2 => 7],
                    2 => [1 => 7, 2 => 7],
                    3 => [1 => 7, 2 => 8],
                    4 => [1 => 8, 2 => 8],
                    5 => [1 => 8, 2 => 8],
                    6 => [1 => 8, 2 => 8],
                ],
                6 => [
                    1 => [1 => 8, 2 => 8],
                    2 => [1 => 8, 2 => 8],
                    3 => [1 => 8, 2 => 8],
                    4 => [1 => 8, 2 => 9],
                    5 => [1 => 9, 2 => 9],
                    6 => [1 => 9, 2 => 9],
                ],
            ];

            // Tabel C berdasarkan gambar
            $tabelC = [
                1 => [1 => 1, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 5],
                2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 5],
                3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 4, 6 => 5, 7 => 6],
                4 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 6],
                5 => [1 => 4, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 7],
                6 => [1 => 4, 2 => 4, 3 => 5, 4 => 6, 5 => 6, 6 => 7, 7 => 7],
                7 => [1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 7, 7 => 7],
                8 => [1 => 5, 2 => 5, 3 => 6, 4 => 7, 5 => 7, 6 => 7, 7 => 7],
            ];

            $hasilPerBagian = [];
            foreach ($data as $kategori => $bagian) {
                foreach ($bagian as $key => $item) {
                    $hasilPerBagian[$kategori][$key] = 0;

                    if (is_array($item)) {
                        foreach ($item as $subKey => $val) {
                            if ($kategori == 'skor_A' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
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

            // ✅ Ambil nilai skor_A dari tabel
            $lengan_atas = $hasilPerBagian['skor_A']['lengan_atas'] ?? 0;
            $lengan_bawah = $hasilPerBagian['skor_A']['lengan_bawah'] ?? 0;
            $pergelangan_tangan  = $hasilPerBagian['skor_A']['pergelangan_tangan'] ?? 0;
            $tangan_memuntir = $hasilPerBagian['skor_A']['tangan_memuntir'] ?? 0;
            $aktivitas_otot = $hasilPerBagian['skor_A']['aktivitas_otot'] ?? 0;
            $bebanA = $hasilPerBagian['skor_A']['beban'] ?? 0;

            // Validasi nilai input agar tidak di luar batas tabel
            $lengan_atas = min(max(1, $lengan_atas), 6);
            $lengan_bawah = min(max(1, $lengan_bawah), 3);
            $pergelangan_tangan = min(max(1, $pergelangan_tangan), 4);
            $tangan_memuntir = min(max(1, $tangan_memuntir), 2);

            // Ambil skor dari tabel
            $nilaiTabelA = $tabelA[$lengan_atas][$lengan_bawah][$pergelangan_tangan][$tangan_memuntir] ?? null;
            $totalSkorA = $nilaiTabelA + $bebanA + $aktivitas_otot;

            // ✅ Ambil nilai skor_B dari tabel
            $leher = $hasilPerBagian['skor_B']['leher'] ?? 0;
            $badan = $hasilPerBagian['skor_B']['badan'] ?? 0;
            $kaki  = $hasilPerBagian['skor_B']['kaki'] ?? 0;
            $bebanB = $hasilPerBagian['skor_B']['beban'] ?? 0;
            $aktivitas_ototB = $hasilPerBagian['skor_B']['aktivitas_otot'] ?? 0;

            // Validasi agar dalam batas
            $leher = min(max(1, $leher), 6);
            $badan = min(max(1, $badan), 6);
            $kaki  = ($kaki > 1) ? 2 : 1; // hanya 1 atau 2

            // Ambil nilai dari tabel
            $nilaiTabelB = $tabelB[$leher][$badan][$kaki] ?? null;
            $totalSkorB = $nilaiTabelB + $bebanB + $aktivitas_ototB;

            // PENENTUAN SKOR C
            $baris = $totalSkorA > 8 ? 8 : $totalSkorA;
            $kolom = $totalSkorB > 7 ? 7 : $totalSkorB;
            // Ambil skor C
            $skorC = $tabelC[$baris][$kolom] ?? null;


            $pengukuran = [
                "skor_A" => $request->skor_A,
                'lengan_atas' => $lengan_atas,
                'lengan_bawah' => $lengan_bawah,
                'pergelangan_tangan' => $pergelangan_tangan,
                'tangan_memuntir' => $tangan_memuntir,
                'aktivitas_otot_A' => $aktivitas_otot,
                'beban_A' => $bebanA,
                "skor_B" => $request->skor_B,
                'leher' => $leher,
                'badan' => $badan,
                'kaki' => $kaki,
                'aktivitas_otot_B' => $aktivitas_ototB,
                'beban_B' => $bebanB,
                'nilai_tabel_A' => $nilaiTabelA,
                'nilai_tabel_B' => $nilaiTabelB,
                'total_skor_A' => $totalSkorA,
                'total_skor_B' => $totalSkorB,
                'skor_rula' => $skorC
            ];
            
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
            $data->pengukuran = json_encode($pengukuran, JSON_UNESCAPED_SLASHES);
            if ($request->foto_samping_kiri != '')
                $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
            if ($request->foto_samping_kanan != '')
                $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
            if ($request->foto_depan != '')
                $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
            if ($request->foto_belakang != '')
                $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
            $data->aktivitas_ukur = $request->aktivitas_ukur;
            $data->permission = $request->permission;
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
            InsertActivityFdl::by($this->user_id)->action('input')->target("Method Rula pada nomor sampel $request->no_sample")->save();

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
    //             if ($request->method == 2) {
    //                 $data = DataLapanganErgonomi::with('detail')->where('method', 2)
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
            ->where('created_by', $this->karyawan)->where('method', 3)
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
            $no_sample = $cek->no_sampel;
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
            InsertActivityFdl::by($this->user_id)->action('delete')->target("Method R dengan nomor sampel $no_sample")->save();
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
