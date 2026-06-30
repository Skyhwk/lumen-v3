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

class FdlSensoricPmBaruController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'pm_baru')->where('is_active', 1)->first();
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
                $arrayParam = json_decode($data->parameter, true);

                // Ambil nama parameter saja dari format "id;name"
                $parameters = array_map(function($item) {
                    return explode(';', $item)[1];
                }, $arrayParam);

                $finalParameter = [];
                foreach ($parameters as $p) {
                    foreach ($listParameter as $keyword) {
                        if (stripos($p, $keyword) !== false) {
                            $finalParameter[] = $p;
                            break;
                        }
                    }
                }

                if ($partikulat !== NULL) {
                    \DB::statement("SET SQL_MODE=''");

                    $no_sampel = strtoupper(trim($request->no_sample));

                    // Ambil parameter dengan shift selain 'Sesaat', untuk memastikan hanya pengambilan waktu tertentu
                    $paramSesaat = DataLapanganPartikulatMeter::where('no_sampel', $no_sampel)
                                ->where('shift_pengambilan', 'Sesaat')
                                ->groupBy('parameter')->pluck('parameter')->toArray();

                    // Cari parameter yang seharusnya diuji tapi belum ada di tabel (belum dicatat)
                    $nilai_param2 = array_values(array_diff($finalParameter, $paramSesaat));

                    $param_fin = $nilai_param2;
                    if(empty($param_fin)){
                        return response()->json([
                            'message' => 'Data Parameter Sesaat sudah terinput semua .!'
                        ], 400);
                    }

                    // Ambil informasi sub-kategori dari master berdasarkan ID kategori_3 (dipisah dengan '-')
                    $id_ket = explode('-', $data->kategori_3)[0];
                    $cek = MasterSubKategori::find($id_ket);

                    return response()->json([
                        'no_sample'  => $data->no_sampel,
                        'jenis'      => $cek->nama_sub_kategori ?? null,
                        'keterangan' => $data->keterangan_1,
                        'id_ket'     => $id_ket,
                        'parameter'  => $param_fin,
                        'iso_classes' => $this->getIsoClasses(),
                        'sampling_locations' => $this->getSamplingLocations(),
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
                        'parameter'  => $finalParameter,
                        'iso_classes' => $this->getIsoClasses(),
                        'sampling_locations' => $this->getSamplingLocations(),
                    ], 200);
                }

            }
        } else {
            return response()->json([
                'message' => 'Fatal Error'
            ], 401);
        }
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

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $requiredFields = [
                'no_sample' => 'No sample tidak boleh kosong!',
                'pilih_parameter' => 'Parameter tidak boleh kosong!',
                'flow' => 'Flow tidak boleh kosong!',
                'kelas_iso' => 'Kelas ISO tidak boleh kosong!',
                'panjang' => 'Panjang tidak boleh kosong!',
                'lebar' => 'Lebar tidak boleh kosong!',
                'luas_area' => 'Luas area tidak boleh kosong!',
                'foto_lokasi_sampel' => 'Foto lokasi sampel tidak boleh kosong!',
            ];
            foreach ($requiredFields as $field => $message) {
                if (empty($request->$field)) {
                    return response()->json(['message' => $message], 401);
                }
            }

            foreach ($request->pilih_parameter as $parameter) {
                $pengukuran = $request->hasil_pengukuran[$parameter] ?? [];

                $data = new DataLapanganPartikulatMeter();
                $data->no_sampel = strtoupper(trim($request->no_sample));
                $data->kategori_3 = $request->id_kategori_3 ?: null;
                $data->keterangan = $request->penamaan_titik ?: null; // penamaan titik
                $data->parameter = $parameter;
                $data->shift_pengambilan = 'Sesaat'; // default if none provided
                $data->pengukuran = json_encode($pengukuran);
                $data->flow = $request->flow ?? null;
                $data->kelas_iso = $request->kelas_iso ?? null;
                $data->nilai_iso = $request->nilai_iso[$parameter] ?? null;
                $data->panjang = $request->panjang ?? null;
                $data->lebar = $request->lebar ?? null;
                $data->luas_area = $request->luas_area ?? null;
                $data->foto_lokasi_sampel = $request->foto_lokasi_sampel ? self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id) : null;
                $data->foto_lain = $request->foto_lain ? self::convertImg($request->foto_lain, 3, $this->user_id) : null;
                $data->created_by = $this->karyawan;
                $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->catatan_sampler = $request->catatan_kondisi_lapangan ?: null; // catatan kondisi lapangan
                $data->save();
            }

            // Update Order Detail
            $orderDetail = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))->where('is_active', 1)->first();

            if ($orderDetail && $orderDetail->tanggal_terima == null) {
                $orderDetail->tanggal_terima = Carbon::now()->format('Y-m-d');
                $orderDetail->save();
            }
            
            if (isset($this->user_id)) {
                InsertActivityFdl::by($this->user_id)->action('input')->target("FDL Sensoric PM >5 & >0.5 pada nomor sampel $request->no_sample")->save();
            }

            DB::commit();

            return response()->json(['message' => "Data Sampling FDL Sensoric PM >5 & >0.5 Dengan No Sample $request->no_sample berhasil disimpan oleh $this->karyawan"], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'line' => $e->getLine()
            ]);
        }
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
                    'message' => 'Data FDL Sensoric PM >5 & >0.5 berhasil dihapus',
                ], 201);
            }

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

    private function getIsoClasses() {
        return [
            '1' => ['0.1' => 10],
            '2' => ['0.1' => 100, '0.2' => 24, '0.3' => 10],
            '3' => ['0.1' => 1000, '0.2' => 237, '0.3' => 102, '0.5' => 35],
            '4' => ['0.1' => 10000, '0.2' => 2370, '0.3' => 1020, '0.5' => 352, '1' => 83],
            '5' => ['0.1' => 100000, '0.2' => 23700, '0.3' => 10200, '0.5' => 3520, '1' => 832],
            '6' => ['0.1' => 1000000, '0.2' => 237000, '0.3' => 102000, '0.5' => 35200, '1' => 8320, '5' => 293],
            '7' => ['0.5' => 352000, '1' => 83200, '5' => 2930],
            '8' => ['0.5' => 3520000, '1' => 832000, '5' => 29300],
            '9g' => ['0.5' => 35200000, '1' => 8320000, '5' => 293000],
        ];
    }

    private function getSamplingLocations() {
        return [
            ['area' => 2, 'nl' => 1],
            ['area' => 4, 'nl' => 2],
            ['area' => 6, 'nl' => 3],
            ['area' => 8, 'nl' => 4],
            ['area' => 10, 'nl' => 5],
            ['area' => 24, 'nl' => 6],
            ['area' => 28, 'nl' => 7],
            ['area' => 32, 'nl' => 8],
            ['area' => 36, 'nl' => 9],
            ['area' => 52, 'nl' => 10],
            ['area' => 56, 'nl' => 11],
            ['area' => 64, 'nl' => 12],
            ['area' => 68, 'nl' => 13],
            ['area' => 72, 'nl' => 14],
            ['area' => 76, 'nl' => 15],
            ['area' => 104, 'nl' => 16],
            ['area' => 108, 'nl' => 17],
            ['area' => 116, 'nl' => 18],
            ['area' => 148, 'nl' => 19],
            ['area' => 156, 'nl' => 20],
            ['area' => 192, 'nl' => 21],
            ['area' => 232, 'nl' => 22],
            ['area' => 276, 'nl' => 23],
            ['area' => 352, 'nl' => 24],
            ['area' => 436, 'nl' => 25],
            ['area' => 636, 'nl' => 26],
            ['area' => 1000, 'nl' => 27]
        ];
    }
}