<?php

namespace App\Http\Controllers\api;

use App\Models\DataLapanganDirectLain;
use App\Models\OrderDetail;
use App\Models\MasterSubKategori;
use App\Models\MasterKaryawan;
use App\Models\Parameter;

use App\Models\DirectLainHeader;
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
use Yajra\Datatables\Datatables;
use App\Models\AnalystFormula as Formula;
use App\Services\AnalystFormula;

class FdlDirectLainController extends Controller
{
    public function index(Request $request)
    {
        $this->autoBlock();
        $data = DataLapanganDirectLain::with('detail')->orderBy('id', 'desc');

        return Datatables::of($data)
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
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
            ->filterColumn('parameter', function ($query, $keyword) {
                $query->where('parameter', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function updateNoSampel(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            DB::beginTransaction();
            try {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();

                DirectLainHeader::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                WsValueUdara::where('no_sampel', $request->no_sampel_lama)
                    ->update(
                        [
                            'no_sampel' => $request->no_sampel_baru,
                            'no_sampel_lama' => $request->no_sampel_lama
                        ]
                    );

                $data->no_sampel = $request->no_sampel_baru;
                $data->no_sampel_lama = $request->no_sampel_lama;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                // update OrderDetail
                $order_detail_lama = OrderDetail::where('no_sampel', $request->no_sampel_lama)->first();

                if ($order_detail_lama) {
                    OrderDetail::where('no_sampel', $request->no_sampel_baru)
                        ->where('is_active', 1)
                        ->update([
                            'tanggal_terima' => $order_detail_lama->tanggal_terima
                        ]);
                }

                DB::commit();
                return response()->json([
                    'message' => 'Berhasil ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru
                ], 200);
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Gagal ubah no sampel ' . $request->no_sampel_lama . ' menjadi ' . $request->no_sampel_baru,
                    'error' => $e->getMessage()
                ], 401);
            }
        } else {
            return response()->json([
                'message' => 'No Sampel tidak boleh kosong'
            ], 401);
        }
    }

    public function approve(Request $request)
    {
        DB::beginTransaction();
        try {
            if (!$request->filled('id')) {
                throw new \Exception('Invalid request: Missing ID');
            }

            // Retrieve initial record
            $initialRecord = DataLapanganDirectLain::findOrFail($request->id);
            $no_sample = $initialRecord->no_sampel;
            $parameterData = $initialRecord->parameter;
            $shift = explode('-', $initialRecord->shift)[0];

            // Get PO & parameter
            $po = OrderDetail::where('no_sampel', $no_sample)->firstOrFail();
            $parameter = Parameter::where('nama_lab', $parameterData)
                ->where('id_kategori', 4)
                ->where('is_active', true)
                ->first();


            $dataLapangan = DataLapanganDirectLain::where('no_sampel', $no_sample)
                ->where('parameter', $parameterData)
                ->where('shift', 'LIKE', $shift . '%')
                ->get();

            $TotalApprove = DataLapanganDirectLain::where('no_sampel', $no_sample)
                ->where('parameter', $parameterData)
                ->where('is_approve', 1)
                ->count();

            $approveCountNeeded = count($dataLapangan);

            // Always approve current record
            $fdl = $initialRecord;
            $fdl->is_approve = 1;
            $fdl->approved_by = $this->karyawan;
            $fdl->approved_at = Carbon::now();
            $fdl->save();

            if ($TotalApprove + 1 >= $approveCountNeeded) {
                $functionObj = Formula::where('id_parameter', $parameter->id)
                    ->where('is_active', true)
                    ->first();
                if(!$functionObj){
                    return response()->json(['message' => 'Formula is Coming Soon'], 404);
                } else{
                    $function = $functionObj->function;
                }

                $data_kalkulasi = AnalystFormula::where('function', $function)
                    ->where('data', $dataLapangan)
                    ->where('id_parameter', $parameter->id)
                    ->process();

                $header = DirectLainHeader::firstOrNew([
                    'no_sampel' => $no_sample,
                    'parameter' => $parameterData,
                ]);

                $header->fill([
                    'id_parameter' => $parameter->id,
                    'is_approve' => 1,
                    // 'lhps' => 1,
                    'approved_by' => $this->karyawan,
                    'approved_at' => Carbon::now(),
                    'created_by' => $header->exists ? $header->created_by : $this->karyawan,
                    'created_at' => $header->exists ? $header->created_at : Carbon::now(),
                    'is_active' => 1,
                ]);
                $header->save();

                WsValueUdara::updateOrCreate(
                    [
                        'no_sampel' => $no_sample,
                        'id_direct_lain_header' => $header->id,
                    ],
                    [
                        'id_po' => $po->id,
                        'is_active' => 1,
                        'hasil1' => $data_kalkulasi['hasil'],
                        // 'hasil2' => $data_kalkulasi['hasil2'], // naik setelah tanggal 10-10-2025
                        // 'hasil3' => $data_kalkulasi['hasil3'], // naik setelah tanggal 10-10-2025
                        // 'hasil4' => $data_kalkulasi['hasil4'], // naik setelah tanggal 10-10-2025
                        'satuan' => $data_kalkulasi['satuan'],
                    ]
                );
            }

            app(NotificationFdlService::class)->sendApproveNotification('Direct Lain', $no_sample, $this->karyawan, $initialRecord->created_by);

            DB::commit();

            return response()->json([
                'message' => 'Data has been Approved',
                'status' => 'success'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
            ], 401);
        }
    }

    private function createOrUpdateHeaderRecord($no_sample, $parameter)
    {
        // Cari header berdasarkan no_sampel dan parameter
        $headUdara = DirectLainHeader::where('no_sampel', $no_sample)
            ->where('parameter', $parameter->nama_lab)
            ->where('is_active', 1)
            ->first();

        if ($headUdara) {
            // Jika header dengan no_sampel dan parameter yang sama ditemukan, update saja
            $headUdara->is_approve = 1;
            $headUdara->approved_by = $this->karyawan;
            $headUdara->approved_at = Carbon::now();
            $headUdara->save();
        } else {
            // Jika tidak ditemukan, buat header baru
            $headUdara = new DirectLainHeader();
            $headUdara->no_sampel = $no_sample;
            $headUdara->id_parameter = $parameter->id;
            $headUdara->parameter = $parameter->nama_lab;
            $headUdara->is_approve = 1;
            $headUdara->approved_by = $this->karyawan;
            $headUdara->approved_at = Carbon::now();
            $headUdara->created_by = $this->karyawan;
            $headUdara->created_at = Carbon::now();
            $headUdara->is_active = 1;
            $headUdara->save();
        }

        return $headUdara;
    }

    private function processAndUpdateWorkspace($no_sample, $po, $parameter, $headUdara, $request){
        // Collect data for specific parameter
        $records = DataLapanganDirectLain::where('no_sampel', $no_sample)
            ->where('parameter', $parameter->nama_lab)
            ->get();

        $function = Formula::where('id_parameter', $parameter->id)->where('is_active', 1)->first()->function;
        $data_parsing = $request->all();
        $data_parsing = (object) $data_parsing;
        $data_parsing->records = $records->collect()->toArray();
        
        $data_kalkulasi = AnalystFormula::where('function', $function)
            ->where('data', $data_parsing)
            ->where('id_parameter', $parameter->id)
            ->process();

        if(!is_array($data_kalkulasi) && $data_kalkulasi == 'Coming Soon') {
            return (object)[
                'message'=> 'Formula is Coming Soon parameter : '.$request->parameter.'',
                'status' => 404
            ];
        }

        // Always update workspace with the calculated average
        $ws = WsValueUdara::where('no_sampel', $no_sample)
            ->where('is_active', 1)
            ->first();

        // Initialize hasil array for new workspace
        $defaultHasil = [
            'avgO2' => 0,
            'avgCO2' => 0,
            'avgCO' => 0,
            'avgVOC' => 0,
            'avgHCHO' => 0,
            'avgH2CO' => 0
        ];

        if (!$ws) {
            // Create new workspace if doesn't exist
            $ws = new WsValueUdara();
            $ws->no_sampel = $no_sample;
            $ws->id_po = $po->id;
            $ws->is_active = 1;
            $ws->id_direct_lain_header = $headUdara->id;

            // Set default values
            $hasil = $defaultHasil;
        } else {
            // Get existing values or use defaults if hasil1 is empty/invalid
            $hasil = json_decode($ws->hasil1, true);
            if (!is_array($hasil)) {
                $hasil = $defaultHasil;
            }
        }

        // Map parameter to hasil key
        $parameterMap = [
            'O2' => 'avgO2',
            'O2 (8 Jam)' => 'avgO2',
            'CO2' => 'avgCO2',
            'CO2 (8 Jam)' => 'avgCO2',
            'CO' => 'avgCO',
            'CO (8 Jam)' => 'avgCO',
            'CO (6 Jam)' => 'avgCO',
            'CO (24 Jam)' => 'avgCO',
            'C O' => 'avgCO',
            'C O (8 Jam)' => 'avgCO',
            'VOC' => 'avgVOC',
            'VOC (8 Jam)' => 'avgVOC',
            'HCHO' => 'avgHCHO',
            'HCHO (8 Jam)' => 'avgHCHO',
            'H2CO' => 'avgH2CO',
            'H2CO (8 Jam)' => 'avgH2CO'
        ];

        // Update the specific parameter value
        if (isset($parameterMap[$parameter->nama_lab])) {
            // $hasil[$parameterMap[$parameter->nama_lab]] = (float) $data_kalkulasi['hasil'];
            $hasil = (float) $data_kalkulasi['hasil'];
            $satuan = $parameterMap[$parameter->nama_lab];
        }

        // Save the updated results
        $ws->hasil1 = $hasil;
        $ws->satuan = $satuan;
        $ws->save();

        return $ws;
        // return $this->createOrUpdateWorkspaceRecord($no_sample, $po, $parameter, $average, $headUdara);
    }

    private function approveRecord($record)
    {
        $record->is_approve = 1;
        $record->approved_by = $this->karyawan;
        $record->approved_at = Carbon::now();
        $record->save();
    }


    // public function approve(Request $request)
    // {
    //     DB::beginTransaction();
    //     try {
    //         if (!$request->filled('id')) {
    //             throw new \Exception('Invalid request: Missing ID');
    //         }

    //         // Retrieve initial record
    //         $initialRecord = DataLapanganDirectLain::findOrFail($request->id);
    //         $no_sample = $initialRecord->no_sampel;
    //         $parameterData = $initialRecord->parameter;

    //         // Find related order and parameter
    //         $po = OrderDetail::where('no_sampel', $no_sample)->firstOrFail();
    //         $parameter = Parameter::where('nama_lab', $parameterData)
    //             ->where('id_kategori', 4)
    //             ->where('is_active', true)
    //             ->firstOrFail();

    //         // Collect related records
    //         $relatedRecords = DataLapanganDirectLain::where('no_sampel', $no_sample)
    //             ->where('parameter', $parameterData)
    //             ->get();

    //         // Process parameter averages
    //         $parametersData = $this->processParameterAverages($relatedRecords);

    //         // Create or update header record
    //         $headUdara = $this->createOrUpdateHeaderRecord($no_sample, $parameter);

    //         // Create or update workspace record
    //         $this->createOrUpdateWorkspaceRecord($no_sample, $po, $parametersData, $headUdara);

    //         // Approve all related records
    //         $this->approveRelatedRecords($relatedRecords);

    //         DB::commit();

    //         return response()->json([
    //             'message' => 'Data has been Approved',
    //             'status' => 'success'
    //         ], 200);

    //     } catch (\Exception $e) {
    //         DB::rollback();
    //         return response()->json([
    //             'message' => $e->getMessage(),
    //             'line' => $e->getLine(),
    //         ], 401);
    //     }
    // }

    // private function processParameterAverages($records)
    // {
    //     $parametersData = [
    //         'O2' => [], 'CO2' => [], 'CO' => [], 
    //         'VOC' => [], 'HCHO' => [], 'H2CO' => []
    //     ];

    //     foreach ($records as $record) {
    //         if ($record->pengukuran) {
    //             $measurements = json_decode($record->pengukuran, true);

    //             if (is_array($measurements)) {
    //                 $total = array_sum($measurements);
    //                 $average = $measurements ? number_format($total / count($measurements), 3) : 0;

    //                 $this->categorizeParameter($record->parameter, $average, $parametersData);
    //             }
    //         }
    //     }

    //     return $this->calculateFinalAverages($parametersData);
    // }

    // private function categorizeParameter($parameter, $average, &$parametersData)
    // {
    //     $paramMapping = [
    //         'O2' => 'O2', 'O2 (8 Jam)' => 'O2',
    //         'CO2' => 'CO2', 'CO2 (8 Jam)' => 'CO2',
    //         'CO' => 'CO', 'CO (8 Jam)' => 'CO',
    //         'VOC' => 'VOC', 'VOC (8 Jam)' => 'VOC',
    //         'HCHO' => 'HCHO', 'HCHO (8 Jam)' => 'HCHO',
    //         'H2CO' => 'H2CO', 'H2CO (8 Jam)' => 'H2CO'
    //     ];

    //     if (isset($paramMapping[$parameter])) {
    //         $parametersData[$paramMapping[$parameter]][] = $average;
    //     }
    // }

    // private function calculateFinalAverages($parametersData)
    // {
    //     $averages = [];
    //     foreach (['O2', 'CO2', 'CO', 'VOC', 'HCHO', 'H2CO'] as $param) {
    //         $averages["avg$param"] = !empty($parametersData[$param]) 
    //             ? number_format(array_sum($parametersData[$param]) / count($parametersData[$param]), 3) 
    //             : 0;
    //     }
    //     return $averages;
    // }

    // private function createOrUpdateHeaderRecord($no_sample, $parameter)
    // {
    //     // Cek apakah ada header dengan no_sample dan parameter yang sama dan is_active = 1
    //     $headUdara = DirectLainHeader::where('no_sampel', $no_sample)
    //         ->where('parameter', $parameter->nama_lab)
    //         ->where('is_active', 1)
    //         ->first();

    //     if ($headUdara) {
    //         // Update existing header record
    //         $headUdara->update([
    //             'is_approve' => 1,
    //             'approved_by' => $this->karyawan,
    //             'approved_at' => Carbon::now(),
    //         ]);
    //     } else {
    //         // Create new header record
    //         $headUdara = DirectLainHeader::create([
    //             'no_sampel' => $no_sample,
    //             'id_parameter' => $parameter->id,
    //             'parameter' => $parameter->nama_lab,
    //             'is_approve' => 1,
    //             'approved_by' => $this->karyawan,
    //             'approved_at' => Carbon::now(),
    //             'created_by' => $this->karyawan,
    //             'created_at' => Carbon::now(),
    //             'is_active' => 1,
    //         ]);
    //     }

    //     return $headUdara;
    // }

    // private function createOrUpdateWorkspaceRecord($no_sample, $po, $parametersData, $headUdara)
    // {
    //     // Cek apakah ada workspace dengan no_sampel yang sama dan is_active = 1
    //     $ws = WsValueUdara::firstOrNew([
    //         'no_sampel' => $no_sample,
    //         'is_active' => 1
    //     ]);

    //     // Ambil data hasil lama (jika ada)
    //     $oldHasil = json_decode($ws->hasil1, true);

    //     // Jika data hasil lama ada dan berupa array, kita gunakan data itu
    //     if (is_array($oldHasil)) {
    //         // Pastikan data lama berupa angka (float atau integer)
    //         foreach ($oldHasil as $key => $value) {
    //             $oldHasil[$key] = (float) $value; // Pastikan tipe data angka
    //         }
    //     } else {
    //         // Jika hasil lama tidak ada atau rusak, set default nilai
    //         $oldHasil = [
    //             'avgO2' => 0,
    //             'avgCO2' => 0,
    //             'avgCO' => 0,
    //             'avgVOC' => 0,
    //             'avgHCHO' => 0,
    //             'avgH2CO' => 0
    //         ];
    //     }

    //     // Update nilai parameter yang disetujui
    //     if (isset($parametersData['avgO2'])) {
    //         $oldHasil['avgO2'] = (float) $parametersData['avgO2'];
    //     }
    //     if (isset($parametersData['avgCO2'])) {
    //         $oldHasil['avgCO2'] = (float) $parametersData['avgCO2'];
    //     }
    //     if (isset($parametersData['avgCO'])) {
    //         $oldHasil['avgCO'] = (float) $parametersData['avgCO'];
    //     }
    //     if (isset($parametersData['avgVOC'])) {
    //         $oldHasil['avgVOC'] = (float) $parametersData['avgVOC'];
    //     }
    //     if (isset($parametersData['avgHCHO'])) {
    //         $oldHasil['avgHCHO'] = (float) $parametersData['avgHCHO'];
    //     }
    //     if (isset($parametersData['avgH2CO'])) {
    //         $oldHasil['avgH2CO'] = (float) $parametersData['avgH2CO'];
    //     }

    //     // Simpan hasil yang sudah diperbarui ke dalam kolom hasil1
    //     $ws->hasil1 = json_encode($oldHasil);
    //     $ws->id_po = $po->id;
    //     $ws->save();
    // }

    // private function approveRelatedRecords($records)
    // {
    //     foreach ($records as $record) {
    //         // Update approval status for each related record
    //         $record->update([
    //             'is_approve' => 1,
    //             'approved_by' => $this->karyawan,
    //             'approved_at' => Carbon::now()
    //         ]);
    //     }
    // }


    // Previous methods remain the same (calculateParameterAverages, etc.)

    public function reject(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;

            $data->is_approve = 0;
            $data->rejected_at = Carbon::now();
            $data->rejected_by = $this->karyawan;
            $data->approved_by = null;
            $data->approved_at = null;
            $data->save();

            // if($cek_sampler->pin_user!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Reject oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($cek_sampler->pin_user, $txt);
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

    public function rejectData(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();

            $data->is_rejected = true;
            $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->rejected_by = $this->karyawan;
            $data->save();

            app(NotificationFdlService::class)->sendRejectNotification("Direct Lain dengan shift($data->shift)", "$request->no_sampel($data->parameter)", $request->reason, $this->karyawan, $data->created_by);
            
            return response()->json([
                'message' => 'Data no sample ' . $data->no_sampel . ' telah di reject'
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Approve'
            ], 401);
        }
    }

    public function delete(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            $data = DataLapanganDirectLain::where('id', $request->id)->first();
            $no_sample = $data->no_sampel;
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

            // if($this->pin!=null){
            //     $nama = $this->name;
            //     $txt = "FDL AIR dengan No sample $no_sample Telah di Hapus oleh $nama";

            //     $telegram = new Telegram();
            //     $telegram->send($this->pin, $txt);
            // }

            return response()->json([
                'message' => 'Data has ben Delete',
                'master_kategori' => 1
            ], 201);
        } else {
            return response()->json([
                'message' => 'Gagal Delete'
            ], 401);
        }
    }

