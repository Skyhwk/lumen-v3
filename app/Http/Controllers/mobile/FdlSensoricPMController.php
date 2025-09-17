<?php

namespace App\Http\Controllers\mobile;

use App\Models\DataLapanganPartikulatMeter;

// MASTER DATA
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\ParameterFdl;

// SERVICE
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

class FdlSensoricPMController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'sensoric_pm')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);
            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
            ->whereIn('kategori_3', ['11-Udara Ambient', '27-Udara Lingkungan Kerja'])
            ->where(function ($q) use ($listParameter) {
                foreach ($listParameter as $keyword) {
                    $q->orWhere('parameter', 'like', "%$keyword%");
                }
            })
            ->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan pada kategori Partikulat Meter'
                ], 401);
            } else {
                
                $partikulat = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))->first();
                // Cek apakah data partikulat tersedia
                if ($partikulat !== NULL) {

                    // Nonaktifkan mode strict SQL agar query groupBy tidak error karena mode ONLY_FULL_GROUP_BY
                    \DB::statement("SET SQL_MODE=''");

                    // Ambil nomor sampel dari request, dan ubah ke huruf kapital
                    $no_sampel = strtoupper(trim($request->no_sample));

                    // Ambil semua parameter yang sudah dicatat di tabel DataLapanganPartikulatMeter
                    // Dikelompokkan berdasarkan parameter (tidak duplikat)
                    $par = DataLapanganPartikulatMeter::where('no_sampel', $no_sampel)
                                ->groupBy('parameter')->pluck('parameter')->toArray();

                    // Ambil parameter dengan shift selain 'Sesaat', untuk memastikan hanya pengambilan waktu tertentu
                    $par2 = DataLapanganPartikulatMeter::where('no_sampel', $no_sampel)
                                ->where('shift_pengambilan', '!=', 'Sesaat')
                                ->groupBy('parameter')->pluck('parameter')->toArray();

                    // Decode data parameter dari tabel utama (yang seharusnya diuji)
                    $p = json_decode($data->parameter, true);

                    // Cari parameter yang seharusnya diuji tapi belum ada di tabel (belum dicatat)
                    $nilai_param2 = array_values(array_diff($p, $par));

                    // Parameter yang sudah tercatat tapi hanya untuk shift non-Sesaat
                    $nilai_param3 = $par2;

                    // Gabungkan hasilnya sesuai kondisi:
                    // Jika tidak ada parameter baru, pakai yang shift non-Sesaat
                    if (empty($nilai_param2)) {
                        $param_fin = json_encode($nilai_param3);
                    }
                    // Jika tidak ada parameter shift non-Sesaat, pakai parameter baru saja
                    elseif (empty($nilai_param3)) {
                        $param_fin = json_encode($nilai_param2);
                    }
                    // Jika dua-duanya ada, gabungkan semua
                    else {
                        $param_fin = json_encode(array_merge($nilai_param3, $nilai_param2));
                    }

                    // Ambil informasi sub-kategori dari master berdasarkan ID kategori_3 (dipisah dengan '-')
                    $id_ket = explode('-', $data->kategori_3)[0];
                    $cek = MasterSubKategori::find($id_ket);
                    // Kirim response JSON yang berisi data utama untuk ditampilkan atau dipakai di frontend
                    return response()->json([
                        'no_sample'  => $data->no_sampel,
                        'jenis'      => $cek->nama_sub_kategori ?? null,
                        'keterangan' => $data->keterangan_1,
                        'id_ket'     => $id_ket,
                        'parameter'  => $param_fin,
                    ], 200);

                } else {

                    // Kalau tidak ada data partikulat, langsung kirim data yang sudah ada di field `parameter` tanpa modifikasi
                    $id_ket = explode('-', $data->kategori_3)[0];
                    $id_ket2 = explode('-', $data->kategori_2)[0];
                    $cek = MasterSubKategori::find($id_ket);
                    return response()->json([
                        'no_sample'  => $data->no_sampel,
                        'jenis'      => $cek->nama_sub_kategori ?? null,
                        'keterangan' => $data->keterangan_1,
                        'id_ket'     => $id_ket,
                        'id_ket2'    => $id_ket2,
                        'param'      => $data->parameter,
                    ], 200);
                }

            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            
            // Validasi Input
            $requiredFields = [
                'jam_pengambilan' => 'Jam pengambilan tidak boleh kosong .!',
                'foto_lokasi_sampel' => 'Foto lokasi sampling tidak boleh kosong .!',
                'foto_lain' => 'Foto lain-lain tidak boleh kosong .!'
            ];
            foreach ($requiredFields as $field => $message) {
                if (empty($request->$field)) {
                    return response()->json(['message' => $message], 401);
                }
            }

            // Pengecekan shift dan parameter
            if ($request->parameter != null) {
                $nilai_array = []; // Array untuk menyimpan shift yang sudah ada
                foreach ($request->parameter as $en => $ab) {
                    if ($request->shift_pengambilan[$en] !== "Sesaat") {
                        $cek = DataLapanganPartikulatMeter::where('no_sampel', strtoupper(trim($request->no_sample)))
                        ->where('parameter', $ab)
                        ->get();

                        foreach ($cek as $key => $value) {
                            // Jika shift yang diminta sudah ada, skip penyimpanan
                            if ($value->shift_pengambilan == 'Sesaat' && $request->shift_pengambilan[$en] == $value->shift_pengambilan) {
                                return response()->json(['message' => 'Shift sesaat sudah terinput di no sample ini .!'], 401);
                            }

                            // Proses durasi untuk pengecekan
                            $durasi = explode("-", $value->shift_pengambilan)[1];
                            $nilai_array[$key] = str_replace('"', "", $durasi);

                            // Jika parameter yang sama dan shift sudah ada, tidak perlu disimpan lagi
                            if (in_array($request->shift_pengambilan[$en], $nilai_array)) {
                                continue 2;  // Skip jika shift sudah ada
                            }
                        }
                    }
                }
            }

            // Proses Input Data
            if ($request->parameter != null) {
                foreach ($request->parameter as $in => $a) {
                    // Skip jika parameter sudah ada dengan shift yang diminta
                    if (in_array($request->shift_pengambilan[$in], $nilai_array)) {
                        continue;  // Skip parameter ini jika sudah ada
                    }

                    // Pengukuran data
                    $pengukuran = [
                        'data-1' => $request->data1[$in],
                        'data-2' => $request->data2[$in],
                        'data-3' => $request->data3[$in],
                        'data-4' => $request->data4[$in],
                        'data-5' => $request->data5[$in],
                    ];

                    // Tentukan shift pengambilan berdasarkan kategori uji
                    $shift_pengambilan = $this->getShiftPengambilan($request->kategori_uji[$in], $request->shift_pengambilan[$in]);

                    // Buat data untuk disimpan
                    $data = new DataLapanganPartikulatMeter();
                    $data->no_sampel = strtoupper(trim($request->no_sample));
                    $data->keterangan = $request->penamaan_titik ?: null;
                    $data->keterangan_2 = $request->penamaan_tambahan ?: null;
                    $data->titik_koordinat = $request->koordinat ?: null;
                    $data->latitude = $request->latitude ?: null;
                    $data->longitude = $request->longitude ?: null;
                    $data->kategori_3 = $request->id_kategori_3 ?: null;
                    $data->parameter = $a;
                    $data->lokasi = $request->lokasi ?: null;
                    $data->waktu_pengukuran = $request->jam_pengambilan;
                    $data->suhu = $request->suhu ?: null;
                    $data->kelembapan = $request->kelembaban ?: null;
                    $data->tekanan_udara = $request->tekanan_udara ?: null;
                    $data->shift_pengambilan = $shift_pengambilan;
                    $data->pengukuran = json_encode($pengukuran);
                    $data->permission = $request->permission ?: null;
                    $data->foto_lokasi_sampel = $request->foto_lokasi_sampel ? self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id) : null;
                    $data->foto_lain = $request->foto_lain ? self::convertImg($request->foto_lain, 3, $this->user_id) : null;
                    $data->created_by = $this->karyawan;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->save();
                }
            }

            // Update Order Detail
            DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sample)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            InsertActivityFdl::by($this->user_id)->action('input')->target("Sensoric PM pada nomor sampel $request->no_sample")->save();


            DB::commit();

            return response()->json(['message' => "Data Sampling PARTICULATE MATTER Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }
    }

    private function getShiftPengambilan($kateg_uji, $shift) {
        if (in_array($kateg_uji, ['24 Jam', '8 Jam', '6 Jam'])) {
            return "$kateg_uji-" . json_encode($shift);
        }
        return 'Sesaat';
    }

    public function index(Request $request)
    {
        $perPage = $request->input('limit', 10);
        $page = $request->input('page', 1);
        $search = $request->input('search');

        $query = DataLapanganPartikulatMeter::with('detail')
            ->where('created_by', $this->karyawan)
            ->whereIn('is_rejected', [0, 1])
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
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve    = true;
            $data->approved_by   = $this->karyawan;
            $data->approved_at   = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            return response()->json([
                'message' => 'Data has ben Approved',
                'cat' => 1
            ], 200);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function detail(Request $request)
    {
        $data = DataLapanganPartikulatMeter::with('detail')->where('id', $request->id)->first();

        return response()->json([
            'id'             => $data->id,
            'no_sample'      => $data->no_sampel,
            'no_order'       => $data->detail->no_order,
            'categori'       => explode('-', $data->detail->kategori_3)[1],
            'sampler'        => $data->created_by,
            'corp'           => $data->detail->nama_perusahaan,
            'keterangan'     => $data->keterangan,
            'keterangan_2'   => $data->keterangan_2,
            'lat'            => $data->latitude,
            'long'           => $data->longitude,
            'kateg_pm'       => $data->parameter,
            'lokasi'         => $data->lokasi,
            'waktu'          => $data->waktu_pengukuran,
            'shift'          => $data->shift_pengambilan,
            'suhu'           => $data->suhu,
            'kelembapan'     => $data->kelembapan,
            'tekanan_u'      => $data->tekanan_udara,
            'pengukuran'     => $data->pengukuran,
            'tikoor'         => $data->titik_koordinat,
            'foto_lok'       => $data->foto_lokasi_sampel,
            'foto_lain'      => $data->foto_lain,
            'coor'           => $data->titik_koordinat,
            'status'         => '200'
        ], 200);
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganPartikulatMeter::where('id', $request->id)->first();
            $cek2 = DataLapanganPartikulatMeter::where('no_sampel', $data->no_sampel)->get();
            if ($cek2->count() > 1) {
                $data->delete();
                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            } else {
                $foto_lok = public_path() . '/dokumentasi/sampling/' . $data->foto_lokasi_sampel;
                $foto_kon = public_path() . '/dokumentasi/sampling/' . $data->foto_kondisi_sampel;
                $foto_lain = public_path() . '/dokumentasi/sampling/' . $data->foto_lain;
                if (is_file($foto_lok)) {
                    unlink($foto_lok);
                }
                if (is_file($foto_kon)) {
                    unlink($foto_kon);
                }
                if (is_file($foto_lain)) {
                    unlink($foto_lain);
                }
                $data->delete();

                return response()->json([
                    'message' => 'Data has ben Delete',
                    'cat' => 4
                ], 201);
            }
            $no_sample = $data->no_sampel;

        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
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