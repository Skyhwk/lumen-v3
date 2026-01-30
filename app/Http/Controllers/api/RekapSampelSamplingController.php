<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\TcOrderDetail;
use App\Models\Parameter;
use App\Models\MasterKategori;
use App\Models\MasterSubKategori;
use App\Models\HargaParameter;
use App\Models\MasterRegulasi;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;
use DataTables;
use Exception;
use Illuminate\Support\Str;
use Log;

class RekapSampelSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = OrderDetail::with(['orderHeader', 'TrackingSatu', 'TrackingDua', 'union', 'tc_order_detail'])
            ->where('kategori_1', 'not like', '%Sd%')
            ->where('is_active', 1);

        // Filter berdasarkan date range
        if ($request->has('date_start') || $request->has('date_end')) {
            $dateStart = $request->date_start;
            $dateEnd = $request->date_end;

            if (!empty($dateStart) && !empty($dateEnd)) {
                $data = $data->whereBetween('tanggal_sampling', [$dateStart, $dateEnd]);
            } elseif (!empty($dateStart)) {
                $data = $data->where('tanggal_sampling', '>=', $dateStart);
            } elseif (!empty($dateEnd)) {
                $data = $data->where('tanggal_sampling', '<=', $dateEnd);
            }
        }

        $data = $data->orderBy('id', 'desc');

        return DataTables::of($data)
            ->filterColumn('order_header.no_document', function ($query, $keyword) {
                $query->whereHas('orderHeader', function ($q) use ($keyword) {
                    $q->where('no_document', 'like', "%$keyword%");
                });
            })
            ->filterColumn('order_header.email_pic_order', function ($query, $keyword) {
                $query->whereHas('orderHeader', function ($q) use ($keyword) {
                    $q->where('email_pic_order', 'like', "%$keyword%");
                });
            })
            ->filterColumn('order_header.nama_pic_order', function ($query, $keyword) {
                $query->whereHas('orderHeader', function ($q) use ($keyword) {
                    $q->where('nama_pic_order', 'like', "%$keyword%");
                });
            })
            ->filterColumn('order_header.nama_pic_sampling', function ($query, $keyword) {
                $query->whereHas('orderHeader', function ($q) use ($keyword) {
                    $q->where('nama_pic_sampling', 'like', "%$keyword%");
                });
            })
            ->filterColumn('tc_order_detail.updated_tc_at', function ($query, $keyword) {
                $query->whereHas('tc_order_detail', function ($q) use ($keyword) {
                    $q->where('updated_tc_at', 'like', "%$keyword%");
                });
            })
            ->filterColumn('tc_order_detail.updated_tc_by', function ($query, $keyword) {
                $query->whereHas('tc_order_detail', function ($q) use ($keyword) {
                    $q->where('updated_tc_by', 'like', "%$keyword%");
                });
            })
            // Tambahan
            ->addColumn('jadwal_lapangan', function ($row) {
                return $row->union ? $row->union['created_at'] : null;
            })
            ->filterColumn('jadwal_lapangan', function ($query, $keyword) {
                $query->whereHas('union', function ($q) use ($keyword) {
                    $q->where('created_at', 'like', "%$keyword%");
                });
            })
            ->addIndexColumn()
            ->rawColumns(['action'])
            ->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function getSubKategori(Request $request)
    {
        $data = MasterSubKategori::where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = Parameter::whereHas('hargaParameter')
            ->where('id_kategori', $request->id_kategori)
            ->where('is_active', 1)
            ->get();
        return response()->json($data);
    }
    public function getParameterNonExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('is_expired', 0)
            ->get();

        return response()->json($data);
    }
    public function getParameterExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('is_expired', 1)
            ->get();

        return response()->json($data);
    }
    public function getParameterPengganti(Request $request)
    {
        $param = [];
        $pengganti = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('is_expired', 1)
            ->get();

        foreach ($data as $key => $value) {
            $penggantiParam  = Parameter::where('id', $value->id_parameter_pengganti)->first();
            if ($penggantiParam) {
                $pengganti[] = $penggantiParam;
            }
        }

        $data = $pengganti;

        return response()->json($data);
    }

    public function checkSampelBeforeSave(Request $request)
    {
        $orderDetail = OrderDetail::where('no_sampel', $request->no_sampel)->first();

        $isSampled = $orderDetail->tanggal_terima !== null;

        return response()->json([
            'is_sampled' => $isSampled
        ], 200);
    }

    public function saveOrderDetail(Request $request)
    {
        DB::beginTransaction();
        // dd($request->all());
        try {
            $orderDetail = OrderDetail::where(['id' => $request->id, 'is_active' => true])->first();
            $beforeUpdate = clone $orderDetail;
            if ($request->tgl_tugas != '') $orderDetail->tanggal_sampling = $request->tgl_tugas;
            if ($request->tgl_terima != '') $orderDetail->tanggal_terima = $request->tgl_terima;
            if ($request->keterangan_1 != '') $orderDetail->keterangan_1 = $request->keterangan_1;
            if ($request->keterangan_2 != '') $orderDetail->keterangan_2 = $request->keterangan_2;
            if ($request->kategori_1 != '') $orderDetail->kategori_1 = $request->kategori_1;
            if ($request->kategori_2 != '') $orderDetail->kategori_2 = $request->kategori_2;
            if ($request->kategori_3 != '') $orderDetail->kategori_3 = $request->kategori_3;
            if ($request->cfr != '') $orderDetail->cfr = $request->cfr;
            if ($request->no_sampel != '') $orderDetail->no_sampel = $request->no_sampel;
            if ($request->fpps != '') $orderDetail->fpps = $request->fpps;
            if ($request->stps != '') $orderDetail->stp_stps = $request->stps;
            if ($request->param != '') {
                $param = (array) $request->param;
                // if ($request->kategori_2 == '1-Air' && $request->expired != '') {
                //     $perubahan = $this->detectExpiredParameterChanges($request->expired);

                //     foreach ($perubahan as $item => $value) {
                //         $val = explode(';', $value['replaced_with'])[1];
                //         $colorimetri = Colorimetri::where('parameter', $value['expired'])->where('no_sampel', $orderDetail->no_sampel)->where('is_active', 1)->first();
                //         if ($colorimetri) {
                //             $colorimetri->parameter = $val;
                //             $colorimetri->save();
                //         }

                //         $titrimetri = Titrimetri::where('parameter', $value['expired'])->where('no_sampel', $orderDetail->no_sampel)->where('is_active', 1)->first();
                //         if ($titrimetri) {
                //             $titrimetri->parameter = $val;
                //             $titrimetri->save();
                //         }

                //         $gravimetri = Gravimetri::where('parameter', $value['expired'])->where('no_sampel', $orderDetail->no_sampel)->where('is_active', 1)->first();
                //         if ($gravimetri) {
                //             $gravimetri->parameter = $val;
                //             $gravimetri->save();
                //         }
                //         array_push($param, $value['replaced_with']);
                //     }
                // }
                $orderDetail->parameter = json_encode($param);
            }
            if ($request->regulasi) $orderDetail->regulasi = json_encode($request->regulasi);
            $orderDetail->updated_by = $this->karyawan;
            $orderDetail->updated_at = Carbon::now();
            $orderDetail->save();

            $tc_update = TcOrderDetail::where('id_order_detail', $orderDetail->id)->first();
            if (!$tc_update) $tc_update = new TcOrderDetail();
            $tc_update->id_order_detail = $orderDetail->id;
            $tc_update->no_sampel       = $orderDetail->no_sampel;
            $tc_update->updated_tc_by   = $this->karyawan;
            $tc_update->updated_tc_at   = Carbon::now()->format('Y-m-d H:i:s');
            $tc_update->save();

            DB::commit();

            if($request->is_forced_update){
                Log::channel('forced_update_sampel')->info('Forcing Update Sampel',[
                    'no_sampel' => $orderDetail->no_sampel,
                    'nama_karyawan' => $this->karyawan,
                    'waktu_update' => Carbon::now()->format('Y-m-d H:i:s'),
                    'before' => $beforeUpdate,
                    'after' => $orderDetail
                ]);
            }

            return response()->json(['message' => 'Saved Successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }

    private function detectExpiredParameterChanges(array $expired): array
    {
        $mapping = [];


        foreach ($expired as $exp) {
            [$expId, $expName] = explode(';', $exp);

            $found = null;
            $parameter = Parameter::where('id', $expId)->first();
            $id_found = $parameter->id_parameter_pengganti;
            if ($id_found) {
                $before_found = Parameter::where('id', $id_found)->first();
                $found = $before_found->nama_lab;
            }

            if ($found) {
                $mapping[] = [
                    'expired' => $expName,
                    'replaced_with' => $id_found . ";" . $found
                ];
            }
        }

        return $mapping;
    }

    private function getBaseName($nama)
    {
        return trim(preg_replace('/\s*\(.*?\)/', '', $nama));
    }

    public function generatePersiapan(Request $request)
    {
        
        DB::beginTransaction();
        try {
            if(isset($request->collectionContain) && $request->collectionContain != null) {
                $data = $request->collectionContain;
            } else {
            $data = OrderDetail::where('is_active', 1)
                    ->where('no_order', $request->no_order)
                    ->where(function($query) {
                        $query->whereJsonDoesntContain('parameter', '309;Pencahayaan')
                            ->whereJsonDoesntContain('parameter', '268;Kebisingan')
                            ->whereJsonDoesntContain('parameter', '318;Psikologi')
                            ->whereJsonDoesntContain('parameter', '230;Ergonomi');
                    })
                    ->where('persiapan', '[]');

                if (isset($request->periode) && $request->periode != null) {
                    $data->where('periode', $request->periode);
                }

                $data = $data->get();
            }
            
            if ($data->isEmpty()) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            foreach ($data as $item => $value) {
                
                if (explode("-", $value->kategori_2)[1] == 'Air') {
                    $parameter_names = array_map(function ($p) {
                        return explode(';', $p)[1];
                    }, json_decode($value->parameter) ?? []);

                    $id_kategori = explode("-", $value->kategori_2)[0];
                    $params = HargaParameter::where('id_kategori', $id_kategori)
                        ->where('is_active', true)
                        ->whereIn('nama_parameter', $parameter_names)
                        ->get();

                    $param_map = [];
                    foreach ($params as $param) {
                        $param_map[$param->nama_parameter] = $param;
                    }

                    $botol_volumes = [];
                    foreach (json_decode($value->parameter) ?? [] as $parameter) {
                        $param_name = explode(';', $parameter)[1];
                        if (isset($param_map[$param_name])) {
                            $param = $param_map[$param_name];
                            if (!isset($botol_volumes[$param->regen])) {
                                $botol_volumes[$param->regen] = 0;
                            }
                            $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                        }
                    }

                    // Generate botol dan barcode
                    $botol = [];

                    $ketentuan_botol = [
                        'ORI' => 1000,
                        'H2SO4' => 1000,
                        'M100' => 100,
                        'HNO3' => 500,
                        'M1000' => 1000,
                        'BENTHOS' => 100
                    ];
                    
                    foreach ($botol_volumes as $type => $volume) {
                        $typeUpper = strtoupper($type);
                        if (!isset($ketentuan_botol[$typeUpper])) {
                            // kalau ketentuan botol tidak ditemukan, skip atau kasih default
                            continue;
                        }
                        $koding = $value->koding_sampling . strtoupper(Str::random(5));

                        // Hitung jumlah botol yang dibutuhkan
                        $jumlah_botol = ceil($volume / $ketentuan_botol[$typeUpper]);

                        $botol[] = (object) [
                            'koding' => $koding,
                            'type_botol' => $type,
                            'volume' => $volume,
                            'file' => $koding . '.png',
                            'disiapkan' => (int) $jumlah_botol
                        ];

                        if (!file_exists(public_path() . '/barcode/botol')) {
                            mkdir(public_path() . '/barcode/botol', 0777, true);
                        }

                        // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                        self::generateQR($koding, '/barcode/botol');
                    }
                    
                    $value->persiapan = json_encode($botol);
                    $value->save();
                } else {
                    /*
                        * Jika kategori bukan air maka tidak perlu membuat botol
                        * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                        */
                    
                    
                    
                    if ($value->kategori_2 == '4-Udara' || $value->kategori_2 == '5-Emisi') {
                    
                        $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                            ->whereIn('parameter', json_decode($value->parameter) ?? [])
                            ->where('is_active', 1)
                            ->get();
                        
                        $persiapan = []; // Pastikan inisialisasi array sebelum digunakan
                        foreach ($cek_ketentuan_parameter as $ketentuan) {
                            $koding = $value->koding_sampling . strtoupper(Str::random(5));
                            $persiapan[] = [
                                'parameter' => \explode(';', $ketentuan->parameter)[1],
                                'disiapkan' => $ketentuan->ketentuan,
                                'koding' => $koding,
                                'file' => $koding . '.png'
                            ];

                            if (!file_exists(public_path() . '/barcode/penjerap')) {
                                mkdir(public_path() . '/barcode/penjerap', 0777, true);
                            }

                            // file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                            self::generateQR($koding, '/barcode/penjerap');
                        }
                        // dd($persiapan, 'persiapan');
                        $value->persiapan = json_encode($persiapan ?? []);
                        $value->save();
                    }
                }
            }
            DB::commit();

            $return = OrderDetail::where('is_active', 1)
            ->where('no_order', $request->no_order);
            if(isset($request->periode) && $request->periode != null) {
                $return->where('periode', $request->periode);
            }
            return $return;
        } catch (Exception $e) {
            DB::rollback();
            dd($e);
            return response()->json(['message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()], 400);
        }
        
    }

    private function generateQR($no_sampel, $directory)
    {
        $filename = \str_replace("/", "_", $no_sampel) . '.png';
        $path = public_path() . "$directory/$filename";
        // if (!file_exists($directory)) {
        //     mkdir($directory, 0777, true);
        // }
        QrCode::format('png')->size(200)->generate($no_sampel, $path);

        return $filename;
    }
}