    public function block(Request $request)
    {
        if (isset($request->id) && $request->id != null) {
            if ($request->is_blocked == true) {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();
                $data->is_blocked = false;
                $data->blocked_by = null;
                $data->blocked_at = null;
                $data->save();
                return response()->json([
                    'message' => 'Data has ben Unblocked for user',
                    'master_kategori' => 1
                ], 200);
            } else {
                $data = DataLapanganDirectLain::where('id', $request->id)->first();
                $data->is_blocked = true;
                $data->blocked_by = $this->karyawan;
                $data->blocked_at = Carbon::now();
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

    public function detail(Request $request)
    {
        $data = DataLapanganDirectLain::with('detail')
            ->where('id', $request->id)
            ->first();

        $this->resultx = 'get Detail sample Direct lainnya Berhasil';

        if (!$data) {
            return response()->json(['error' => 'Data not found'], 404);
        }

        return response()->json([
            'id' => $data->id ?? '-',
            'no_sample' => $data->no_sampel ?? '-',
            'no_order' => $data->detail->no_order ?? '-',
            'sub_kategori' => explode('-', $data->detail->kategori_3)[1],
            'id_sub_kategori' => explode('-', $data->detail->kategori_3)[0],
            'sampler' => $data->created_by ?? '-',
            'nama_perusahaan' => $data->detail->nama_perusahaan ?? '-',
            'keterangan' => $data->keterangan ?? '-',
            'keterangan_2' => $data->keterangan_2 ?? '-',
            'latitude' => $data->latitude ?? '-',
            'longitude' => $data->longitude ?? '-',
            'parameter' => $data->parameter ?? '-',
            'kondisi_lapangan' => $data->kondisi_lapangan ?? '-',
            'lokasi' => $data->lokasi ?? '-',
            'jenis_pengukuran' => $data->jenis_pengukuran ?? '-',
            'waktu' => $data->waktu ?? '-',
            'shift' => $data->shift ?? '-',
            'suhu' => $data->suhu ?? '-',
            'kelembaban' => $data->kelembaban ?? '-',
            'tekanan_udara' => $data->tekanan_udara ?? '-',
            'pengukuran' => $data->pengukuran ?? '-',
            'titik_koordinat' => $data->titik_koordinat ?? '-',
            'foto_lokasi' => $data->foto_lokasi_sample ?? '-',
            'foto_lain' => $data->foto_lain ?? '-',
            'status' => '200'
        ], 200);
    }

    protected function autoBlock()
    {
        $tgl = Carbon::now()->subDays(7);
        $data = DataLapanganDirectLain::where('is_blocked', 0)->where('created_at', '<=', $tgl)->update(['is_blocked' => 1, 'blocked_by' => 'System', 'blocked_at' => Carbon::now()->format('Y-m-d H:i:s')]);
    }
}