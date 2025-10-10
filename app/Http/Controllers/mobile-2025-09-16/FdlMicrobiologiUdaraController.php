<?php

namespace App\Http\Controllers\mobile;

// DATA LAPANGAN
use App\Models\DataLapanganMicrobiologi;

// DETAIL LAPANGAN
use App\Models\DetailMicrobiologi;

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
use App\Services\SendTelegram;
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

class FdlMicrobiologiUdaraController extends Controller
{
    public function getSample(Request $request)
    {
        if (isset($request->no_sample) && $request->no_sample != null) {
            $parameter = ParameterFdl::select('parameters')->where('nama_fdl', 'microbiologi')->where('is_active', 1)->first();
            $listParameter = json_decode($parameter->parameters, true);

            $data = OrderDetail::where('no_sampel', strtoupper(trim($request->no_sample)))
                ->whereIn('parameter', $listParameter)
                ->where('is_active', 1)->first();
            if (is_null($data)) {
                return response()->json([
                    'message' => 'No Sample tidak ditemukan..'
                ], 401);
            } else {
                $no_sampel = strtoupper(trim($request->no_sample));
                $microBio = DetailMicrobiologi::where('no_sampel', $no_sampel)->first();
            
                if ($microBio) {
                    \DB::statement("SET SQL_MODE=''");
            
                    // Ambil semua parameter unik dari data microbiologi
                    $param = DetailMicrobiologi::where('no_sampel', $no_sampel)
                        ->groupBy('parameter')
                        ->get();
            
                    $parKurangShift = [];
            
                    foreach ($param as $item) {
                        // Abaikan parameter dengan shift 'Sesaat'
                        if ($item->shift_pengambilan != 'Sesaat') {
                            $jumlah = DetailMicrobiologi::where('no_sampel', $no_sampel)
                                ->where('parameter', $item->parameter)
                                ->count();
            
                            if ($jumlah < 3) {
                                $parKurangShift[] = $item->parameter;
                            }
                        }
                    }
            
                    // Ambil parameter dari data OrderDetail
                    $paramTarget = json_decode($data->parameter, true);
                    $paramTerisi = $param->pluck('parameter')->toArray();
            
                    // Cari parameter yang belum diisi sama sekali
                    $paramBelumIsi = array_values(array_diff($paramTarget, $paramTerisi));
            
                    // Gabungkan dua jenis kekurangan parameter
                    $paramFinal = array_merge($parKurangShift, $paramBelumIsi);
            
                    // Hilangkan duplikat jika ada
                    $paramFinal = array_values(array_unique($paramFinal));
            
                    $cek = MasterSubKategori::find(explode('-', $data->kategori_3)[0]);
            
                    return response()->json([
                        'no_sample'  => $data->no_sampel,
                        'jenis'      => $cek->nama_sub_kategori ?? null,
                        'keterangan' => $data->keterangan_1,
                        'id_ket'     => explode('-', $data->kategori_3)[0],
                        'param'      => json_encode($paramFinal),
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
            $fdl = DataLapanganMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
            if ($request->waktu == '') {
                return response()->json([
                    'message' => 'Jam pengambilan tidak boleh kosong .!'
                ], 401);
            }
            if ($request->param != null) {
                foreach ($request->param as $en => $ab) {
                    $cek = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('parameter', $ab)->get();

                    if ($request->shift[$en] !== "Sesaat") {
                        $nilai_array = array();
                        foreach ($cek as $key => $value) {
                            $nilai_array[$key] = $value->shift_pengambilan;
                        }
                        if (in_array($request->shift[$en], $nilai_array)) {
                            return response()->json([
                                'message' => 'Pengambilan' . $ab . ' Shift ' . $request->shift[$en] . ' sudah ada !'
                            ], 401);
                        }
                    }
                }
            }
            foreach ($request->param as $in => $a) {
                $namaAlat = $request->nama_alat[$in] ?? null;
                $namaAlatmanual = $request->nama_alat_manual[$in] ?? null;
                $pengukuran = [
                    'Flow Rate' => $request->flow[$in],
                    'Durasi' => $request->durasi[$in] . ' menit'
                ];
                $fdlvalue = new DetailMicrobiologi();
                $fdlvalue->parameter                         = $a;
                $fdlvalue->shift_pengambilan                   = $request->shift[$in];
                if($request->metode_uji[$in] != '')         $fdlvalue->metode_uji           = $request->metode_uji[$in];
                if($request->metode_sampling[$in] != '')    $fdlvalue->metode_sampling      = $request->metode_sampling[$in];
                
                if ($namaAlat !== null && $namaAlat !== '') {
                    $fdlvalue->nama_alat = $namaAlat;
                }
                if ($namaAlatmanual !== null && $namaAlatmanual !== '') {
                    $fdlvalue->nama_alat_manual = $namaAlatmanual;
                }

                $fdlvalue->no_sampel                 = strtoupper(trim($request->no_sampel));
                if ($request->penamaan_titik != '') $fdlvalue->keterangan            = $request->penamaan_titik;
                if ($request->penamaan_tambahan != '') $fdlvalue->keterangan_2          = $request->penamaan_tambahan;
                if ($request->kondisi != '') $fdlvalue->kondisi_ruangan                    = $request->kondisi;
                if ($request->ventilasi != '') $fdlvalue->ventilasi                = $request->ventilasi;
                if ($request->waktu != '') $fdlvalue->waktu_pengukuran                        = $request->waktu;
                if ($request->suhu != '') $fdlvalue->suhu                          = $request->suhu;
                if ($request->kelem != '') $fdlvalue->kelembapan                        = $request->kelem;
                if ($request->tekU != '') $fdlvalue->tekanan_udara                     = $request->tekU;
                $fdlvalue->pengukuran                          = json_encode($pengukuran);
                if ($request->catatan_sampler != '') $fdlvalue->catatan_sampling                      = $request->catatan_sampler;
                if ($request->permission != '') $fdlvalue->permission                     = $request->permission;

                // DOKUMENTASI
                if ($request->statFoto == 'adaFoto') {
                    if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                    if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                } else {
                    if ($request->foto_lokasi_sampel != '') $fdlvalue->foto_lokasi_sampel   = self::convertImg($request->foto_lokasi_sampel, 1, $this->user_id);
                    if ($request->foto_alat != '') $fdlvalue->foto_kondisi_sampel     = self::convertImg($request->foto_alat, 2, $this->user_id);
                    if ($request->foto_lain != '') $fdlvalue->foto_lain                 = self::convertImg($request->foto_lain, 3, $this->user_id);
                }
                $fdlvalue->created_by                                                  = $this->karyawan;
                $fdlvalue->created_at                                                 = Carbon::now()->format('Y-m-d H:i:s');
                $fdlvalue->save();
            }
            if (is_null($fdl)) {
                $data = new DataLapanganMicrobiologi();
                if ($request->id_kategori_3 != '') $data->kategori_3                 = $request->id_kategori_3;
                $data->no_sampel                                              = strtoupper(trim($request->no_sampel));
                $data->created_by                                             = $this->karyawan;
                $data->created_at                                            = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            }

            $this->resultx = "Data Sampling FDL MICROBIOLOGI Dengan No Sample $request->no_sampel berhasil disimpan oleh $this->karyawan";

            $update = DB::table('order_detail')
                ->where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->update(['tanggal_terima' => Carbon::now()->format('Y-m-d H:i:s')]);

            InsertActivityFdl::by($this->user_id)->action('input')->target("Microbiologi Udara pada nomor sampel $request->no_sampel")->save();

                DB::commit();
            return response()->json([
                'message' => $this->resultx
            ], 200);
        }catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'code' => $e->getCode()
            ], 401);
        }
    }

    public function index(Request $request)
    {
        try {
            $perPage = $request->input('limit', 10);
            $page = $request->input('page', 1);
            $search = $request->input('search');

            $query = DataLapanganMicrobiologi::with(['detail', 'detailMicrobiologi'])
                ->where('created_by', $this->karyawan)
                ->where(function ($q) {
                    $q->where('is_rejected', 1)
                    ->orWhere(function ($q2) {
                        $q2->where('is_rejected', 0)
                            ->whereDate('created_at', '>=', Carbon::now()->subDays(7));
                    });
                });


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

            $modified = $data->getCollection()->map(function ($item) {
                $item->grouped_shift = $item->detailMicrobiologi
                    ->groupBy('shift_pengambilan')
                    ->map(function ($group) {
                        return $group->values(); 
                    });
                return $item;
            });

            $data->setCollection($modified);

            return response()->json($data);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Get Data'
            ]);
        }
    }

    public function approve(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganMicrobiologi::where('id', $request->id)->first();

            $no_sample = $data->no_sampel;

            $data->is_approve     = 1;
            $data->approved_by = $this->karyawan;
            $data->approved_at = Carbon::now()->format('Y-m-d H:i:s');
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
        if ($request->tip == 1) {
            $data = DataLapanganMicrobiologi::with('detail')->where('no_sampel', $request->no_sample)->first();
            $this->resultx = 'get Detail sample lapangan Microbio success';
            return response()->json([
                'no_sample'      => $data->no_sampel,
                'no_order'       => $data->detail->no_order,
                'categori'       => explode('-', $data->detail->kategori_3)[1],
                'sampler'        => $data->created_by,
                'corp'           => $data->detail->nama_perusahaan,
            ], 200);
        } else if ($request->tip == 2) {
            $data = DetailMicrobiologi::with('detail')->where('no_sampel', $request->no_sample)->get();
            $this->resultx = 'get Detail sample lapangan Microbiologi success';
            return response()->json([
                'data'             => $data,
            ], 200);
        } else if ($request->tip == 3) {
            $data = DetailMicrobiologi::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan Microbiologi success';
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    public function delete(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->id) {
                return response()->json(['message' => 'Gagal Delete, ID tidak valid'], 400);
            }

            $header = DataLapanganMicrobiologi::find($request->id);
            if (!$header) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $no_sampel = strtoupper(trim($header->no_sampel));
            DetailMicrobiologi::where('no_sampel', $no_sampel)->delete();

            $this->resultx = "Data Sampling FDL MICROBIOLOGI Dengan No Sample $no_sampel berhasil disimpan oleh $this->karyawan";

            $header->delete();

            InsertActivityFdl::by($this->user_id)->action('delete')->target("Microbiologi Udara pada nomor sampel $no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => $this->resultx,
            ]);
        } catch (\Throwable $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Gagal Delete',
                // 'error' => $e->getMessage(), // Aktifkan jika debugging
            ], 500);
        }
    }

    public function deleteParameter(Request $request)
    {
        DB::beginTransaction();
        try {
            $data = DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sampel)))
                ->where('id', $request->id)
                ->first();

            if (!$data) {
                return response()->json(['message' => 'Data tidak ditemukan'], 404);
            }

            $parameter = $data->parameter;

            $data->delete();

            InsertActivityFdl::by($this->user_id)
                ->action('delete')
                ->target("parameter $parameter di nomor sampel {$request->no_sampel}")
                ->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl Microbiologi parameter $parameter di no sample {$request->no_sampel} berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Gagal Delete',
            ], 500);
        }
    }

    public function deleteShift(Request $request)
    {
        DB::beginTransaction();
        try {
            DetailMicrobiologi::where('no_sampel', strtoupper(trim($request->no_sampel)))
            ->where('shift_pengambilan', $request->shift)
            ->delete();
            
            InsertActivityFdl::by($this->user_id)->action('delete')->target(" shift $request->shift di nomor sampel $request->no_sampel")->save();

            DB::commit();

            return response()->json([
                'message' => "Fdl Microbiologi shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh {$this->karyawan}.!",
            ]);
        } catch (\Exception $th) {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 500);
        }   
    }

    public function getShift(Request $request)
    {
        $no_sampel = strtoupper(trim($request->no_sample));
        $shift = $request->shift;
        $importantKeyword = [
            "T. Bakteri (8 Jam)", "T. Jamur (8 Jam)", "T. Jamur (1 Jam)",
            "T. Bakteri (1 Jam)", "Jumlah Bakteri Total", "Fungal Counts",
            "T. Bakteri (KUDR - 8 Jam)", "T. Jamur (KUDR - 8 Jam)",
            "Bacterial Counts", "E.Coli (KB)", "E.Coli", "Total Bakteri", "Total Bakteri (KB)", "Total Coliform", "LEGIONELLA"
        ];

        $data = DetailMicrobiologi::where('no_sampel', $no_sampel)
            ->where('shift_pengambilan', $shift)
            ->first();

        $po = OrderDetail::where('no_sampel', $no_sampel)
            ->where('is_active', true)
            ->first();

        if (!$po) {
            return response()->json([
                'error' => 'Order sample tidak ditemukan atau tidak aktif'
            ], 404);
        }

        \DB::statement("SET SQL_MODE=''");

        // Ambil semua parameter unik dari data microbiologi
        $param = DetailMicrobiologi::where('no_sampel', $no_sampel)
            ->groupBy('parameter')
            ->get();

        $parKurangShift = [];

        foreach ($param as $item) {
            if ($item->shift_pengambilan != 'Sesaat') {
                $jumlah = DetailMicrobiologi::where('no_sampel', $no_sampel)
                    ->where('parameter', $item->parameter)
                    ->count();

                if ($jumlah < 3) {
                    $parKurangShift[] = $item->parameter;
                }
            }
        }

        // Ambil parameter dari data OrderDetail
        $paramTarget = json_decode($po->parameter, true);
        $paramTerisi = $param->pluck('parameter')->toArray();

        // Cari parameter yang belum diisi sama sekali
        $paramBelumIsi = array_values(array_diff($paramTarget, $paramTerisi));

        $mergedParams = array_merge($parKurangShift, $paramBelumIsi);

        $paramFinal = array_values(array_unique(array_map(function ($item) {
            $parts = explode(';', $item);
            return isset($parts[1]) ? trim($parts[1]) : trim($parts[0]);
        }, $mergedParams)));
        
        // Ambil informasi jenis dari MasterSubKategori
        $kategoriId = explode('-', $po->kategori_3)[0];
        $cek = MasterSubKategori::find($kategoriId);

        $parameterList = ParameterFdl::select("parameters")->where('is_active', 1)->where('nama_fdl','microbiologi')->first();
        $response = [
            'no_sample'  => $po->no_sampel,
            'jenis'      => $cek->nama_sub_kategori ?? null,
            'keterangan' => $po->keterangan_1,
            'id_ket'     => $kategoriId,
            'param'      => $paramFinal,
            'is_filled'  => $data ? true : false,
            'important_keyword' => $importantKeyword,
            'parameterList' => json_decode($parameterList->parameters,true),
        ];

        // Jika data shift ada, tambahkan flag dan data
        if ($data) {
            $response['non'] = 1;
            $response['data'] = $data;
        }
        return response()->json($response, 200);
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