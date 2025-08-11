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
use App\Services\InsertActivityFdl;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class FdlMethodNbmController extends Controller
{
    public function getSample(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

        // Check if method is valid and execute accordingly
        $method = 1;
        if ($method >= 1 && $method <= 10) {
            return $this->processMethod($request, $fdl, $method);
        }

        return response()->json(['message' => 'Method not found.'], 400);
    }

    private function extractNestedInputs($inputs, $prefix) {
        $result = [];

        foreach ($inputs as $key => $value) {
            if (strpos($key, $prefix . '[') === 0 && substr($key, -1) === ']') {
                $fieldName = substr($key, strlen($prefix) + 1, -1);
                $result[$fieldName] = $value;
            }
        }

        return $result;
    }

    private function formatArrayKeysUnderscore($array) {
        $result = [];
        foreach ($array as $key => $value) {
            $newKey = strtolower(str_replace(' ', '_', $key));
            $result[$newKey] = $value;
        }
        return $result;
    }

    private function prosesSkor(array $data, array $bagian_kiri, array $bagian_kanan): array {
        $skor_kiri = 0;
        $skor_kanan = 0;
        $result = [];

        foreach ($data as $bagian => $nilai) {
            if (!is_string($nilai) || strpos($nilai, '-') === false) {
                continue;
            }

            [$skor, $keterangan] = explode('-', $nilai, 2);
            $skor = (int) trim($skor);
            $bagian_key = strtolower(str_replace(' ', '_', $bagian));
            $result["skor_{$bagian_key}"] = $skor;

            if (in_array($bagian_key, $bagian_kiri)) {
                $skor_kiri += $skor;
            } elseif (in_array($bagian_key, $bagian_kanan)) {
                $skor_kanan += $skor;
            }
        }

        $total_skor = $skor_kiri + $skor_kanan;

        if ($total_skor <= 20) {
            $tingkat_risiko = 0;
            $kategori_risiko = 'Rendah';
            $tindakan = 'Belum diperlukan adanya tindakan perbaikan';
        } elseif ($total_skor <= 41) {
            $tingkat_risiko = 1;
            $kategori_risiko = 'Sedang';
            $tindakan = 'Mungkin diperlukan tindakan dikemudian hari';
        } elseif ($total_skor <= 62) {
            $tingkat_risiko = 2;
            $kategori_risiko = 'Tinggi';
            $tindakan = 'Diperlukan tindakan segera';
        } elseif ($total_skor <= 84) {
            $tingkat_risiko = 3;
            $kategori_risiko = 'Sangat Tinggi';
            $tindakan = 'Diperlukan tindakan menyeluruh sesegera mungkin';
        } else {
            $tingkat_risiko = null;
            $kategori_risiko = 'Tidak Diketahui';
            $tindakan = '-';
        }

        return array_merge($result, [
            'skor_kiri' => $skor_kiri,
            'skor_kanan' => $skor_kanan,
            'total_skor' => $total_skor,
            'tingkat_risiko' => $tingkat_risiko,
            'kategori_risiko' => $kategori_risiko,
            'tindakan_perbaikan' => $tindakan,
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try{
            $inputs = $request->all();

            // Daftar bagian tubuh
            $bagian_kiri = [
                "bahu_kiri", "leher_atas", "pinggul", "lengan_atas_kiri", "siku_kiri", "lengan_bawah_kiri", "pergelangan_tangan_kiri",
                "tangan_kiri", "paha_kiri", "lutut_kiri", "betis_kiri", "pergelangan_kaki_kiri", "kaki_kiri"
            ];

            $bagian_kanan = [
                "bahu_kanan", "tengkuk", "punggung", "pinggang", "pantat", "lengan_atas_kanan", "siku_kanan", "lengan_bawah_kanan", "pergelangan_tangan_kanan",
                "tangan_kanan", "paha_kanan", "lutut_kanan", "betis_kanan", "pergelangan_kaki_kanan", "kaki_kanan"
            ];

            // Ambil data dari request
            $sebelum = $request->get('sebelum_kerja') ?? $this->extractNestedInputs($inputs, 'sebelum_kerja');
            $setelah = $request->get('setelah_kerja') ?? $this->extractNestedInputs($inputs, 'setelah_kerja');

            // dd($sebelum, $setelah);

            // Format key-nya ke snake_case
            $sebelum = $this->formatArrayKeysUnderscore($sebelum);
            $setelah = $this->formatArrayKeysUnderscore($setelah);


            // Proses sebelum dan setelah kerja
            $hasil_sebelum = $this->prosesSkor($sebelum, $bagian_kiri, $bagian_kanan);
            $hasil_setelah = $this->prosesSkor($setelah, $bagian_kiri, $bagian_kanan);

            // Simpan hasil pengukuran
            $pengukuran = [
                'sebelum' => $hasil_sebelum,
                'setelah' => $hasil_setelah
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
            if ($request->foto_samping_kiri != '')
                $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
            if ($request->foto_samping_kanan != '')
                $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
            if ($request->foto_depan != '')
                $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
            if ($request->foto_belakang != '')
                $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
            if ($request->permission != '')
                $data->permission = $request->permission;
            $data->method = 1;
            $data->sebelum_kerja = json_encode($sebelum);
            $data->setelah_kerja = json_encode($setelah);
            $data->pengukuran = json_encode($pengukuran);
            $data->created_by = $this->karyawan;
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            // UPDATE ORDER DETAIL
            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
                ->where('parameter', 'LIKE', '%Ergonomi%')
                ->update([
                    'tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s'),
                ]);

            InsertActivityFdl::by($this->user_id)->action('input')->target("Method NBM pada nomor sampel $request->no_sample")->save();
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
            ->where('created_by', $this->karyawan)->where('method', 1)
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
            DB::beginTransaction();
            try {
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
                
                InsertActivityFdl::by($this->user_id)->action('delete')->target("Mehtod NBM dengan nomor sampel $cek->no_sampel")->save();

                $cek->delete();
                DB::commit();
                return response()->json([
                    'message' => 'Data has ben Deleted',
                    'cat' => 1
                ], 200);
            } catch (\Exception $th) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal Delete'
                ], 500);
            }
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
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
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
                return response()->json(['message' => 'Tidak ada data berdasarkan No. Sample tersebut.'], 401);
            }
        } catch (Exception $e) {
            dd($e);
        }
    }

    // JUMLAH SKOR POSTUR TUBUH
    private function calculateTotalDurasi($data){
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