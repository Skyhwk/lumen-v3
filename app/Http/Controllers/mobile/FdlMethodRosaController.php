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

class FdlMethodRosaController extends Controller
{
    public function getSample(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

        // Check if method is valid and execute accordingly
        $method = 4;
        if ($method >= 1 && $method <= 10) {
            return $this->processMethod($request, $fdl, $method);
        }

        return response()->json(['message' => 'Method not found.'], 400);
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            // Matching Data
            $sectionA = [
                2 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                3 => [2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                4 => [2 => 3, 3 => 3, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                5 => [2 => 4, 3 => 4, 4 => 4, 5 => 4, 6 => 5, 7 => 6, 8 => 7, 9 => 8],
                6 => [2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                7 => [2 => 6, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
                8 => [2 => 7, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9, 8 => 9, 9 => 9],
            ];

            $sectionB = [
                0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                1 => [0 => 1, 1 => 1, 2 => 2, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 3, 5 => 4, 6 => 6, 7 => 7],
                3 => [0 => 2, 1 => 2, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 8],
                4 => [0 => 3, 1 => 3, 2 => 4, 3 => 4, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                5 => [0 => 4, 1 => 4, 2 => 5, 3 => 5, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
                6 => [0 => 5, 1 => 5, 2 => 6, 3 => 7, 4 => 8, 5 => 8, 6 => 9, 7 => 9],
            ];

            $sectionC = [
                0 => [0 => 1, 1 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6],
                1 => [0 => 1, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
                2 => [0 => 1, 1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7],
                3 => [0 => 2, 1 => 3, 2 => 3, 3 => 3, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                4 => [0 => 3, 1 => 4, 2 => 4, 3 => 5, 4 => 5, 5 => 6, 6 => 7, 7 => 8],
                5 => [0 => 4, 1 => 5, 2 => 5, 3 => 6, 4 => 6, 5 => 7, 6 => 8, 7 => 9],
                6 => [0 => 5, 1 => 6, 2 => 6, 3 => 7, 4 => 7, 5 => 8, 6 => 8, 7 => 9],
                7 => [0 => 6, 1 => 7, 2 => 7, 3 => 8, 4 => 8, 5 => 9, 6 => 9, 7 => 9],
            ];

            $sectionD = [
                1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9],
                7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9],
                8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9],
                9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9],
            ];

            $skorRosa = [
                1 => [1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                2 => [1 => 2, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                3 => [1 => 3, 2 => 3, 3 => 3, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                4 => [1 => 4, 2 => 4, 3 => 4, 4 => 4, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                5 => [1 => 5, 2 => 5, 3 => 5, 4 => 5, 5 => 5, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                6 => [1 => 6, 2 => 6, 3 => 6, 4 => 6, 5 => 6, 6 => 6, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                7 => [1 => 7, 2 => 7, 3 => 7, 4 => 7, 5 => 7, 6 => 7, 7 => 7, 8 => 8, 9 => 9, 10 => 10],
                8 => [1 => 8, 2 => 8, 3 => 8, 4 => 8, 5 => 8, 6 => 8, 7 => 8, 8 => 8, 9 => 9, 10 => 10],
                9 => [1 => 9, 2 => 9, 3 => 9, 4 => 9, 5 => 9, 6 => 9, 7 => 9, 8 => 9, 9 => 9, 10 => 10],
                10 => [1 => 10, 2 => 10, 3 => 10, 4 => 10, 5 => 10, 6 => 10, 7 => 10, 8 => 10, 9 => 10, 10 => 10],
            ];

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
                'section_A' => $parsedData['section_A'] ?? [],
                'section_B' => $parsedData['section_B'] ?? [],
                'section_C' => $parsedData['section_C'] ?? [],
            ];

            function extractScore($value)
            {
                if (is_string($value) && preg_match('/^(\d+)-/', $value, $matches)) {
                    return (int) $matches[1];
                }
                return (int) $value; // karena kamu kirim int, bukan string "1-..."
            }

            $hasilPerBagian = [];
            foreach ($data as $kategori => $bagian) {
                foreach ($bagian as $key => $item) {
                    $hasilPerBagian[$kategori][$key] = 0;

                    if (is_array($item)) {
                        foreach ($item as $subKey => $val) {
                            if ($kategori == 'section_A' && $key == 'lengan_atas' && $subKey == 'tambahan_3') {
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


            // SECTION A
            $tinggi_kursi = $hasilPerBagian['section_A']['tinggi_kursi'] ?? 0;
            $lebar_dudukan = $hasilPerBagian['section_A']['lebar_dudukan'] ?? 0;
            $durasi_kerja_tinggi_kursi_dan_lebar_dudukan = $hasilPerBagian['section_A']['durasi_kerja_bagian_kursi'] ?? 0;
            $sandaran_lengan = $hasilPerBagian['section_A']['sandaran_lengan'] ?? 0;
            $sandaran_punggung = $hasilPerBagian['section_A']['sandaran_punggung'] ?? 0;
            // $durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung = $hasilPerBagian['section_A']['durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung'] ?? 0;

            $armRestAndBackSupport = $sandaran_lengan + $sandaran_punggung;
            $seatPanHeightOrdDepth = $tinggi_kursi + $lebar_dudukan;
            $totalTinggiDanLebarKursi = $seatPanHeightOrdDepth + $durasi_kerja_tinggi_kursi_dan_lebar_dudukan;
            $totalSandaranLenganDanPunggung = $armRestAndBackSupport;
            // $totalSandaranLenganDanPunggung = $armRestAndBackSupport + $durasi_kerja_bagian_sandaran_lengan_dan_sandaran_punggung;

            $nilai_section_A = $sectionA[$seatPanHeightOrdDepth][$armRestAndBackSupport];
            $totalSkorA = $nilai_section_A + $durasi_kerja_tinggi_kursi_dan_lebar_dudukan;

            // SECTION B
            $monitor = $hasilPerBagian['section_B']['monitor'] ?? 0;
            $durasi_kerja_monitor = $hasilPerBagian['section_B']['durasi_kerja_monitor'] ?? 0;
            $telepon = $hasilPerBagian['section_B']['telepon'] ?? 0;
            $durasi_kerja_telepon = $hasilPerBagian['section_B']['durasi_kerja_telepon'] ?? 0;

            $totalMonitor = $monitor + $durasi_kerja_monitor;
            $totalTelepon = $telepon + $durasi_kerja_telepon;

            $totalSkorB = $sectionB[$totalTelepon][$totalMonitor];

            // SECTION C
            $keyboard = $hasilPerBagian['section_C']['keyboard'] ?? 0;
            $mouse = $hasilPerBagian['section_C']['mouse'] ?? 0;
            $durasi_kerja_keyboard = $hasilPerBagian['section_C']['durasi_kerja_keyboard'] ?? 0;
            $durasi_kerja_mouse = $hasilPerBagian['section_C']['durasi_kerja_mouse'] ?? 0;

            $totalKeyboard = $keyboard + $durasi_kerja_keyboard;
            $totalMouse = $mouse + $durasi_kerja_mouse;

            $totalSkorC = $sectionC[$totalMouse][$totalKeyboard];

            // SECTION D
            $totalSkorD = $sectionD[$totalSkorB][$totalSkorC];

            // FINAL ROSA
            $finalRosa = $skorRosa[$totalSkorA][$totalSkorD];

            // SAVE DATA
            $pengukuran = [
                "section_A" => $request->section_A,
                "section_B" => $request->section_B,
                "section_C" => $request->section_C,
                'skor_mouse' => $mouse,
                'skor_monitor' => $monitor,
                'skor_telepon' => $telepon,
                'nilai_table_a' => $nilai_section_A,
                'skor_keyboard' => $keyboard,
                'final_skor_rosa' => $finalRosa,
                'total_section_a' => $totalSkorA,
                'total_section_b' => $totalSkorB,
                'total_section_c' => $totalSkorC,
                'total_section_d' => $totalSkorD,
                'skor_lebar_kursi' => $lebar_dudukan,
                'total_skor_mouse' => $totalMouse,
                'skor_tinggi_kursi' => $tinggi_kursi,
                'total_skor_monitor' => $totalMonitor,
                'total_skor_telepon' => $totalTelepon,
                'total_skor_keyboard' => $totalKeyboard,
                'skor_sandaran_lengan' => $sandaran_lengan,
                'skor_sandaran_punggung' => $sandaran_punggung,
                'skor_durasi_kerja_mouse' => $durasi_kerja_mouse,
                'skor_durasi_kerja_monitor' => $durasi_kerja_monitor,
                'skor_durasi_kerja_telepon' => $durasi_kerja_telepon,
                'skor_durasi_kerja_keyboard' => $durasi_kerja_keyboard,
                'skor_durasi_kerja_bagian_kursi' => $durasi_kerja_tinggi_kursi_dan_lebar_dudukan,
                'skor_total_tinggi_kursi_dan_lebar_dudukan' => $seatPanHeightOrdDepth,
                'skor_total_sandaran_lengan_dan_punggung' => $armRestAndBackSupport,
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
            $data->method = 4;
            $data->pengukuran = json_encode($pengukuran, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->first();

            if($orderDetail->tanggal_terima == null) {
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }

            // INSERT ACTIVITY
            InsertActivityFdl::by($this->user_id)->action('input')->target("Method Rosa pada nomor sampel $request->no_sample")->save();

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

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganErgonomi::with('detail')
            ->where('created_by', $this->karyawan)->where('method', 4)
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
            InsertActivityFdl::by($this->user_id)->action('delete')->target("Method Rosa dengan nomor sampel $no_sample")->save();
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
