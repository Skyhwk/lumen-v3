<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganLingkunganHidup;
use App\Models\DetailLingkunganHidup;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\LingkunganHeader;
use App\Models\WsValueLingkungan;
use App\Models\WsValueUdara;

use App\Services\NotificationFdlService;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Yajra\Datatables\Datatables;

class FdlLingkunganHidupController extends Controller
{
    public function index(Request $request){
        $this->autoBlock();
        $data = DataLapanganLingkunganHidup::with('detail', 'detailLingkunganHidup')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.tanggal_sampling', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('tanggal_sampling', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.nama_perusahaan', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('nama_perusahaan', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('detail.no_order', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('no_order', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_sampel_lama', function ($query, $keyword) {
                $query->where('no_sampel_lama', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('detail.kategori_3', function ($query, $keyword) {
                $query->whereHas('detail', function ($q) use ($keyword) {
                    $q->where('kategori_3', 'like', '%' . $keyword . '%');
                });
            })
            ->filterColumn('is_approve', function ($query, $keyword) {
                $query->where('is_approve', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function approve(Request $request){
        DB::beginTransaction();
        try {
            if (isset($request->id) && $request->id != null) {
                $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();

                if ($data != null) {

                    $order = OrderDetail::where('no_sampel', $data->no_sampel)->first();

                    if ($order) {
                        $tanggalTerima = $order->tanggal_terima;
                        $parameterArray = json_decode($order->parameter, true); // pastikan parameter disimpan sebagai JSON di database
                    } else {
                        return response()->json([
                            'status' => 'error',
                            'message' => 'Order tidak ditemukan'
                        ], 404);
                    }

                    $parameter = [];
                    $id_parameter = [];

                    if (is_array($parameterArray)) {
                        foreach ($parameterArray as $item) {
                            $parts = explode(';', $item);
                            $id_parameter[] = trim($parts[0] ?? '');
                            $parameter[] = trim($parts[1] ?? '');
                        }
                    }

                    $targetParams = [
                        'Suhu' => 'suhu',
                        'Kelembaban' => 'kelembapan',
                        'Laju Ventilasi' => 'auto_laju',
                        'Tekanan Udara' => 'tekanan_udara',
                        'Laju Ventilasi (8 Jam)' => 'auto_laju',
                        'Kelembaban 8J (LK)' => 'kelembapan',
                        'Suhu 8J (LK)' => 'suhu',
                    ];

                    $foundParams = array_intersect($parameter, array_keys($targetParams));

                    // Ambil detail hanya sekali
                    $details = DetailLingkunganHidup::where('no_sampel', $data->no_sampel)
                        ->get();
                    

                    if(!empty($foundParams)) {
                        // Loop setiap parameter
                        foreach ($foundParams as $index => $param) {

                            $column = $targetParams[$param];
                            $angkaKoma = Str::contains($param, 'Laju Ventilasi (8 Jam)');

                            // Handle kolom auto_laju
                            if ($column === 'auto_laju') {
                                $lokasi = optional($details->first())->lokasi;
                                $column = ($lokasi === 'Indoor') ? 'laju_ventilasi' : 'kecepatan_angin';
                            }

                            // Ambil rata-rata nilai parameter
                            $nilaiList = $details->pluck($column)->filter(fn($val) => $val !== null && $val !== '');
                            $rataRata = $nilaiList->count() > 0 ? round($nilaiList->avg(), $angkaKoma ? 2 : 1) : null;

                            $satuan = null;
                            $lowerParam = strtolower($param);

                            // Simpan Header
                            $header = LingkunganHeader::updateOrCreate(
                                [
                                    'no_sampel' => $data->no_sampel,
                                    'id_parameter' => $id_parameter[$index] ?? null,
                                ],
                                [
                                    'parameter' => $param,
                                    'template_stp' => 30,
                                    'tanggal_terima' => $tanggalTerima,
                                    'created_by' => $this->karyawan,
                                    'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
                                    'is_approved' => true,
                                    'approved_by' => $this->karyawan,
                                    'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
                                ]
                            );

                            // id header
                            $id_header = $header->id;

                            $dataLingkungan = [];
                            $dataUdara = [];

                            if (Str::contains($lowerParam, 'suhu')) {
                                $dataLingkungan['C11'] = $rataRata;
                                $dataUdara['hasil12'] = $rataRata;
                                $dataUdara['satuan'] = '°C';
                            }

                            if (Str::contains($lowerParam, 'kelembaban')) {
                                $dataLingkungan['C4'] = $rataRata;
                                $dataUdara['hasil5'] = $rataRata;
                                $dataUdara['satuan'] = '%';
                            }

                            if (Str::contains($lowerParam, 'laju ventilasi')) {
                                $dataLingkungan['C7'] = $rataRata;
                                $dataUdara['hasil8'] = $rataRata;
                                $dataUdara['satuan'] = 'm/s';
                            }


                            // Simpan ke WsValueLingkungan
                            WsValueLingkungan::updateOrCreate(
                                [
                                    'lingkungan_header_id' => $id_header,
                                    'no_sampel' => $data->no_sampel,
                                ],
                                $dataLingkungan
                            );

                            // Simpan ke WsValueUdara
                            WsValueUdara::updateOrCreate(
                                [
                                    'id_lingkungan_header' => $id_header,
                                    'no_sampel' => $data->no_sampel,
                                ],
                                $dataUdara
                            );
                        }
                    }

                    $data->is_approve   = true;
                    $data->approved_by  = $this->karyawan;
                    $data->approved_at  = Carbon::now();
                    $data->save();

                    DetailLingkunganHidup::where('no_sampel', $data->no_sampel)->update([
                        'is_approve'  => true,
                        'approved_by' => $this->karyawan,
                        'approved_at' => Carbon::now()
                    ]);

                    app(NotificationFdlService::class)->sendApproveNotification(
                        'Lingkungan Hidup',
                        $data->no_sampel,
                        $this->karyawan,
                        $data->created_by
                    );

                    DB::commit();

                    return response()->json([
                        'status'  => 'success',
                        'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove'
                    ], 200);
                }

                // kalau data tidak ditemukan
                DB::rollBack();
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Data tidak ditemukan'
                ], 404);
            }

            // kalau request->id null
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal Approve, ID tidak valid'
            ], 400);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal Approve: ' . $e->getMessage(),
                'line'    => $e->getLine()
            ], 500);
        }
    }

    // public function approve(Request $request){
    //     DB::beginTransaction();
    //     try {
    //         if (isset($request->id) && $request->id != null) {
    //             $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();

    //             if ($data != null) {

    //                 $order = OrderDetail::where('no_sampel', $data->no_sampel)->first();

    //                 if ($order) {
    //                     $tanggalTerima = $order->tanggal_terima;
    //                     $parameterArray = json_decode($order->parameter, true); // pastikan parameter disimpan sebagai JSON di database
    //                 } else {
    //                     return response()->json([
    //                         'status' => 'error',
    //                         'message' => 'Order tidak ditemukan'
    //                     ], 404);
    //                 }

    //                 $parameter = [];
    //                 $id_parameter = [];

    //                 if (is_array($parameterArray)) {
    //                     foreach ($parameterArray as $item) {
    //                         $parts = explode(';', $item);
    //                         $id_parameter[] = trim($parts[0] ?? '');
    //                         $parameter[] = trim($parts[1] ?? '');
    //                     }
    //                 }

    //                 $targetParams = [
    //                     'Suhu' => 'suhu',
    //                     'Kelembaban' => 'kelembapan',
    //                     'Laju Ventilasi' => 'auto_laju',
    //                     'Tekanan Udara' => 'tekanan_udara',
    //                     'Laju Ventilasi (8 Jam)' => 'auto_laju',
    //                     'Kelembaban 8J (LK)' => 'kelembapan',
    //                     'Suhu 8J (LK)' => 'suhu',
    //                 ];

    //                 $foundParams = array_intersect($parameter, array_keys($targetParams));

    //                 // Ambil detail hanya sekali
    //                 $detailsSesaat = DetailLingkunganHidup::where('no_sampel', $data->no_sampel)
    //                     ->where('kategori_pengujian', 'Sesaat')
    //                     ->get();

    //                 $details8Jam = DetailLingkunganHidup::where('no_sampel', $data->no_sampel)
    //                     ->where(function ($query) {
    //                         $query->where('kategori_pengujian', 'like', '%8J%')
    //                             ->orWhere('kategori_pengujian', 'like', '%8 Jam%');
    //                     })
    //                     ->get();
                    
    //                 $c1 =NULL;
    //                 $c2 =NULL;
    //                 $c3 =NULL;
    //                 $c4 =NULL;
    //                 $c5 =NULL;
    //                 $c6 =NULL;
    //                 $c7 =NULL;
    //                 $c8 =NULL;
    //                 $c9 =NULL;
    //                 $c10 =NULL;
    //                 $c11 =NULL;
    //                 $c12 =NULL;

    //                 if(!empty($foundParams)) {
    //                     // Loop setiap parameter
    //                     foreach ($foundParams as $index => $param) {
    //                         $column = $targetParams[$param];
    //                         $is8Jam = Str::contains($param, ['8J', '8 Jam']);
    //                         $angkaKoma = Str::contains($param, 'Laju Ventilasi (8 Jam)');
    //                         $details = $detailsSesaat->merge($details8Jam);
    //                         // $details = $is8Jam ? $details8Jam : $detailsSesaat;

    //                         // Handle kolom auto_laju
    //                         if ($column === 'auto_laju') {
    //                             $lokasi = optional($details->first())->lokasi;
    //                             $column = ($lokasi === 'Indoor') ? 'laju_ventilasi' : 'kecepatan_angin';
    //                         }

    //                         // Ambil rata-rata nilai parameter
    //                         $nilaiList = $details->pluck($column)->filter(fn($val) => $val !== null && $val !== '');
    //                         $rataRata = $nilaiList->count() > 0 ? round($nilaiList->avg(), $angkaKoma ? 2 : 1) : null;

    //                         $satuan = null;
    //                         $lowerParam = strtolower($param);

    //                         if (Str::contains($lowerParam, 'suhu')) {
    //                             $satuan = '°C';
    //                             $c12 = $rataRata; //°C
    //                         } elseif (Str::contains($lowerParam, 'kelembaban')) {
    //                             $satuan = '%';
    //                             $c5 = $rataRata; //%
    //                         } elseif (Str::contains($lowerParam, 'laju ventilasi')) {
    //                             $satuan = 'm/s';
    //                             $c8 = $rataRata; //m/s
    //                         } elseif (Str::contains($lowerParam, 'tekanan udara')) {
    //                             $satuan = 'mmHg';
    //                         }

    //                         // Simpan Header
    //                         $header = LingkunganHeader::updateOrCreate(
    //                             [
    //                                 'no_sampel' => $data->no_sampel,
    //                                 'id_parameter' => $id_parameter[$index] ?? null,
    //                             ],
    //                             [
    //                                 'parameter' => $param,
    //                                 'template_stp' => 30,
    //                                 'tanggal_terima' => $tanggalTerima,
    //                                 'created_by' => $this->karyawan,
    //                                 'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                                 'is_approved' => true,
    //                                 'approved_by' => $this->karyawan,
    //                                 'approved_at' => Carbon::now()->format('Y-m-d H:i:s')
    //                             ]
    //                         );

    //                         // id header
    //                         $id_header = $header->id;

    //                         // Simpan ke WsValueLingkungan
    //                         WsValueLingkungan::updateOrCreate(
    //                             [
    //                                 'lingkungan_header_id' => $id_header,
    //                                 'no_sampel' => $data->no_sampel, // <- harus pakai no_sampel, bukan rata-rata
    //                             ],
    //                             [
    //                                 'C' => $c1,
    //                                 'C1' => $c2,
    //                                 'C2' => $c3,
    //                                 'C3' => $c4,
    //                                 'C4' => $c5,
    //                                 'C5' => $c6,
    //                                 'C6' => $c7,
    //                                 'C7' => $c8,
    //                                 'C8' => $c9,
    //                                 'C9' => $c10,
    //                                 'C10' => $c11,
    //                                 'C11' => $c12,
    //                                 'tanggal_terima' =>$tanggalTerima,
    //                                 'created_at' => Carbon::now()->format('Y-m-d H:i:s'),
    //                                 'created_by' => $this->karyawan,
    //                             ]
    //                         );

    //                         // Simpan ke WsValueUdara
    //                         WsValueUdara::updateOrCreate(
    //                             [
    //                                 'id_lingkungan_header' => $id_header,
    //                                 'no_sampel' => $data->no_sampel,
    //                             ],
    //                             [
    //                                 'hasil1' => $c1,
    //                                 'hasil2' => $c2,
    //                                 'hasil3' => $c3,
    //                                 'hasil4' => $c4,
    //                                 'hasil5' => $c5,
    //                                 'hasil6' => $c6,
    //                                 'hasil7' => $c7,
    //                                 'hasil8' => $c8,
    //                                 'hasil9' => $c9,
    //                                 'hasil10' => $c10,
    //                                 'hasil11' => $c11,
    //                                 'hasil12' => $c12,
    //                                 'satuan' => $satuan,
    //                             ]
    //                         );
    //                     }
    //                 }

    //                 $data->is_approve   = true;
    //                 $data->approved_by  = $this->karyawan;
    //                 $data->approved_at  = Carbon::now();
    //                 $data->save();

    //                 DetailLingkunganHidup::where('no_sampel', $data->no_sampel)->update([
    //                     'is_approve'  => true,
    //                     'approved_by' => $this->karyawan,
    //                     'approved_at' => Carbon::now()
    //                 ]);

    //                 app(NotificationFdlService::class)->sendApproveNotification(
    //                     'Lingkungan Hidup',
    //                     $data->no_sampel,
    //                     $this->karyawan,
    //                     $data->created_by
    //                 );

    //                 DB::commit();

    //                 return response()->json([
    //                     'status'  => 'success',
    //                     'message' => 'Data no sampel ' . $data->no_sampel . ' berhasil diapprove'
    //                 ], 200);
    //             }

    //             // kalau data tidak ditemukan
    //             DB::rollBack();
    //             return response()->json([
    //                 'status'  => 'error',
    //                 'message' => 'Data tidak ditemukan'
    //             ], 404);
    //         }

    //         // kalau request->id null
    //         DB::rollBack();
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Gagal Approve, ID tidak valid'
    //         ], 400);

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         return response()->json([
    //             'status'  => 'error',
    //             'message' => 'Gagal Approve: ' . $e->getMessage(),
    //             'line'    => $e->getLine()
    //         ], 500);
    //     }
    // }

    public function updateNoSampel(Request $request){
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();

                DetailLingkunganHidup::where('no_sampel', $request->no_sampel_lama)->update([
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama, 
                    ]);
                
                LingkunganHeader::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

                WsValueLingkungan::where('no_sampel', $request->no_sampel_lama)
                ->update(
                    [
                        'no_sampel' => $request->no_sampel_baru,
                        'no_sampel_lama' => $request->no_sampel_lama
                    ]
                );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now();
                $data->save();

                // update OrderDetail
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)
                    ->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel '.$request->no_sampel_lama.' menjadi '.$request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function reject(Request $request){
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();
            $no_sampel = $data->no_sampel;

            $data->is_approve = false;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();
            // dd($data);

            // if($detail_sampler->pin_user!=null){
            //     $nama = $this->karyawan;
            //     $txt = "FDL AIR dengan No sample $no_sampel Telah di Reject oleh $nama";
                
            //     $telegram = new Telegram();
            //     $telegram->send($detail_sampler->pin_user, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Reject',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request){
        if (isset($request->id) || $request->id != null || isset($request->shift) || $request->shift != null) {
            if($request->no_sampel == null){
                return response()->json([
                    'message' => 'Aplikasi anda belum update...!'
                ], 401);
            }
            $detail = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
            if($request->tip == 1){
                $convert_par = ["TSP", "TSP (24 Jam)", "TSP (6 Jam)", "TSP (8 Jam)", "Pb", "Pb (24 Jam)", "Pb (6 Jam)", "Pb (8 Jam)"];
                $convert_24jam = ["TSP (24 Jam)", "Pb (24 Jam)"];
                $convert_8jam = ["TSP (8 Jam)", "Pb (8 Jam)"];
                $convert_6jam = ["TSP (6 Jam)", "Pb (6 Jam)"];
                $convert_sesaat = ["TSP", "Pb"];
                $status_par = '';

                if(in_array($request->parameter, $convert_par)) {
                    if(in_array($request->parameter, $convert_sesaat)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_sesaat)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_sesaat);
                    }else if(in_array($request->parameter, $convert_24jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_24jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_24jam);
                    }else if(in_array($request->parameter, $convert_8jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_8jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_8jam);
                    }else if(in_array($request->parameter, $convert_6jam)) {
                        $detail6 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->whereIn('parameter', $convert_6jam)->get();
                        $detail6->each->delete();
                        $status_par = json_encode($convert_6jam);
                    }

                    $detail2 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->get();
                    if($detail2->count() > 0) {
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $status_par di no sample $request->no_sampel berhasil dihapus oleh $nama.!";
                        // if($this->pin!=null){
                            
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 1
                        ], 201);
                    }else {
                        $data = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                        $data->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $status_par di no sample $request->no_sampel berhasil dihapus oleh oleh $nama.!";
                        // if($this->pin!=null){
                            
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 2
                        ], 201);
                    }
                }else {
                    $detail3 = DetailLingkunganHidup::where('id', $request->id)->first();
                    if($detail->count() > 1) {
                        $detail3->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $detail->parameter di no sample $detail->no_sampel berhasil dihapus oleh $nama.!";
                        // if($this->pin!=null){
                            
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 1
                        ], 201);
                    }else {
                        $data2 = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                        $detail3->delete();
                        $data2->delete();
                        $nama = $this->karyawan;
                        $this->resultx = "Fdl LH parameter $detail->parameter di no sample $detail->no_sampel berhasil dihapus oleh $nama.!";
                        // if($this->pin!=null){
                            
                        //     $telegram = new Telegram();
                        //     $telegram->send($this->pin, $this->resultx);
                        // }
                        return response()->json([
                            'message' => $this->resultx,
                            'kategori' => 2
                        ], 201);
                    }
                }
            }else if($request->tip == 2) {
                $detail4 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->where('shift_pengambilan', $request->shift)->get();
                $shift = array();
                foreach ($detail as $dat) {
                    $shift[$dat['shift_pengambilan']][] = $dat;
                }
                if(count($shift) > 1) {
                    $detail4->each->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LH shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh $nama.!";
                   
                    // if($this->pin!=null){
                        
                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }
                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 1
                    ], 201);
                }else {
                    $data3 = DataLapanganLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->first();
                    $detail4->each->delete();
                    $data3->delete();

                    $nama = $this->karyawan;
                    $this->resultx = "Fdl LH shift $request->shift di no sample $request->no_sampel berhasil dihapus oleh $nama.!";
                    
                    // if($this->pin!=null){
                        
                    //     $telegram = new Telegram();
                    //     $telegram->send($this->pin, $this->resultx);
                    // }

                    return response()->json([
                        'message' => $this->resultx,
                        'cat' => 2
                    ], 201);
                }
            }else if($request->tip == 3){
                $data4 = DataLapanganLingkunganHidup::where('id', $request->id)->first();
                $detail5 = DetailLingkunganHidup::where('no_sampel', strtoupper(trim($request->no_sampel)))->delete();
                $data4->delete();

                $nama = $this->karyawan;
                $this->resultx = "Fdl LH no sample $request->no_sampel berhasil dihapus oleh $nama.!";
                
                // if($this->pin!=null){
                //     $telegram = new Telegram();
                //     $telegram->send($this->pin, $this->resultx);
                // }

                return response()->json([
                    'message' => $this->resultx,
                ], 201);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request){
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();
                $data->is_blocked     = false;
                $data->blocked_by    = null;
                $data->blocked_at    = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();
                $data->is_blocked     = true;
                $data->blocked_by    = $this->karyawan;
                $data->blocked_at    = Carbon::now();
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Blocked for user',
                    'master_kategori' => 1
                ], 200);
            }
        } else {
            return response()->json([
                'message' => 'Gagal Melakukan Blocked'
            ], 401);
        }
    }

    public function detail(Request $request){
        if($request->tip == 1) {
            $data = DataLapanganLingkunganHidup::with('detail')->where('no_sampel', $request->no_sampel)->first();
            $this->resultx = 'get Detail sample lingkuhan hidup success';

            return response()->json([
                'no_sampel'        => $data->no_sampel,
                'no_order'         => $data->detail->no_order,
                'sub_kategori'     => explode('-', $data->detail->kategori_3)[1],
                'id_sub_kategori'  => explode('-', $data->detail->kategori_3)[0],
                'sampler'          => $data->created_by,
                'nama_perusahaan'  => $data->detail->nama_perusahaan,
            ], 200);

        }else if($request->tip == 2) {
            $data = DetailLingkunganHidup::with('detail')->where('no_sampel', $request->no_sampel)->get();
            $this->resultx = 'get Detail sample lapangan lingkungan hidup success';
            
            return response()->json([
                'data'             => $data,
            ], 200);

        }else if($request->tip == 3) {
            $data = DetailLingkunganHidup::with('detail')->where('id', $request->id)->first();
            $this->resultx = 'get Detail sample lapangan lingkungan hidup success';
            
            return response()->json([
                'data'             => $data,
            ], 200);
        }
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganLingkunganHidup::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganLingkunganHidup::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Lingkungan Hidup", $request->no_sampel, $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

} 