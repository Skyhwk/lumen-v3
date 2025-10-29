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

class FdlMethodBahayaErgonomiController extends Controller
{
    public function getSample(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->first();

            return $this->processMethod($request, $fdl, 8);

        return response()->json(['message' => 'Method not found.'], 400);
    }

    public function checkHasVideo(Request $request)
    {
        $fdl = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))->where('method', 8)->first();
        
        if(!$fdl) return response()->json(['message' => 'Tidak ada data berdasarkan No. Sample tersebut.'], 401);
        
        if($fdl && $fdl->video_dokumentasi != null){ 
            return response()->json(['message' => 'Video sudah di upload.', 'isset_video' => true], 200);
        } else {
            return response()->json(['message' => 'Video belum di upload.', 'isset_video' => false], 200);
        }
    }

    // public function storeVideo(Request $request)
    // {
    //     // Simpan file
    //     if ($request->hasFile('video')) {
    //         $video = $request->file('video');
    //         $safeName = 'video_' . time() . '_' . $request->id . '.' . $video->getClientOriginalExtension();
            
    //         $destinationPath = public_path() . '/dokumentasi/sampling/';
    //         $video->move($destinationPath, $safeName);
    //         // Simpan referensi ke database
    //         DataLapanganErgonomi::find($request->id)->update([
    //             'video_dokumentasi' => $safeName
    //         ]);

    //         return response()->json([
    //             'success' => true,
    //         ]);
    //     }

    //     return response()->json([
    //         'success' => false,
    //     ]);
    // }

    public function storeVideo(Request $request)
    {
        // Simpan file
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $noSampelSafe = preg_replace('/[^A-Za-z0-9_\-]/', '_', $request->no_sampel);
            $safeName = 'video_' . time() . '_' . $noSampelSafe . '.' . $video->getClientOriginalExtension();

            $destinationPath = public_path() . '/dokumentasi/sampling/';
            $video->move($destinationPath, $safeName);
            // Simpan referensi ke database
            DataLapanganErgonomi::where('no_sampel', $request->no_sampel)->where('method', 8)->update([
                'video_dokumentasi' => $safeName
            ]);

            return response()->json([
                'success' => true,
            ]);
        }

        return response()->json([
            'success' => false,
        ]);
    }

    public function store(Request $request)
    {
        // dd($request->all());
        DB::beginTransaction();
        try{
            function hitungRisiko($posisi, $berat)
            {
                $poin = 0;
                if ($posisi == 'Pengangkatan dengan jarak dekat') {
                    if ($berat == 'Berat benda >23Kg') {
                        $poin = 5;
                    } elseif ($berat == 'Berat benda Sekitar 7 - 23 Kg') {
                        $poin = 3;
                    } else {
                        $poin = 0;
                    }
                } elseif ($posisi == 'Pengangkatan dengan jarak sedang') {
                    if ($berat == 'Berat benda >16Kg') {
                        $poin = 6;
                    } elseif ($berat == 'Berat benda Sekitar 5 - 16 Kg') {
                        $poin = 3;
                    } else {
                        $poin = 0;
                    }
                } elseif ($posisi == 'Pengangkatan dengan jarak jauh') {
                    if ($berat == 'Berat benda >13Kg') {
                        $poin = 6;
                    } elseif ($berat == 'Berat benda Sekitar 4.5 - 13 Kg') {
                        $poin = 3;
                    } else {
                        $poin = 0;
                    }
                }

                return $poin;
            }

            // Ambil data Manual_Handling dari request
            $manualHandling = $request->input('Manual_Handling');

            // Hitung total skor
            $total_skor_1 = 0;
            if ($manualHandling !== 'Tidak') {
                if (is_array($manualHandling) && isset($manualHandling['Posisi Angkat Beban']) && isset($manualHandling['Estimasi Berat Benda'])) {
                    $total_skor_1 = hitungRisiko($manualHandling['Posisi Angkat Beban'], $manualHandling['Estimasi Berat Benda']);
                }
            }

            $total_skor_2 = 0;
            if ($manualHandling !== 'Tidak' && isset($manualHandling['Faktor Resiko']) && is_array($manualHandling['Faktor Resiko'])) {
                foreach ($manualHandling['Faktor Resiko'] as $faktor => $nilai) {
                    // Periksa jika $nilai adalah array
                    if (is_array($nilai)) {
                        foreach ($nilai as $sub_nilai) {
                            // Pastikan nilai tidak 'Tidak' dan dalam format yang benar
                            if (is_string($sub_nilai) && $sub_nilai !== 'Tidak') {
                                // Ambil nilai sebelum tanda '-'
                                $skor = explode('-', $sub_nilai)[0];
                                if (is_numeric($skor)) {
                                    $total_skor_2 += intval($skor);
                                }
                            }
                        }
                    } elseif (is_string($nilai) && $nilai !== 'Tidak') {
                        // Tangani kasus jika $nilai adalah string sederhana
                        $skor = explode('-', $nilai)[0];
                        if (is_numeric($skor)) {
                            $total_skor_2 += intval($skor);
                        }
                    }
                }
            }

            $total_skor = $total_skor_1 + $total_skor_2;

            // Menghitung total durasi untuk Tubuh_Bagian_Atas dan Tubuh_Bagian_Bawah
            $hitung = [
                "Tubuh_Bagian_Atas" => $request->input('Tubuh_Bagian_Atas'),
                "Tubuh_Bagian_Bawah" => $request->input('Tubuh_Bagian_Bawah'),
            ];

            $totalDurasiAtas = $this->calculateTotalDurasi($hitung['Tubuh_Bagian_Atas']);
            $totalDurasiBawah = $this->calculateTotalDurasi($hitung['Tubuh_Bagian_Bawah']);
            $totalSkor = $totalDurasiAtas + $totalDurasiBawah;
            // Tambahkan total skor ke array Manual_Handling jika bukan 'Tidak'
            if ($manualHandling !== 'Tidak') {
                $manualHandling['Total Poin 1'] = $total_skor_1;
                $manualHandling['Faktor Resiko']['Total Poin 2'] = $total_skor_2;
                $manualHandling['Total Poin Akhir'] = $total_skor;
            }

            // Buat array pengukuran dengan data yang telah dimodifikasi
            $pengukuran = [
                "Tubuh_Bagian_Atas" => $request->input('Tubuh_Bagian_Atas'),
                "Tubuh_Bagian_Bawah" => $request->input('Tubuh_Bagian_Bawah') ? $request->input('Tubuh_Bagian_Bawah') : 'Tidak Ada',
                "Jumlah_Skor_Postur" => $totalSkor,
                "Manual_Handling" => $manualHandling
            ];
            // Simpan data ke database
            $data = new DataLapanganErgonomi();
            if ($request->no_order != '') {
                $data->no_order = $request->no_order;
            }
            
            if (strtoupper(trim($request->no_sample)) != '') {
                $data->no_sampel = strtoupper(trim($request->no_sample));
            }
            if ($request->pekerja != '') {
                $data->nama_pekerja = $request->pekerja;
            }
            if ($request->divisi != '') {
                $data->divisi = $request->divisi;
            }
            if ($request->usia != '') {
                $data->usia = $request->usia;
            }
            $data->lama_kerja = json_encode($request->year . " Tahun" . ", " . $request->month . " Bulan");
            if ($request->kelamin != '') {
                $data->jenis_kelamin = $request->kelamin;
            }
            if ($request->waktu_bekerja != '') {
                $data->waktu_bekerja = $request->waktu_bekerja;
            }
            if ($request->aktivitas != '') {
                $data->aktivitas = $request->aktivitas;
            }
            $data->method = 8;
            $data->pengukuran = json_encode($pengukuran);
            $data->aktivitas_ukur = $request->aktivitas_ukur;
            $data->permission = $request->permission;
            $data->created_by = $this->karyawan;
            if ($request->foto_samping_kiri != '')
                $data->foto_samping_kiri = self::convertImg($request->foto_samping_kiri, 1, $this->user_id);
            if ($request->foto_samping_kanan != '')
                $data->foto_samping_kanan = self::convertImg($request->foto_samping_kanan, 2, $this->user_id);
            if ($request->foto_depan != '')
                $data->foto_depan = self::convertImg($request->foto_depan, 3, $this->user_id);
            if ($request->foto_belakang != '')
                $data->foto_belakang = self::convertImg($request->foto_belakang, 4, $this->user_id);
            $data->created_at = Carbon::now()->format('Y-m-d H:i:s');

            // Simpan data ke database
            $data->save();

            // UPDATE ORDER DETAIL
            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->where('kategori_3', 'LIKE', '%27-%')
                ->orWhere('kategori_3', 'LIKE', '%53-%')
                ->where('parameter', 'LIKE', '%Ergonomi%')->first();
                InsertActivityFdl::by($this->user_id)->action('input')->target("Bahaya Ergonomi pada nomor sampel $data->no_sample")->save();

            if($orderDetail->tanggal_terima == null) {
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d H:i:s');
                $orderDetail->save();
            }

            DB::commit();
            return response()->json([
                'message' => 'Data berhasil disimpan.',
                'id' => $data->id
            ], 200);
        }catch (\Exception $e) {
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
            ->where('created_by', $this->karyawan)->where('method', 8)
            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));

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
            InsertActivityFdl::by($this->user_id)->action('delete')->target("Bahaya Ergonomi pada nomor sampel $cek->no_sampel")->save();
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

    // private function processMethod($request, $fdl, $method)
    // {
    //     try {
    //         // Check for the existence of the sample with the appropriate category and parameter
    //         $check = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
    //         ->where(function ($query) {
    //             $query->where('kategori_3', 'LIKE', '%27-%')
    //                 ->orWhere('kategori_3', 'LIKE', '%53-%');
    //         })
    //         ->where('parameter', 'LIKE', '%Ergonomi%')
    //         ->where('is_active', true)
    //         ->first();

    //         // Check if the data for the given method already exists
    //         $data = DataLapanganErgonomi::where('no_sampel', strtoupper(trim($request->no_sample)))
    //             ->where('method', $method)
    //             ->first();

    //         // Respond based on whether the data already exists
    //         if ($check) {
    //             if ($data) {
    //                 return response()->json(['message' => 'No. Sample sudah di input.'], 401);
    //             } else {
    //                 return response()->json([
    //                     'message' => 'Successful.',
    //                     'data' => $fdl
    //                 ], 200);
    //             }
    //         } else {
    //             return response()->json(['message' => 'Tidak ada data Ergonomi berdasarkan No. Sample tersebut.'], 401);
    //         }
    //     } catch (Exception $e) {
    //         dd($e);
    //     }
    // }

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
                if (isset($request->upload_video)) {
                    if ($data) {
                        return response()->json([
                            'message' => 'Successful.',
                            'data' => $fdl
                        ], 200);
                    } else {
                        return response()->json(['message' => 'No. Sample sudah di input.'], 401);
                    }
                } else {
                    if ($data) {
                        return response()->json(['message' => 'No. Sample sudah di input.'], 401);
                    } else {
                        return response()->json([
                            'message' => 'Successful.',
                            'data' => $fdl
                        ], 200);
                    }
                }
            } else {
                return response()->json(['message' => 'Tidak ada parameter Ergonomi di No. Sampel tersebut.'], 401);
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

    public function convertBase64File($base64Data = '', $type = '', $user = '', $extension = 'jpeg')
    {
        // Deteksi dan potong header base64
        if (strpos($base64Data, 'base64,') !== false) {
            $base64Data = explode('base64,', $base64Data)[1];
        }

        // Decode base64
        $fileData = base64_decode($base64Data);

        // Nama file unik
        $safeName = date('YmdHis') . '_' . $user . $type . '.' . $extension;

        // Tentukan path (atur berdasarkan jenis file juga boleh)
        $destinationPath = public_path() . '/dokumentasi/sampling/';
        
        // Simpan file
        $success = file_put_contents($destinationPath . $safeName, $fileData);

        // Return nama file kalau sukses, atau false
        return $success ? $safeName : false;
    }
}