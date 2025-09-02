<?php

namespace App\Http\Controllers\api;

use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Picqer\Barcode\BarcodeGeneratorPNG as Barcode;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;
use Datatables;
use Carbon\Carbon;
use App\Services\{Notification, GetAtasan};
use App\Services\SamplingPlanServices;
use App\Models\SamplingPlan;
use App\Models\QuotationNonKontrak;
use App\Models\QuotationKontrakH;
use App\Models\QuotationKontrakD;
use App\Models\ParameterAnalisa;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use App\Models\MasterKaryawan;
use App\Models\Jadwal;
use App\Models\HargaParameter;
use App\Models\FtcT;
use App\Models\Ftc;
use App\Http\Controllers\Controller;
use App\Helpers\WorkerOperation;
use App\Jobs\RenderSamplingPlan;

class ReadyOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            if ($request->mode == 'non_kontrak') {
                $data = QuotationNonKontrak::with([
                    'sales',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'konfirmasi'
                ])
                    ->where('id_cabang', $request->cabang)
                    ->where('flag_status', 'sp')
                    ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->where('is_ready_order', true)
                    ->where('konfirmasi_order', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            } else if ($request->mode == 'kontrak') {
                $data = QuotationKontrakH::with([
                    'sales',
                    'detail',
                    'sampling' => function ($q) {
                        $q->orderBy('periode_kontrak', 'asc');
                    },
                    'konfirmasi'
                ])
                    ->where('id_cabang', $request->cabang)
                    ->where('flag_status', 'sp')
                    ->where('is_active', true)
                    ->where('is_approved', true)
                    ->where('is_emailed', true)
                    ->where('is_ready_order', true)
                    ->where('konfirmasi_order', true)
                    ->whereYear('tanggal_penawaran', $request->year)
                    ->orderBy('tanggal_penawaran', 'desc');
            }

            $jabatan = $request->attributes->get('user')->karyawan->id_jabatan;
            switch ($jabatan) {
                case 24: // Sales Staff
                    $data->where('sales_id', $this->user_id);
                    break;
                case 21: // Sales Supervisor
                    $bawahan = MasterKaryawan::whereJsonContains('atasan_langsung', (string) $this->user_id)
                        ->pluck('id')
                        ->toArray();
                    array_push($bawahan, $this->user_id);
                    $data->whereIn('sales_id', $bawahan);
                    break;
            }

            return DataTables::of($data)
                ->addColumn('count_jadwal', function ($row) {
                    return $row->sampling ? $row->sampling->sum(function ($sampling) {
                        return $sampling->jadwal->count();
                    }) : 0;
                })
                ->addColumn('count_detail', function ($row) {
                    return $row->detail ? $row->detail->count() : 0;
                })
                ->filterColumn('data_lama', function ($query, $keyword) {
                    if (Str::contains($keyword, 'QS U')) {
                        $query->whereNotNull('data_lama')
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) IS NOT NULL")
                            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(data_lama, '$.no_order')) != 'null'");
                    }
                })
                ->make(true);
        } catch (Exception $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getDetailKontrak(Request $request)
    {
        if (!empty($request->id)) {
            $data = QuotationKontrakD::where('id_request_quotation_kontrak_h', $request->id)
                ->orderBy('periode_kontrak', 'asc')
                ->get();

            return response()->json(['data' => $data, 'status' => '200'], 200);
        } else {
            return response()->json(['message' => 'Data not found.!', 'status' => 401], 401);
        }
    }

    public function requestSamplingPlan(Request $request)
    {
        try {
            $dataArray = (object) [
                'no_quotation' => $request->no_quotation,
                'quotation_id' => $request->quotation_id,
                'tanggal_penawaran' => $request->tanggal_penawaran,
                'sampel_id' => $request->sampel_id,
                'tanggal_sampling' => $request->tanggal_sampling,
                'jam_sampling' => $request->jam_sampling,
                'is_sabtu' => $request->is_sabtu,
                'is_minggu' => $request->is_minggu,
                'is_malam' => $request->is_malam,
                'tambahan' => $request->tambahan,
                'keterangan_lain' => $request->keterangan_lain,
                'karyawan' => $this->karyawan
            ];
            if ($request->status_quotation == 'kontrak') {
                $dataArray->periode = $request->periode;
                $spServices = SamplingPlanServices::on('insertKontrak', $dataArray)->insertSPKontrak();
            } else {
                $spServices = SamplingPlanServices::on('insertNon', $dataArray)->insertSP();
            }

            if ($spServices) {
                $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
                $this->dispatch($job);

                return response()->json(['message' => 'Add Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $th) {
            return response()->json(['message' => 'Add Request Sampling Plan Failed: ' . $th->getMessage() . ' Line: ' . $th->getLine() . ' File: ' . $th->getFile() . '', 'status' => 401], 401);
        }
    }


    public function rescheduleSamplingPlan(Request $request)
    {
        try {
            $dataArray = (object) [
                "no_document" => $request->no_document,
                "no_quotation" => $request->no_quotation,
                "quotation_id" => $request->quotation_id,
                "karyawan" => $this->karyawan,
                "tanggal_sampling" => $request->tanggal_sampling,
                "jam_sampling" => $request->jam_sampling,
                "tambahan" => $request->tambahan,
                "keterangan_lain" => $request->keterangan_lain,
                "tanggal_penawaran" => $request->tanggal_penawaran,
                'is_sabtu' => $request->is_sabtu,
                'is_minggu' => $request->is_minggu,
                'is_malam' => $request->is_malam,
            ];

            if ($request->sample_id && $request->periode) {
                $dataArray->sample_id = $request->sample_id;
                $dataArray->periode = $request->periode;
                $spServices = SamplingPlanServices::on('insertSingleKontrak', $dataArray)->insertSPSingleKontrak();
            } else {
                $spServices = SamplingPlanServices::on('insertSingleNon', $dataArray)->insertSPSingle();
            }

            if ($spServices) {
                $job = new RenderSamplingPlan($request->quotation_id, $request->status_quotation);
                $this->dispatch($job);

                return response()->json(['message' => 'Reschedule Request Sampling Plan Success', 'status' => 200], 200);
            }
        } catch (Exception $e) {
            return response()->json(['message' => 'Reschedule Request Sampling Plan Failed: ' . $e->getMessage(), 'line' => $e->getLine(), 'file' => $e->getFile(), 'status' => 401], 401);
        }
    }

    public function reject(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }

                $data_lama = null;
                if ($data->data_lama != null)
                    $data_lama = json_decode($data->data_lama);

                if ($data_lama != null && $data_lama->no_order != null) {
                    $json = json_encode([
                        'id_qt' => $data_lama->id_qt,
                        'no_qt' => $data_lama->no_qt,
                        'no_order' => $data_lama->no_order,
                        'id_order' => $data_lama->id_order,
                        'status_sp' => (string) $request->perubahan_sp
                    ]);
                    $data->data_lama = $json;
                } else {
                    if ($data->flag_status == 'sp') {
                        $json = json_encode([
                            'id_qt' => $data->id,
                            'no_qt' => $data->no_document,
                            'no_order' => null,
                            'id_order' => null,
                            'status_sp' => (string) $request->perubahan_sp
                        ]);
                        $data->data_lama = $json;
                    }
                }

                $data->is_approved = false;
                $data->approved_by = null;
                $data->approved_at = null;
                $data->flag_status = 'rejected';
                $data->is_rejected = true;
                $data->rejected_by = $this->karyawan;
                $data->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->keterangan_reject = $request->keterangan_reject;
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Request Quotation number ' . $data->no_document . ' success rejected.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot rejected data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function voidQuotation(Request $request)
    {
        DB::beginTransaction();
        try {
            if (isset($request->id) || $request->id != '') {
                if ($request->mode == 'non_kontrak') {
                    $data = QuotationNonKontrak::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation';
                    if (count(json_decode($data->data_pendukung_sampling)) == 0) {
                        $data->is_ready_order = 1;
                    }
                } else if ($request->mode == 'kontrak') {
                    $data = QuotationKontrakH::where('id', $request->id)->where('is_active', true)->first();
                    $type_doc = 'quotation_kontrak';
                }
                $sampling_plan = SamplingPlan::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);
                $jadwal = Jadwal::where('no_quotation', $data->no_document)->where('is_active', true)->update(['is_active' => false]);

                $data->flag_status = 'void';
                $data->is_active = false;
                $data->document_status = 'Non Aktif';
                $data->deleted_by = $this->karyawan;
                $data->deleted_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();

                DB::commit();
                return response()->json([
                    'message' => 'Success void request Quotation number ' . $data->no_document . '.!',
                    'status' => '200'
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => 'Cannot void data.!',
                    'status' => '401'
                ], 401);
            }
        } catch (Exception $e) {
            DB::rollback();
            return response()->json([
                'message' => 'Error: ' . $e->getMessage(),
                'line' => $e->getLine(),
                'status' => '500'
            ], 500);
        }
    }

    public function writeOrder(Request $request)
    {
        try {
            if ($request->status_quotation == 'kontrak') {
                $prosess = self::generateOrderKontrak($request);
                $dataQuotation = QuotationKontrakH::where('no_document', $request->no_document)->where('is_active', true)->first();
                $message = "No. Penawaran : " . $request->no_document . " telah di order.";
                $sales = GetAtasan::where('id', $dataQuotation->sales_id)->get()->pluck('id');

                Notification::whereIn('id', $sales)->title('New Order')->message($message)->url('/qt-ordered')->send();
                return response()->json($prosess->getData(), $prosess->getStatusCode());
            } else {
                $prosess = $this->generateOrderNonKontrak($request);
                $dataQuotation = QuotationNonKontrak::where('no_document', $request->no_document)->where('is_active', true)->first();
                $message = "No. Penawaran : " . $request->no_document . " telah di order.";
                $sales = GetAtasan::where('id', $dataQuotation->sales_id)->get()->pluck('id');
                Notification::whereIn('id', $sales)->title('New Order')->message($message)->url('/qt-ordered')->send();
                return response()->json($prosess->getData(), $prosess->getStatusCode());
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Write Order Failed: ' . $th->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function generateOrderNonKontrak($request)
    {
        try {
            if (!$request->id) {
                return response()->json([
                    'message' => 'Data not found.!',
                    'status' => 401
                ], 401);
            }

            $dataQuotation = QuotationNonKontrak::with(['sales', 'sampling', 'pelanggan'])
                ->where('id', $request->id)
                ->first();

            if ($dataQuotation->pelanggan == null) {
                return response()->json([
                    'message' => 'ID Pelanggan not found.!',
                    'status' => 401
                ], 401);
            }

            //penentuan tahun berdasarkan penawaran
            $y = substr(explode('/', $dataQuotation->no_document)[2], 0, 2);

            $cek_order = OrderHeader::where('id_pelanggan', $dataQuotation->pelanggan_ID)
                ->where('no_document', 'like', '%' . $y . '-%')
                ->orderBy(DB::raw('CAST(SUBSTRING(no_order, 5) AS UNSIGNED)'), 'DESC')
                ->first();

            $id_pelanggan = $dataQuotation->pelanggan_ID;
            $no_urut = sprintf("%02d", 1);

            if ($cek_order != null) {
                $no_order_terakhir = $cek_order->no_order;
                $no_order_terakhir = \str_replace('R1', "", $no_order_terakhir);
                $no_order_terakhir = \str_replace($id_pelanggan, "", $no_order_terakhir);
                $no_order_terakhir = strlen($no_order_terakhir) > 4 ? substr($no_order_terakhir, -3) : substr($no_order_terakhir, -2);
                $no_urut = sprintf("%02d", (int) $no_order_terakhir + 1);
            }

            $no_order = $id_pelanggan . $y . $no_urut;
            // dd($id_pelanggan, $no_order);
            if (count(json_decode($dataQuotation->data_pendukung_sampling)) == 0) {
                /*
                    Generate order kusus untuk tanpa pengujian
                */
                return self::orderNonKontrakNonPengujian($dataQuotation, $no_order);
            } else {
                $dataJadwal = null;
                if ($dataQuotation->status_sampling != 'SD') {
                    $jadwalCollection = collect($dataQuotation->sampling->first()->jadwal ?? []);
                    $dataJadwal = $jadwalCollection->map(function ($item) {
                        return [
                            'tanggal' => $item->tanggal,
                            'kategori' => json_decode($item->kategori, true)
                        ];
                    })->groupBy('tanggal')->map(function ($items, $tanggal) {
                        return [
                            'tanggal' => $tanggal,
                            'kategori' => $items->pluck('kategori')->flatten()->unique()->values()->all()
                        ];
                    })->values()->all();

                    if ($dataJadwal == null) {
                        return response()->json([
                            'message' => 'No Quotation Belum terjadwal',
                            'status' => 401
                        ], 401);
                    }

                    $array_kategori_jadwal = [];
                    $tanggal_jadwal = [];
                    // $array_jadwal_kategori = [];
                    foreach ($dataJadwal as $key => $value) {
                        $kategori_jadwal = $value['kategori'];
                        // array_push($array_jadwal_kategori, $kategori_jadwal);
                        array_push($array_kategori_jadwal, (int) count($kategori_jadwal));
                        array_push($tanggal_jadwal, $value['tanggal']);
                    }
                    // $mergedArray = array_merge(
                    //     ...$array_jadwal_kategori // Menggunakan spread operator agar lebih fleksibel
                    // );

                    // // Hapus duplikat jika diperlukan
                    // $mergedArray = array_unique($mergedArray);

                    // usort($mergedArray, function ($a, $b) {
                    //     // Ambil angka dari akhir string
                    //     preg_match('/\d+$/', $a, $aMatch);
                    //     preg_match('/\d+$/', $b, $bMatch);

                    //     return (int)$aMatch[0] - (int)$bMatch[0];
                    // });

                    // // Dump hasil setelah diurutkan
                    // dd($mergedArray);
                    $total_kategori = (int) array_sum($array_kategori_jadwal);
                    $tanggal_jadwal = array_values(array_unique($tanggal_jadwal));

                    $array_jumlah_titik = [];
                    $jumlah_kategori = 0;

                    foreach (json_decode($dataQuotation->data_pendukung_sampling) as $key => $data_pengujian) {
                        $jumlah_kategori++;
                        array_push($array_jumlah_titik, (int) $data_pengujian->jumlah_titik);
                    }
                    // dd($array_jumlah_titik, $jumlah_kategori);
                    $total_titik = (int) array_sum($array_jumlah_titik);
                    // dd($total_titik, $total_kategori);
                    if ($total_titik > $total_kategori) {
                        return response()->json([
                            'message' => 'Terdapat perbedaan titik antara jadwal dan quotation, silahkan hubungi admin terkait untuk update jadwal.!',
                            'status' => 401
                        ], 401);
                    }
                }

                $data_lama = null;
                if ($dataQuotation->data_lama != null) {
                    $data_lama = json_decode($dataQuotation->data_lama);
                }

                if ($data_lama != null && $data_lama->no_order != null) {
                    /*
                        Jika data lama ada dan no order ada maka re-generate order
                    */
                    $no_order = $data_lama->no_order;
                    return self::reOrderNonKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama);
                } else {
                    /*
                        Jika data lama tidak ada atau no order tidak ada maka generate order
                    */
                    return self::orderNonKontrak($dataQuotation, $no_order, $dataJadwal);
                }
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Generate Order Non Kontrak Failed: ' . $th->getMessage() . ' on file ' . $th->getFile() . ' on line ' . $th->getLine(),
                'status' => 401
            ], 401);
        }
    }

    public function generateOrderKontrak($request)
    {
        try {
            if (!$request->id) {
                return response()->json([
                    'message' => 'Data not found.!',
                    'status' => 401
                ], 401);
            }

            $dataQuotation = QuotationKontrakH::with(['sales', 'sampling', 'pelanggan'])
                ->where('id', $request->id)
                ->first();

            if ($dataQuotation->pelanggan == null) {
                return response()->json([
                    'message' => 'ID Pelanggan not found.!',
                    'status' => 401
                ], 401);
            }

            //penentuan tahun berdasarkan penawaran
            $y = substr(explode('/', $dataQuotation->no_document)[2], 0, 2);

            $cek_order = OrderHeader::where('id_pelanggan', $dataQuotation->pelanggan_ID)
                ->where('no_document', 'like', '%' . $y . '-%')
                ->orderBy(DB::raw('CAST(SUBSTRING(no_order, 5) AS UNSIGNED)'), 'DESC')
                ->first();

            $id_pelanggan = $dataQuotation->pelanggan_ID;
            $no_urut = sprintf("%02d", 1);
            if ($cek_order != null) {
                $no_order_terakhir = $cek_order->no_order;
                $no_order_terakhir = \str_replace('R1', "", $no_order_terakhir);
                $no_order_terakhir = \str_replace($id_pelanggan, "", $no_order_terakhir);
                $no_order_terakhir = strlen($no_order_terakhir) > 4 ? substr($no_order_terakhir, -3) : substr($no_order_terakhir, -2);
                $no_urut = sprintf("%02d", (int) $no_order_terakhir + 1);
            }

            $no_order = $id_pelanggan . $y . $no_urut;

            if (count(json_decode($dataQuotation->data_pendukung_sampling)) == 0) {
                /*
                    Generate order kusus untuk tanpa pengujian
                */

                return response()->json([
                    'message' => 'Generate Order Kontrak Non Pengujian Belum dapat dilakukan.',
                    'status' => 200
                ], 200);
                // return self::orderNonKontrakNonPengujian($dataQuotation, $no_order);
            }
            $dataJadwal = [];
            if ($dataQuotation->status_sampling != 'SD') {
                $jadwalCollection = collect();
                foreach ($dataQuotation->sampling as $sampling) {
                    $periode = $sampling->periode_kontrak;
                    foreach ($sampling->jadwal as $jadwal) {
                        $jadwal->periode = $periode;
                        $jadwalCollection->push($jadwal);
                    }
                }
                // dd($jadwalCollection);
                $dataJadwal = $jadwalCollection->map(function ($item) {
                    $tanggal = $item->tanggal;
                    $periode = $item->periode;
                    return [
                        'periode_kontrak' => $periode,
                        'tanggal' => $tanggal,
                        'kategori' => json_decode($item->kategori, true)
                    ];
                })->groupBy('periode_kontrak')->map(function ($items, $periode) {
                    return [
                        'periode_kontrak' => $periode,
                        'jadwal' => $items->map(function ($item) {
                            return [
                                'tanggal' => $item['tanggal'],
                                'kategori' => $item['kategori']
                            ];
                        })->values()->all()
                    ];
                })->values()->all();

                $dataJadwal = collect($dataJadwal)->sortBy('periode_kontrak')->values()->all();
                // dd($dataJadwal);
                if ($dataJadwal == null) {
                    return response()->json([
                        'message' => 'No Quotation Belum terjadwal',
                        'status' => 401
                    ], 401);
                }

                // dump($dataJadwal);
                $array_kategori_jadwal = [];
                $tanggal_jadwal = [];
                foreach ($dataJadwal as $key => $value) {
                    foreach ($value['jadwal'] as $jadwal) {
                        $kategori_jadwal = $jadwal['kategori'];
                        // var_dump($kategori_jadwal);
                        array_push($array_kategori_jadwal, (int) count($kategori_jadwal));
                        array_push($tanggal_jadwal, $jadwal['tanggal']);
                    }
                }
                // dd($array_kategori_jadwal, $tanggal_jadwal);
                $total_kategori = (int) array_sum($array_kategori_jadwal);
                $tanggal_jadwal = array_values(array_unique($tanggal_jadwal));

                $array_jumlah_titik = [];
                $jumlah_kategori = 0;

                // dd($array_kategori_jadwal,$dataJadwal);
                foreach ($dataQuotation->detail as $detail) {
                    if ($detail->status_sampling == 'SD')
                        continue;
                    foreach (json_decode($detail->data_pendukung_sampling) as $data_pengujian) {
                        // dd($data_pengujian);
                        foreach ($data_pengujian->data_sampling as $data_sampling) {
                            // dump($data_sampling);
                            $jumlah_kategori++;
                            array_push($array_jumlah_titik, (int) $data_sampling->jumlah_titik);
                        }
                    }
                }

                $total_titik = (int) array_sum($array_jumlah_titik);
                // dd($total_titik, $total_kategori);
                if ($total_titik > $total_kategori) {
                    return response()->json([
                        'message' => 'Terdapat perbedaan titik antara jadwal dan quotation, silahkan hubungi admin terkait untuk update jadwal.!',
                        'status' => 401
                    ], 401);
                }
            }

            $data_lama = null;
            if ($dataQuotation->data_lama != null) {
                $data_lama = json_decode($dataQuotation->data_lama);
            }

            if ($data_lama != null && $data_lama->no_order != null) {
                /*
                    Jika data lama ada dan no order ada maka re-generate order
                */
                $no_order = $data_lama->no_order;
                return self::reOrderKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama);
            } else {
                /*
                    Jika data lama tidak ada atau no order tidak ada maka generate order
                */
                return self::orderKontrak($dataQuotation, $no_order, $dataJadwal);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Generate Order Kontrak Failed: ' . $th->getMessage() . ' in Line ' . $th->getLine(),
                'status' => 401
            ], 401);
        }
    }

    public function orderNonKontrakNonPengujian($dataQuotation, $no_order)
    {
        DB::beginTransaction();
        try {
            $data_lama = null;
            if ($dataQuotation->data_lama != null) {
                $data_lama = json_decode($dataQuotation->data_lama);
                if ($data_lama->no_order != null) {
                    $no_order = $data_lama->no_order;
                }
            }
            // dd($no_order);
            if ($data_lama != null && $data_lama->no_order != null) {
                OrderDetail::where('no_order', $no_order)->where('is_active', 1)->update(['is_active' => 0]);

                $data = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                $data->no_document = $dataQuotation->no_document;
                $data->id_pelanggan = $dataQuotation->pelanggan_ID;
                $data->flag_status = 'ordered';
                $data->is_revisi = 0;
                $data->id_cabang = $dataQuotation->id_cabang;
                $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $data->konsultan = $dataQuotation->konsultan;
                $data->alamat_kantor = $dataQuotation->alamat_kantor;
                $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                $data->nama_pic_order = $dataQuotation->nama_pic_order;
                $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                $data->no_pic_order = $dataQuotation->no_pic_order;
                $data->email_pic_order = $dataQuotation->email_pic_order;
                $data->alamat_sampling = $dataQuotation->alamat_sampling;
                $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
                $data->kategori_customer = $dataQuotation->kategori_customer;
                $data->sub_kategori = $dataQuotation->sub_kategori;
                $data->bahan_customer = $dataQuotation->bahan_customer;
                $data->merk_customer = $dataQuotation->merk_customer;
                $data->status_wilayah = $dataQuotation->status_wilayah;
                $data->total_ppn = $dataQuotation->total_ppn;
                $data->grand_total = $dataQuotation->grand_total;
                $data->total_dicount = $dataQuotation->total_dicount;
                $data->total_dpp = $dataQuotation->total_dpp;
                $data->piutang = $dataQuotation->piutang;
                $data->biaya_akhir = $dataQuotation->biaya_akhir;
                $data->wilayah = $dataQuotation->wilayah;
                $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                $data->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
                $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                $data->updated_by = $this->karyawan;
                $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                $data->save();
            } else {
                $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', 1)->first();
                if ($cek_no_qt != null) {
                    return response()->json([
                        'message' => 'No Quotation already Ordered.!',
                    ], 401);
                } else {
                    $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                    if ($cek_no_order != null) {
                        return response()->json([
                            'message' => 'No Order already Ordered.!',
                        ], 401);
                    }
                    $data = new OrderHeader;
                    $data->id_pelanggan = $dataQuotation->pelanggan->id_pelanggan;
                    $data->no_order = $no_order;
                    $data->no_quotation = $dataQuotation->no_quotation;
                    $data->no_document = $dataQuotation->no_document;
                    $data->flag_status = 'ordered';
                    $data->id_cabang = $dataQuotation->id_cabang;
                    $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $data->konsultan = $dataQuotation->konsultan;
                    $data->alamat_kantor = $dataQuotation->alamat_kantor;
                    $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                    $data->nama_pic_order = $dataQuotation->nama_pic_order;
                    $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                    $data->no_pic_order = $dataQuotation->no_pic_order;
                    $data->email_pic_order = $dataQuotation->email_pic_order;
                    $data->alamat_sampling = $dataQuotation->alamat_sampling;
                    $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                    $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                    $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                    $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                    $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
                    $data->kategori_customer = $dataQuotation->kategori_customer;
                    $data->sub_kategori = $dataQuotation->sub_kategori;
                    $data->bahan_customer = $dataQuotation->bahan_customer;
                    $data->merk_customer = $dataQuotation->merk_customer;
                    $data->status_wilayah = $dataQuotation->status_wilayah;
                    $data->total_ppn = $dataQuotation->total_ppn;
                    $data->grand_total = $dataQuotation->grand_total;
                    $data->total_dicount = $dataQuotation->total_dicount;
                    $data->total_dpp = $dataQuotation->total_dpp;
                    $data->piutang = $dataQuotation->piutang;
                    $data->biaya_akhir = $dataQuotation->biaya_akhir;
                    $data->wilayah = $dataQuotation->wilayah;
                    $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                    $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                    $data->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
                    $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                    $data->is_revisi = 0;
                    $data->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $data->created_by = $this->karyawan;
                    $data->save();
                }
            }

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            DB::commit();
            return response()->json([
                'message' => "Generate Order Non Kontrak $dataQuotation->no_document Non Pengujian Success",
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Generate Order Non Kontrak Non Pengujian Failed: Line ' . $th->getLine() . ' Message: ' . $th->getMessage(),
                'status' => 401
            ], 401);
        }
    }

    public function orderNonKontrak($dataQuotation, $no_order, $dataJadwal)
    {
        DB::beginTransaction();
        try {
            $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', 1)->first();
            if ($cek_no_qt != null) {
                throw new Exception("No Quotation already Ordered.!", 401);
            }

            $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
            if ($cek_no_order != null) {
                throw new Exception("No Order $no_order already Ordered.!", 401);
            }

            $generator = new Barcode();

            $dataOrderHeader = new OrderHeader;
            $dataOrderHeader->id_pelanggan = $dataQuotation->pelanggan_ID;
            $dataOrderHeader->no_order = $no_order;
            $dataOrderHeader->no_quotation = $dataQuotation->no_quotation;
            $dataOrderHeader->no_document = $dataQuotation->no_document;
            $dataOrderHeader->flag_status = 'ordered';
            $dataOrderHeader->id_cabang = $dataQuotation->id_cabang;
            $dataOrderHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $dataOrderHeader->konsultan = $dataQuotation->konsultan;
            $dataOrderHeader->alamat_kantor = $dataQuotation->alamat_kantor;
            $dataOrderHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $dataOrderHeader->nama_pic_order = $dataQuotation->nama_pic_order;
            $dataOrderHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $dataOrderHeader->no_pic_order = $dataQuotation->no_pic_order;
            $dataOrderHeader->email_pic_order = $dataQuotation->email_pic_order;
            $dataOrderHeader->alamat_sampling = $dataQuotation->alamat_sampling;
            $dataOrderHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $dataOrderHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $dataOrderHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $dataOrderHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $dataOrderHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $dataOrderHeader->kategori_customer = $dataQuotation->kategori_customer;
            $dataOrderHeader->sub_kategori = $dataQuotation->sub_kategori;
            $dataOrderHeader->bahan_customer = $dataQuotation->bahan_customer;
            $dataOrderHeader->merk_customer = $dataQuotation->merk_customer;
            $dataOrderHeader->status_wilayah = $dataQuotation->status_wilayah;
            $dataOrderHeader->total_ppn = $dataQuotation->total_ppn;
            $dataOrderHeader->grand_total = $dataQuotation->grand_total;
            $dataOrderHeader->total_dicount = $dataQuotation->total_dicount;
            $dataOrderHeader->total_dpp = $dataQuotation->total_dpp;
            $dataOrderHeader->piutang = $dataQuotation->piutang;
            $dataOrderHeader->biaya_akhir = $dataQuotation->biaya_akhir;
            $dataOrderHeader->wilayah = $dataQuotation->wilayah;
            $dataOrderHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $dataOrderHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $dataOrderHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $dataOrderHeader->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
            $dataOrderHeader->is_revisi = 0;
            $dataOrderHeader->created_at = Carbon::now()->format('Y-m-d H:i:s');
            $dataOrderHeader->created_by = $this->karyawan;
            $dataOrderHeader->save();

            $n = 1;
            $no = 0;
            $kategori = '';
            $parameter = [];
            $regulasi = [];
            $DataPendukungSampling = json_decode($dataQuotation->data_pendukung_sampling);
            foreach ($DataPendukungSampling as $key => $value) {
                // =================================================================
                foreach ($value->penamaan_titik as $pt) {
                    $props = get_object_vars($pt);
                    $noSampel = key($props);
                    // $value = $props[$key];

                    $no_sample = $no_order . '/' . $noSampel;
                    /*
                     * Disini bagian pembuatan no sample dan no cfr/lhp
                     * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                     * Jika kategori sama maka no akan di increment
                     * Jika kategori tidak sama maka no akan di reset menjadi 0
                     * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                     */

                    // Menggunakan array_map untuk mengekstrak nama parameter dan cek Ergonomi
                    $parameterNames = array_map(function ($param) {
                        $parts = explode(';', $param);
                        return isset($parts[1]) ? $parts[1] : '';
                    }, $value->parameter);

                    if ($value->kategori_1 == '1-Air') {
                        $no++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "11-Udara Ambient"){
                        $no++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "27-Udara Lingkungan Kerja") {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no++;
                        } else {
                            if(count($value->parameter) == 1 && $parameter == $value->parameter && $regulasi == $value->regulasi && $this->cekParamDirect($value->parameter)) {

                            } else {
                                $no++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                    } else if ($value->kategori_1 == '5-Emisi' && in_array($value->kategori_2, ['31-Emisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'])) {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no++;
                        } else {
                            if (
                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) != count($value->parameter)) ||
                                ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                            ) {
                                $no++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                    } else {
                        if (count($value->parameter) == 1) {
                            if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi) || in_array('Ergonomi', $parameterNames) || in_array('Ergonomi (GS-LK)', $parameterNames) || in_array('Ergonomi (GO-LK)', $parameterNames)) {
                                $no++;
                            } else {
                                if (
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) > 1) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi)) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) == count($value->parameter) && json_encode($parameter) != json_encode($value->parameter))
                                ) {
                                    $no++;
                                }
                            }
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                        } else {
                            $no++;
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                        }
                    }

                    $rand_str = strtoupper(md5($no_sample));
                    for ($i = 1; $i <= 5; $i++) {
                        $no_sampling = self::randomstr($rand_str);
                        $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                        if ($cek_no_sampling == null) {
                            break;
                        }
                    }

                    $number_imaginer = sprintf("%03d", $n);

                    $tanggal_sampling = Carbon::now()->format('Y-m-d');
                    if ($dataQuotation->status_sampling != 'SD') {
                        foreach ($dataJadwal as $jadwal) {
                            foreach ($jadwal['kategori'] as $index => $kat) {
                                if (\explode(' - ', $kat)[1] == $number_imaginer) {
                                    $tanggal_sampling = $jadwal['tanggal'];
                                    break 2;
                                }
                            }
                        }
                    }

                    // $penamaan_titik = $value->penamaan_titik;
                    // if (is_array($penamaan_titik)) {
                    //     $penamaan_titik = isset($value->penamaan_titik[$f]) ? $value->penamaan_titik[$f] : '';
                    // }
                    // $props = get_object_vars($penamaan_titik);
                    $penamaan_titik = $props[$noSampel];

                    $DataOrderDetail = new OrderDetail;
                    $DataOrderDetail->id_order_header = $dataOrderHeader->id;
                    $DataOrderDetail->no_order = $dataOrderHeader->no_order;
                    $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                    $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                    $DataOrderDetail->no_sampel = $no_sample;
                    $DataOrderDetail->koding_sampling = $no_sampling;
                    $DataOrderDetail->kontrak = 'N';
                    $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                    $DataOrderDetail->kategori_1 = $dataQuotation->status_sampling;
                    $DataOrderDetail->kategori_2 = $value->kategori_1;
                    $DataOrderDetail->kategori_3 = $value->kategori_2;
                    $DataOrderDetail->cfr = $no_cfr;
                    $DataOrderDetail->keterangan_1 = $penamaan_titik;
                    $DataOrderDetail->parameter = json_encode($value->parameter);
                    $DataOrderDetail->regulasi = !empty($value->regulasi) ? json_encode($value->regulasi) : json_encode([]);
                    $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $DataOrderDetail->created_by = $this->karyawan;
                    $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                    $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                    // =================================================================

                    if (!file_exists(public_path() . '/barcode/sampling')) {
                        mkdir(public_path() . '/barcode/sampling', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));
                    // $qr_sampling = $this->generateQR($no_sampling, '/barcode/sampling');

                    if (!file_exists(public_path() . '/barcode/sample')) {
                        mkdir(public_path() . '/barcode/sample', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));
                    // $qr_sample = $this->generateQR($no_sample, '/barcode/sample');

                    if (explode("-", $value->kategori_1)[1] == 'Air') {

                        $parameter_names = array_map(function ($p) {
                            return explode(';', $p)[1];
                        }, $value->parameter);

                        $id_kategori = explode("-", $value->kategori_1)[0];

                        $params = HargaParameter::where('id_kategori', $id_kategori)
                            ->where('is_active', true)
                            ->whereIn('nama_parameter', $parameter_names)
                            ->get();

                        $param_map = [];
                        foreach ($params as $param) {
                            $param_map[$param->nama_parameter] = $param;
                        }

                        $botol_volumes = [];
                        foreach ($value->parameter as $parameter) {
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
                            // if($type == ''){
                            //     continue; // Skip if type is empty
                            // }
                            $koding = $no_sampling . strtoupper(Str::random(5));
                            // Hitung jumlah botol yang dibutuhkan
                            $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                            $botol[] = (object) [
                                'koding' => $koding,
                                'type_botol' => $type,
                                'volume' => $volume,
                                'file' => $koding . '.png',
                                'disiapkan' => $jumlah_botol
                            ];

                            if (!file_exists(public_path() . '/barcode/botol')) {
                                mkdir(public_path() . '/barcode/botol', 0777, true);
                            }

                            // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                            $this->generateQR($koding, '/barcode/botol');
                        }

                        $DataOrderDetail->persiapan = json_encode($botol);
                    } else {
                        /*
                         * Jika kategori bukan air maka tidak perlu membuat botol
                         * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                         */
                        if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                            $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                ->whereIn('parameter', $value->parameter)
                                ->where('is_active', true)
                                ->get();
                            $persiapan = []; // Pastikan inisialisasi array sebelum digunakan
                            foreach ($cek_ketentuan_parameter as $ketentuan) {
                                $koding = $no_sampling . strtoupper(Str::random(5));
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
                                $this->generateQR($koding, '/barcode/penjerap');
                            }
                            //2025-03-01 18:28
                            $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                        }
                    }

                    $DataOrderDetail->save();

                    Ftc::create([
                        'no_sample' => $no_sample
                    ]);

                    FtcT::create([
                        'no_sample' => $no_sample
                    ]);

                    foreach ($value->parameter as $v) {
                        $insert_analisa[] = [
                            'no_order' => $no_order,
                            'no_sampel' => $no_sample,
                            'tanggal_order' => $dataOrderHeader->tanggal_order,
                            'parameter' => $v
                        ];
                    }

                    ParameterAnalisa::insert($insert_analisa);

                    $n++;
                    $kategori = $value->kategori_2;
                    $regulasi = $value->regulasi;
                    $parameter = $value->parameter;
                } //Penutup Foreach
            } //Penutup For each
            // dd('Generate Order Non Kontrak Success');
            // dd($dataQuotation->sampling->isNotEmpty());
            if ($dataQuotation->sampling->isNotEmpty()) {
                foreach ($dataQuotation->sampling->first()->jadwal as $jadwal) {
                    $jadwal->status = 1;
                    $jadwal->save();
                }
            }

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();
            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            DB::commit();

            return response()->json([
                'message' => 'Generate Order Non Kontrak Success',
                'status' => 200
            ], 200);
        } catch (\Throwable $th) {
            DB::rollback();
            dd($th);
            throw new Exception($th->getMessage() . ' in line ' . $th->getLine(), 401);
        }
    }

    public function reOrderNonKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama)
    {
        DB::beginTransaction();
        try {
            $generator = new Barcode();
            $data_detail_lama = OrderDetail::where('no_order', $data_lama->no_order)->get();

            OrderDetail::where('no_order', $no_order)->update([
                'nama_perusahaan' => $dataQuotation->nama_perusahaan,
                'alamat_perusahaan' => $dataQuotation->alamat_kantor,
                'no_quotation' => $dataQuotation->no_document
            ]);

            $sampel_order_lama = $data_detail_lama->pluck('no_sampel')->toArray();
            $dps_details = json_decode($dataQuotation->data_pendukung_sampling, true);

            $sampel_detail_baru = [];
            $detail_baru = [];
            foreach ($dps_details as $dps) {
                // $penamaan_titik = array_merge(...$dps["penamaan_titik"]);
                $penamaan_titik = [];
                foreach ($dps["penamaan_titik"] as $titik) {
                    foreach ($titik as $kode => $nama) {
                        $penamaan_titik[$kode] = $nama;
                    }
                }
                $temp = array_keys($penamaan_titik);
                $sampel_detail_baru[] = $temp;
                // dd($dps, $dps, $temp, $penamaan_titik);
                foreach ($temp as $t) {
                    $detail_baru[$no_order . "/" . $t] = [
                        // "periode_kontrak" => $dps["periode_kontrak"],
                        "status_sampling" => $dataQuotation->status_sampling,
                        "kategori_1" => $dps["kategori_1"],
                        "kategori_2" => $dps["kategori_2"],
                        "regulasi" => $dps["regulasi"],
                        "parameter" => $dps["parameter"],
                        "penamaan_titik" => $penamaan_titik[$t]
                    ];
                }
            }

            $sampel_detail_baru = array_merge(...$sampel_detail_baru);
            $sampel_detail_baru = array_map(function ($item) use ($no_order) {
                return $no_order . "/" . $item;
            }, $sampel_detail_baru);
            sort($sampel_detail_baru);
            // dd($sampel_detail_baru, $detail_baru);

            $perubahan_data = array_values(array_intersect($sampel_order_lama, $sampel_detail_baru));
            $pengurangan_data = array_values(array_diff($sampel_order_lama, $sampel_detail_baru));
            $penambahan_data = array_values(array_diff($sampel_detail_baru, $sampel_order_lama));

            // dd($perubahan_data, $pengurangan_data, $penambahan_data);

            if (!empty($perubahan_data)) {
                foreach ($perubahan_data as $changes) {
                    $existing_detail = OrderDetail::where('no_order', $data_lama->no_order)
                        ->where('no_sampel', $changes)
                        ->where('is_active', 1)
                        ->first();
                    if ($existing_detail) {
                        //  ============ insert detail history ===============
                        $search_kategori = '%' . \explode('-', $detail_baru[$changes]["kategori_2"])[1] . ' - ' . substr($changes, -3) . '%';
                        $cek_jadwal = Jadwal::where('no_quotation', $dataQuotation->no_document)
                            ->where('is_active', 1)
                            ->where('kategori', 'like', $search_kategori)
                            ->select('tanggal', 'kategori')
                            ->groupBy('tanggal', 'kategori')
                            ->first();

                        // $existing_detail->periode = $detail_baru[$changes]["periode_kontrak"];
                        $existing_detail->kategori_2 = $detail_baru[$changes]["kategori_1"];
                        $existing_detail->kategori_3 = $detail_baru[$changes]["kategori_2"];
                        $existing_detail->keterangan_1 = $detail_baru[$changes]["penamaan_titik"];
                        $existing_detail->parameter = json_encode($detail_baru[$changes]["parameter"]);
                        $existing_detail->regulasi = json_encode($detail_baru[$changes]["regulasi"]);
                        if (isset($cek_jadwal->tanggal))
                            $existing_detail->tanggal_sampling = $cek_jadwal->tanggal;
                        $existing_detail->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                        $existing_detail->save();
                    } else {
                        array_push($penambahan_data, $changes);
                        DB::rollback();
                        return response()->json([
                            'status' => 'failed',
                            'message' => "Ditemukan inkonsistensi data pada sistem. Mohon hubungi tim IT untuk pemeriksaan lebih lanjut.",
                        ], 401);
                    }
                }
            }

            if (!empty($pengurangan_data)) {
                $data = OrderDetail::where('no_order', $no_order)
                    ->whereIn('no_sampel', $pengurangan_data)
                    ->update(['is_active' => 0]);

                Ftc::whereIn('no_sample', $pengurangan_data)->update(['is_active' => 0]);
                FtcT::whereIn('no_sample', $pengurangan_data)->update(['is_active' => 0]);
            }

            $n = 0;
            $no_urut_cfr = 0;
            $mark = [];
            if (!empty($penambahan_data) != null) {
                // Add data
                $cek_detail = OrderDetail::where('id_order_header', $data_lama->id_order)
                    // ->where('active', 0)
                    ->orderByDesc('no_sampel')
                    ->first();

                $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
                $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
                $n = $no_urut_sample + 1;
                $trigger = 0;
                $kategori = $cek_detail->kategori_3;
                $regulasi = json_decode($cek_detail->regulasi) ?? [];
                $parameter = json_decode($cek_detail->parameter) ?? [];
                foreach ($penambahan_data as $key => $changes) {
                    $value = (object) $detail_baru[$changes];

                    // dd($value->parameter);
                    $no_sample = $changes;
                    /*
                     * Disini bagian pembuatan no sample dan no cfr/lhp
                     * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                     * Jika kategori sama maka no akan di increment
                     * Jika kategori tidak sama maka no akan di reset menjadi 0
                     * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                     */

                    // Menggunakan array_map untuk mengekstrak nama parameter dan cek Ergonomi
                    $parameterNames = array_map(function ($param) {
                        $parts = explode(';', $param);
                        return isset($parts[1]) ? $parts[1] : '';
                    }, $value->parameter);

                    if ($value->kategori_1 == '1-Air') {
                        $no_urut_cfr++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "11-Udara Ambient"){
                        $no_urut_cfr++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "27-Udara Lingkungan Kerja") {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no_urut_cfr++;
                        } else {
                            if(count($value->parameter) == 1 && $parameter == $value->parameter && json_encode($regulasi) == json_encode($value->regulasi) && $this->cekParamDirect($value->parameter)) {

                            } else {
                                $no_urut_cfr++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == '5-Emisi' && in_array($value->kategori_2, ['31-Emisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'])) {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no_urut_cfr++;
                        } else {
                            if (
                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) != count($value->parameter)) ||
                                ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                            ) {
                                $no_urut_cfr++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else {
                        if (count($value->parameter) == 1) {
                            if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi) || in_array('Ergonomi', $parameterNames) || in_array('Ergonomi (GS-LK)', $parameterNames) || in_array('Ergonomi (GO-LK)', $parameterNames)) {
                                if (
                                    $cek_detail->kategori_3 != $value->kategori_2 ||
                                    $cek_detail->regulasi != json_encode($value->regulasi) ||
                                    $cek_detail->parameter != json_encode($value->parameter)
                                ) {
                                    $no_urut_cfr++;
                                }

                                if (in_array('Ergonomi', $parameterNames)) {
                                    $no_urut_cfr++;
                                }
                            } else {
                                if (
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) > 1) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi)) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) == count($value->parameter) && json_encode($parameter) != json_encode($value->parameter))
                                ) {
                                    $no_urut_cfr++;
                                }
                            }
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        } else {
                            $no_urut_cfr++;
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        }
                    }

                    $rand_str = strtoupper(md5($no_sample));
                    for ($i = 1; $i <= 5; $i++) {
                        $no_sampling = self::randomstr($rand_str);
                        $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                        if ($cek_no_sampling == null) {
                            break;
                        }
                    }

                    $number_imaginer = sprintf("%03d", $n);
                    $tanggal_sampling = Carbon::now()->format('Y-m-d');
                    if ($dataQuotation->status_sampling != 'SD') {
                        $mark[] = $number_imaginer;
                        foreach ($dataJadwal as $jadwal) {
                            foreach ($jadwal['kategori'] as $index => $kat) {
                                if (\explode(' - ', $kat)[1] == $number_imaginer) {
                                    $tanggal_sampling = $jadwal['tanggal'];
                                    $key = array_search($number_imaginer, $mark);
                                    unset($mark[$key]);
                                    break 2;
                                }
                            }
                        }
                    }
                    // dd($mark);
                    $penamaan_titik = $value->penamaan_titik;

                    // =================================================================
                    $dataD = new OrderDetail;
                    $dataD->id_order_header = $data_lama->id_order;
                    $dataD->no_order = $no_order;
                    $dataD->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $dataD->alamat_perusahaan = $dataQuotation->alamat_kantor;
                    $dataD->no_quotation = $dataQuotation->no_document;
                    $dataD->no_sampel = $no_sample;
                    $dataD->koding_sampling = $no_sampling;
                    $dataD->kontrak = 'N';
                    $dataD->tanggal_sampling = $tanggal_sampling; //belum di set
                    $dataD->kategori_1 = $value->status_sampling;
                    $dataD->kategori_2 = $value->kategori_1;
                    $dataD->kategori_3 = $value->kategori_2;
                    $dataD->cfr = $no_cfr;
                    $dataD->keterangan_1 = $penamaan_titik;
                    $dataD->parameter = json_encode($value->parameter);
                    $dataD->regulasi = json_encode($value->regulasi);
                    $dataD->regulasi = !empty($value->regulasi) ? json_encode($value->regulasi) : json_encode([]);
                    $dataD->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $dataD->created_by = $this->karyawan;
                    $dataD->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                    $dataD->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                    // =================================================================
                    if (!file_exists(public_path() . '/barcode/sampling')) {
                        mkdir(public_path() . '/barcode/sampling', 0777, true);
                    }
                    if (!file_exists(public_path() . '/barcode/sample')) {
                        mkdir(public_path() . '/barcode/sample', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));
                    file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                    // =================================================================
                    if (explode("-", $value->kategori_1)[1] == 'Air') {
                        $parameter_names = array_map(function ($p) {
                            return explode(';', $p)[1];
                        }, $value->parameter);

                        $id_kategori = explode("-", $value->kategori_1)[0];
                        $params = HargaParameter::where('id_kategori', $id_kategori)
                            ->where('is_active', true)
                            ->whereIn('nama_parameter', $parameter_names)
                            ->get();

                        $param_map = [];
                        foreach ($params as $param) {
                            $param_map[$param->nama_parameter] = $param;
                        }

                        $botol_volumes = [];
                        foreach ($value->parameter as $parameter) {
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
                            $koding = $no_sampling . strtoupper(Str::random(5));
                            $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                            $botol[] = (object) [
                                'koding' => $koding,
                                'type_botol' => $type,
                                'volume' => $volume,
                                'file' => $koding . '.png',
                                'disiapkan' => $jumlah_botol
                            ];

                            if (!file_exists(public_path() . '/barcode/botol')) {
                                mkdir(public_path() . '/barcode/botol', 0777, true);
                            }

                            // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                            $this->generateQR($koding, '/barcode/botol');
                        }

                        $dataD->persiapan = json_encode($botol);
                    } else {
                        /*
                         * Jika kategori bukan air maka tidak perlu membuat botol
                         * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                         */
                        if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                            $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                ->whereIn('parameter', $value->parameter)
                                ->where('is_active', true)
                                ->get();
                            $persiapan = []; // Pastikan inisialisasi array sebelum digunakan
                            foreach ($cek_ketentuan_parameter as $ketentuan) {
                                $koding = $no_sampling . strtoupper(Str::random(5));
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
                                $this->generateQR($koding, '/barcode/penjerap');
                            }

                            $dataD->persiapan = json_encode($persiapan ?? []);
                        }
                    }

                    // =================================================================
                    $dataD->save();
                    Ftc::create([
                        'no_sample' => $no_sample
                    ]);

                    FtcT::create([
                        'no_sample' => $no_sample
                    ]);

                    // ======================================================================================= INSERT TO TABLE PARAMETER ANALISA ===================================================================================
                    foreach ($value->parameter as $v) {
                        $insert_analisa[] = [
                            'no_order' => $no_order,
                            'no_sampel' => $no_sample,
                            'tanggal_order' => Carbon::now()->format('Y-m-d'),
                            'parameter' => $v
                        ];
                    }

                    ParameterAnalisa::insert($insert_analisa);

                    $n++;
                    $kategori = $value->kategori_2;
                    $regulasi = $value->regulasi;
                    $parameter = $value->parameter;
                }
                // dd($mark);
                if (!empty($mark)) {
                    DB::rollBack();
                    Log::channel('reorder')->info("Terdapat kategori yang belum terjadwal pada", [
                        "no_quotation" => $dataQuotation->no_document,
                        "kategori" => implode(", ", $mark),
                    ]);
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Terdapat entri jadwal sampling yang belum di-update. Harap koordinasi dengan admin sampling untuk melakukan pembaruan data jadwal tersebut.',
                    ], 401);
                }
            }

            $data = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
            $data->no_document = $dataQuotation->no_document;
            $data->id_pelanggan = $dataQuotation->pelanggan_ID;
            $data->flag_status = 'ordered';
            $data->is_revisi = 0;
            $data->id_cabang = $dataQuotation->id_cabang;
            $data->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $data->konsultan = $dataQuotation->konsultan;
            $data->alamat_kantor = $dataQuotation->alamat_kantor;
            $data->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $data->nama_pic_order = $dataQuotation->nama_pic_order;
            $data->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $data->no_pic_order = $dataQuotation->no_pic_order;
            $data->email_pic_order = $dataQuotation->email_pic_order;
            $data->alamat_sampling = $dataQuotation->alamat_sampling;
            $data->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $data->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $data->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $data->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $data->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $data->kategori_customer = $dataQuotation->kategori_customer;
            $data->sub_kategori = $dataQuotation->sub_kategori;
            $data->bahan_customer = $dataQuotation->bahan_customer;
            $data->merk_customer = $dataQuotation->merk_customer;
            $data->status_wilayah = $dataQuotation->status_wilayah;
            $data->total_ppn = $dataQuotation->total_ppn;
            $data->grand_total = $dataQuotation->grand_total;
            $data->total_dicount = $dataQuotation->total_dicount;
            $data->total_dpp = $dataQuotation->total_dpp;
            $data->piutang = $dataQuotation->piutang;
            $data->biaya_akhir = $dataQuotation->biaya_akhir;
            $data->wilayah = $dataQuotation->wilayah;
            $data->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $data->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $data->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $data->tanggal_order = Carbon::now()->format('Y-m-d');
            $data->updated_by = $this->karyawan;
            $data->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $data->save();

            //update general order detail
            OrderDetail::where('no_order', $no_order)
                ->update([
                    'nama_perusahaan' => $dataQuotation->nama_perusahaan,
                    'alamat_perusahaan' => $dataQuotation->alamat_kantor,
                    'no_quotation' => $dataQuotation->no_document
                ]);

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            $data_detail_baru = OrderDetail::where('no_order', $no_order)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'periode', 'tanggal_sampling', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $data_to_log = [
                'data_lama' => $data_detail_lama->toArray(),
                'data_baru' => $data_detail_baru->toArray()
            ];

            $excludes_bcc = ['kharina@intilab.com', 'abidah@intilab.com', 'sucita@intilab.com'];
            $bcc = GetAtasan::where('user_id', 54)->get()->pluck('email')->toArray();
            $bcc = array_filter($bcc, function ($item) use ($excludes_bcc) {
                return !in_array($item, $excludes_bcc);
            });

            // $workerOperation = new WorkerOperation();
            // $workerOperation->index($data, $data_to_log, $bcc, $this->user_id);
            // dd('stop');
            DB::commit();
            return response()->json([
                'message' => 'Re-Order Non Kontrak Success',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }


    public function orderKontrak($dataQuotation, $no_order, $dataJadwal)
    {
        $generator = new Barcode();
        DB::beginTransaction();
        try {
            $cek_no_qt = OrderHeader::where('no_document', $dataQuotation->no_document)->where('is_active', true)->first();
            if ($cek_no_qt != null) {
                return response()->json([
                    'message' => 'No Quotation already Ordered.!',
                ], 401);
            } else {
                $cek_no_order = OrderHeader::where('no_order', $no_order)->where('is_active', 1)->first();
                if ($cek_no_order != null) {
                    return response()->json([
                        'message' => 'No Order already Ordered.!',
                    ], 401);
                }

                $dataOrderHeader = new OrderHeader;
                $dataOrderHeader->id_pelanggan = $dataQuotation->pelanggan_ID;
                $dataOrderHeader->no_order = $no_order;
                $dataOrderHeader->no_quotation = $dataQuotation->no_quotation;
                $dataOrderHeader->no_document = $dataQuotation->no_document;
                $dataOrderHeader->flag_status = 'ordered';
                $dataOrderHeader->id_cabang = $dataQuotation->id_cabang;
                $dataOrderHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
                $dataOrderHeader->konsultan = $dataQuotation->konsultan;
                $dataOrderHeader->alamat_kantor = $dataQuotation->alamat_kantor;
                $dataOrderHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
                $dataOrderHeader->nama_pic_order = $dataQuotation->nama_pic_order;
                $dataOrderHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
                $dataOrderHeader->no_pic_order = $dataQuotation->no_pic_order;
                $dataOrderHeader->email_pic_order = $dataQuotation->email_pic_order;
                $dataOrderHeader->alamat_sampling = $dataQuotation->alamat_sampling;
                $dataOrderHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
                $dataOrderHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
                $dataOrderHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
                $dataOrderHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
                $dataOrderHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
                $dataOrderHeader->kategori_customer = $dataQuotation->kategori_customer;
                $dataOrderHeader->sub_kategori = $dataQuotation->sub_kategori;
                $dataOrderHeader->bahan_customer = $dataQuotation->bahan_customer;
                $dataOrderHeader->merk_customer = $dataQuotation->merk_customer;
                $dataOrderHeader->status_wilayah = $dataQuotation->status_wilayah;
                $dataOrderHeader->total_ppn = $dataQuotation->total_ppn;
                $dataOrderHeader->grand_total = $dataQuotation->grand_total;
                $dataOrderHeader->total_dicount = $dataQuotation->total_dicount;
                $dataOrderHeader->total_dpp = $dataQuotation->total_dpp;
                $dataOrderHeader->piutang = $dataQuotation->piutang;
                $dataOrderHeader->biaya_akhir = $dataQuotation->biaya_akhir;
                $dataOrderHeader->wilayah = $dataQuotation->wilayah;
                $dataOrderHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
                $dataOrderHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
                $dataOrderHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
                $dataOrderHeader->tanggal_order = Carbon::now()->format('Y-m-d');
                $dataOrderHeader->created_at = Carbon::now()->format('Y-m-d H:i:s');
                $dataOrderHeader->created_by = $this->karyawan;
                $dataOrderHeader->save();

                $n = 1;
                $no = 0;

                $kategori = '';
                $regulasi = [];
                $parameter = [];
                $detail = $dataQuotation->detail()->orderBy('periode_kontrak', 'asc')->get();

                $oldPeriode = '';
                foreach ($detail as $k => $t) {
                    $periode_kontrak = $t->periode_kontrak;
                    $tanggal_sampling = $periode_kontrak . '-01';
                    $sampling_plan = $dataJadwal;

                    foreach (json_decode($t->data_pendukung_sampling) as $ky => $val) {
                        // each mencari data pendukung sampling per periode
                        $DataPendukungSampling = is_array($val->data_sampling) ? $val->data_sampling : json_decode($val->data_sampling);
                        foreach ($DataPendukungSampling as $key => $value) {
                            // each mencari data sampling per detail
                            foreach ($value->penamaan_titik as $pt) {
                                // =================================================================
                                $props = get_object_vars($pt);
                                $noSampel = key($props);
                                // $value = $props[$key];

                                $no_sample = $no_order . '/' . $noSampel;
                                /*
                                 * Disini bagian pembuatan no sample dan no cfr/lhp
                                 * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                                 * Jika kategori sama maka no akan di increment
                                 * Jika kategori tidak sama maka no akan di reset menjadi 0
                                 * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                                 */

                                // Menggunakan array_map untuk mengekstrak nama parameter dan cek Ergonomi
                                $parameterNames = array_map(function ($param) {
                                    $parts = explode(';', $param);
                                    return isset($parts[1]) ? $parts[1] : '';
                                }, $value->parameter);

                                if ($value->kategori_1 == '1-Air') {
                                    $no++;
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "11-Udara Ambient"){
                                    $no++;
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "27-Udara Lingkungan Kerja") {
                                    if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                        $no++;
                                    } else {
                                        if(count($value->parameter) == 1 && $parameter == $value->parameter && $regulasi == $value->regulasi && $this->cekParamDirect($value->parameter)) {
            
                                        } else {
                                            $no++;
                                        }
                                    }
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                } else if ($value->kategori_1 == '5-Emisi' && in_array($value->kategori_2, ['31-Emisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'])) {
                                    if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                                        $no++;
                                    } else {
                                        if (
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) != count($value->parameter)) ||
                                            ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                                        ) {
                                            $no++;
                                        }
                                    }
                                    $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                } else {
                                    if (count($value->parameter) == 1) {
                                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi) || in_array('Ergonomi', $parameterNames) || in_array('Ergonomi (GS-LK)', $parameterNames) || in_array('Ergonomi (GO-LK)', $parameterNames)) {
                                            $no++;
                                        } else {
                                            if (
                                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) > 1) ||
                                                ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi)) ||
                                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) == count($value->parameter) && json_encode($parameter) != json_encode($value->parameter))
                                            ) {
                                                $no++;
                                            }
                                        }
                                        if ($oldPeriode != '' && $oldPeriode != $periode_kontrak) {
                                            $no++;
                                        }
                                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                    } else {
                                        $no++;
                                        $no_cfr = $no_order . '/' . sprintf("%03d", $no);
                                    }
                                }

                                $rand_str = strtoupper(md5($no_sample));
                                for ($i = 1; $i <= 5; $i++) {
                                    $no_sampling = self::randomstr($rand_str);
                                    $cek_no_sampling = OrderDetail::where('koding_sampling', $no_sampling)->first();
                                    if ($cek_no_sampling == null) {
                                        break;
                                    }
                                }

                                $number_imaginer = sprintf("%03d", $n);
                                if ($dataQuotation->status_sampling != 'SD') {
                                    foreach ($dataJadwal as $sampling_plan) {
                                        if ($sampling_plan['periode_kontrak'] == $periode_kontrak) {
                                            foreach ($sampling_plan['jadwal'] as $jadwal) {
                                                foreach ($jadwal['kategori'] as $kategori) {
                                                    if (explode(' - ', $kategori)[1] == $number_imaginer) {
                                                        $tanggal_sampling = $jadwal['tanggal'];
                                                        break 2;
                                                    }
                                                }
                                            }
                                            break;
                                        }
                                    }
                                }

                                // $penamaan_titik = $value->penamaan_titik;
                                // if (is_array($value->penamaan_titik)) {
                                //     $penamaan_titik = isset($value->penamaan_titik[$f]) ? $value->penamaan_titik[$f] : '';
                                // }
                                $penamaan_titik = $props[$noSampel];

                                $DataOrderDetail = new OrderDetail;
                                $DataOrderDetail->id_order_header = $dataOrderHeader->id;
                                $DataOrderDetail->no_order = $dataOrderHeader->no_order;
                                $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                                $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                                $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                                $DataOrderDetail->no_sampel = $no_sample;
                                $DataOrderDetail->koding_sampling = $no_sampling;
                                $DataOrderDetail->kontrak = 'C';
                                $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                                $DataOrderDetail->kategori_1 = $t->status_sampling;
                                $DataOrderDetail->kategori_2 = $value->kategori_1;
                                $DataOrderDetail->kategori_3 = $value->kategori_2;
                                $DataOrderDetail->cfr = $no_cfr;
                                $DataOrderDetail->keterangan_1 = $penamaan_titik;
                                $DataOrderDetail->periode = $periode_kontrak;
                                $DataOrderDetail->parameter = json_encode($value->parameter);
                                $DataOrderDetail->regulasi = !empty($value->regulasi) ? json_encode($value->regulasi) : json_encode([]);
                                $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                                $DataOrderDetail->created_by = $this->karyawan;
                                $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                                $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                                // =================================================================

                                if (!file_exists(public_path() . '/barcode/sampling')) {
                                    mkdir(public_path() . '/barcode/sampling', 0777, true);
                                }
                                if (!file_exists(public_path() . '/barcode/sample')) {
                                    mkdir(public_path() . '/barcode/sample', 0777, true);
                                }

                                file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                                file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                                if (explode("-", $value->kategori_1)[1] == 'Air') {
                                    $parameter_names = array_map(function ($p) {
                                        return explode(';', $p)[1];
                                    }, $value->parameter);

                                    $id_kategori = explode("-", $value->kategori_1)[0];
                                    $params = HargaParameter::where('id_kategori', $id_kategori)
                                        ->where('is_active', true)
                                        ->whereIn('nama_parameter', $parameter_names)
                                        ->get();

                                    $param_map = [];
                                    foreach ($params as $param) {
                                        $param_map[$param->nama_parameter] = $param;
                                    }

                                    $botol_volumes = [];
                                    foreach ($value->parameter as $parameter) {
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
                                        $koding = $no_sampling . strtoupper(Str::random(5));

                                        // Hitung jumlah botol yang dibutuhkan
                                        $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                                        $botol[] = (object) [
                                            'koding' => $koding,
                                            'type_botol' => $type,
                                            'volume' => $volume,
                                            'file' => $koding . '.png',
                                            'disiapkan' => $jumlah_botol
                                        ];

                                        if (!file_exists(public_path() . '/barcode/botol')) {
                                            mkdir(public_path() . '/barcode/botol', 0777, true);
                                        }

                                        // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                        $this->generateQR($koding, '/barcode/botol');
                                    }

                                    $DataOrderDetail->persiapan = json_encode($botol);
                                } else {
                                    /*
                                     * Jika kategori bukan air maka tidak perlu membuat botol
                                     * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                                     */
                                    if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                                        $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                            ->whereIn('parameter', $value->parameter)
                                            ->where('is_active', true)
                                            ->get();
                                        $persiapan = [];
                                        foreach ($cek_ketentuan_parameter as $ketentuan) {
                                            $koding = $no_sampling . strtoupper(Str::random(5));
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
                                            $this->generateQR($koding, '/barcode/penjerap');
                                        }
                                        //2025-03-01 18:28
                                        $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                                    }
                                }

                                $DataOrderDetail->save();

                                Ftc::create([
                                    'no_sample' => $no_sample
                                ]);

                                FtcT::create([
                                    'no_sample' => $no_sample
                                ]);

                                foreach ($value->parameter as $v) {
                                    $insert_analisa[] = [
                                        'no_order' => $no_order,
                                        'no_sampel' => $no_sample,
                                        'tanggal_order' => $dataOrderHeader->tanggal_order,
                                        'parameter' => $v
                                    ];
                                }

                                ParameterAnalisa::insert($insert_analisa);

                                $n++;
                                $kategori = $value->kategori_2;
                                $regulasi = $value->regulasi;
                                $parameter = $value->parameter;
                                $oldPeriode = $periode_kontrak;
                            }
                        }
                    }
                }
            }
            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            DB::commit();
            return response()->json([
                'message' => 'Generate Order Kontrak Success',
                'status' => 200
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }

    public function reOrderKontrak($dataQuotation, $no_order, $dataJadwal, $data_lama)
    {
        DB::beginTransaction();
        try {
            $generator = new Barcode();
            $data_detail_lama = OrderDetail::where('no_order', $data_lama->no_order)->where('is_active', 1)->get();

            OrderDetail::where('no_order', $no_order)->update([
                'nama_perusahaan' => $dataQuotation->nama_perusahaan,
                'alamat_perusahaan' => $dataQuotation->alamat_kantor,
                'no_quotation' => $dataQuotation->no_document
            ]);

            $sampel_order_lama = $data_detail_lama->pluck('no_sampel')->toArray();
            $dps_details = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataQuotation->id)->select('data_pendukung_sampling', 'status_sampling')->get()->toArray();

            $sampel_detail_baru = [];
            $detail_baru = [];
            foreach ($dps_details as $dps) {
                $status_sampling = $dps["status_sampling"];
                $dps = json_decode($dps["data_pendukung_sampling"], true);
                $dps = reset($dps);

                foreach ($dps["data_sampling"] as $ds) {
                    $penamaan_titik = [];
                    foreach ($ds["penamaan_titik"] as $titik) {
                        foreach ($titik as $kode => $nama) {
                            $penamaan_titik[$kode] = $nama;
                        }
                    }

                    $temp = array_keys($penamaan_titik);
                    $sampel_detail_baru[] = $temp;

                    foreach ($temp as $t) {
                        $detail_baru[$no_order . "/" . $t] = [
                            "periode_kontrak" => $dps["periode_kontrak"],
                            "status_sampling" => $status_sampling,
                            "kategori_1" => $ds["kategori_1"],
                            "kategori_2" => $ds["kategori_2"],
                            "regulasi" => $ds["regulasi"],
                            "parameter" => $ds["parameter"],
                            "penamaan_titik" => $penamaan_titik[$t]
                        ];
                    }
                }
            }

            // dd($detail_baru, $sampel_detail_baru);
            $sampel_detail_baru = array_merge(...$sampel_detail_baru);
            $sampel_detail_baru = array_map(function ($item) use ($no_order) {
                return $no_order . "/" . $item;
            }, $sampel_detail_baru);
            sort($sampel_detail_baru);

            // dd($sampel_order_lama, $sampel_detail_baru);
            $perubahan_data = array_values(array_intersect($sampel_order_lama, $sampel_detail_baru));
            $pengurangan_data = array_values(array_diff($sampel_order_lama, $sampel_detail_baru));
            $penambahan_data = array_values(array_diff($sampel_detail_baru, $sampel_order_lama));

            // dd($perubahan_data, $pengurangan_data, $penambahan_data);
            if (!empty($perubahan_data)) {
                foreach ($perubahan_data as $changes) {
                    $existing_detail = OrderDetail::where('no_order', $data_lama->no_order)
                        ->where('no_sampel', $changes)
                        ->where('is_active', 1)
                        ->first();
                    if ($existing_detail) {
                        //  ============ insert detail history ===============
                        $search_kategori = '%' . \explode('-', $detail_baru[$changes]["kategori_2"])[1] . ' - ' . substr($changes, -3) . '%';
                        $cek_jadwal = Jadwal::where('no_quotation', $dataQuotation->no_document)
                            ->where('is_active', 1)
                            ->where('kategori', 'like', $search_kategori)
                            ->select('tanggal', 'kategori')
                            ->groupBy('tanggal', 'kategori')
                            ->first();

                        $existing_detail->periode = $detail_baru[$changes]["periode_kontrak"];
                        $existing_detail->kategori_2 = $detail_baru[$changes]["kategori_1"];
                        $existing_detail->kategori_3 = $detail_baru[$changes]["kategori_2"];
                        $existing_detail->keterangan_1 = $detail_baru[$changes]["penamaan_titik"];
                        $existing_detail->parameter = json_encode($detail_baru[$changes]["parameter"]);
                        $existing_detail->regulasi = json_encode($detail_baru[$changes]["regulasi"]);
                        if (isset($cek_jadwal->tanggal))
                            $existing_detail->tanggal_sampling = $cek_jadwal->tanggal;
                        $existing_detail->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                        $existing_detail->save();
                    } else {
                        array_push($penambahan_data, $changes);
                        DB::rollback();
                        return response()->json([
                            'status' => 'failed',
                            'message' => "Ditemukan inkonsistensi data pada sistem. Mohon hubungi tim IT untuk pemeriksaan lebih lanjut.",
                        ], 401);
                    }
                }
            }

            $no = 0;
            $no_urut_cfr = 0;
            if (!empty($penambahan_data)) {
                // dd($penambahan_data);
                $cek_detail = OrderDetail::where('id_order_header', $data_lama->id_order)
                    // ->where('active', 0)
                    ->orderBy('no_sampel', 'DESC')
                    ->first();

                $no_urut_sample = (int) \explode("/", $cek_detail->no_sampel)[1];
                $no_urut_cfr = (int) \explode("/", $cek_detail->cfr)[1];
                $no = $no_urut_sample;
                $trigger = 0;
                $kategori = '';
                $regulasi = $cek_detail->regulasi ?? [];
                $parameter = $cek_detail->parameter ?? [];
                $oldPeriode = '';
                $mark = [];
                foreach ($penambahan_data as $changes) {
                    $value = (object) $detail_baru[$changes];
                    // =================================================================
                    $no++;
                    $no_sample = $changes;
                    /*
                     * Disini bagian pembuatan no sample dan no cfr/lhp
                     * Jika jumlah parameter kurang dari 2 maka akan di cek apakah kategori sama atau tidak
                     * Jika kategori sama maka no akan di increment
                     * Jika kategori tidak sama maka no akan di reset menjadi 0
                     * Jika Kategori Air atau id 1 maka satu nomor sample sama dengan satu nomor cfr/lhp
                     */

                    // Menggunakan array_map untuk mengekstrak nama parameter dan cek Ergonomi
                    $parameterNames = array_map(function ($param) {
                        $parts = explode(';', $param);
                        return isset($parts[1]) ? $parts[1] : '';
                    }, $value->parameter);

                    if ($value->kategori_1 == '1-Air') {
                        $no_urut_cfr++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "11-Udara Ambient"){
                        $no_urut_cfr++;
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == "4-Udara" && $value->kategori_2 == "27-Udara Lingkungan Kerja") {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no_urut_cfr++;
                        } else {
                            if(count($value->parameter) == 1 && $parameter == $value->parameter && json_encode($regulasi) == json_encode($value->regulasi) && $this->cekParamDirect($value->parameter)) {

                            } else {
                                $no_urut_cfr++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else if ($value->kategori_1 == '5-Emisi' && in_array($value->kategori_2, ['31-Emisi Kendaraan (Bensin)', '32-Emisi Kendaraan (Solar)'])) {
                        if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi)) {
                            $no_urut_cfr++;
                        } else {
                            if (
                                ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) != count($value->parameter)) ||
                                ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi))
                            ) {
                                $no_urut_cfr++;
                            }
                        }
                        $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                    } else {
                        if (count($value->parameter) == 1) {
                            if ($kategori != $value->kategori_2 || json_encode($regulasi) != json_encode($value->regulasi) || in_array('Ergonomi', $parameterNames) || in_array('Ergonomi (GS-LK)', $parameterNames) || in_array('Ergonomi (GO-LK)', $parameterNames)) {
                                // dump($cek_detail);
                                if (
                                    $cek_detail->kategori_3 != $value->kategori_2 ||
                                    $cek_detail->regulasi != json_encode($value->regulasi) ||
                                    $cek_detail->parameter != json_encode($value->parameter) ||
                                    $cek_detail->periode != $value->periode_kontrak
                                ) {
                                    $no_urut_cfr++;
                                }

                                if (in_array('Ergonomi', $parameterNames)) {
                                    $no_urut_cfr++;
                                }
                            } else {
                                if (
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) > 1) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) != json_encode($value->regulasi)) ||
                                    ($kategori == $value->kategori_2 && json_encode($regulasi) == json_encode($value->regulasi) && count($parameter) == count($value->parameter) && json_encode($parameter) != json_encode($value->parameter))
                                ) {
                                    $no_urut_cfr++;
                                }
                            }
                            if ($oldPeriode != '' && $oldPeriode != $value->periode_kontrak) {
                                $no_urut_cfr++;
                            }
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        } else {
                            $no_urut_cfr++;
                            $no_cfr = $no_order . '/' . sprintf("%03d", $no_urut_cfr);
                        }
                    }

                    $rand_str = strtoupper(md5($no_sample));
                    for ($i = 1; $i <= 5; $i++) {
                        $no_sampling = self::randomstr($rand_str);
                        $cek_no_sampling = DB::table('order_detail')->where('koding_sampling', $no_sampling)->first();
                        if ($cek_no_sampling == null) {
                            break;
                        }
                    }

                    $number_imaginer = sprintf("%03d", $no);
                    $tanggal_sampling = $value->periode_kontrak . '-01';
                    //dedi 2025-02-14
                    if ($value->status_sampling != 'SD') {
                        $mark[] = $number_imaginer;
                        foreach ($dataJadwal as $sampling_plan) {
                            if ($sampling_plan['periode_kontrak'] == $value->periode_kontrak) {
                                foreach ($sampling_plan['jadwal'] as $jadwal) {
                                    foreach ($jadwal['kategori'] as $kategori) {
                                        // dd($kategori, $number_imaginer);
                                        if (explode(' - ', $kategori)[1] == $number_imaginer) {
                                            $tanggal_sampling = $jadwal['tanggal'];
                                            $key = array_search($number_imaginer, $mark);
                                            unset($mark[$key]);
                                            break 2;
                                        }
                                    }
                                }
                                break;
                            }
                        }
                    }

                    $penamaan_titik = $value->penamaan_titik;

                    // =================================================================
                    $DataOrderDetail = new OrderDetail;
                    $DataOrderDetail->id_order_header = $data_lama->id_order;
                    $DataOrderDetail->no_order = $data_lama->no_order;
                    $DataOrderDetail->nama_perusahaan = $dataQuotation->nama_perusahaan;
                    $DataOrderDetail->alamat_perusahaan = $dataQuotation->alamat_kantor;
                    $DataOrderDetail->no_quotation = $dataQuotation->no_document;
                    $DataOrderDetail->no_sampel = $no_sample;
                    $DataOrderDetail->koding_sampling = $no_sampling;
                    $DataOrderDetail->kontrak = 'C';
                    $DataOrderDetail->periode = $value->periode_kontrak;
                    $DataOrderDetail->tanggal_sampling = $tanggal_sampling;
                    $DataOrderDetail->kategori_1 = $value->status_sampling;
                    $DataOrderDetail->kategori_2 = $value->kategori_1;
                    $DataOrderDetail->kategori_3 = $value->kategori_2;
                    $DataOrderDetail->cfr = $no_cfr;
                    $DataOrderDetail->keterangan_1 = $penamaan_titik;
                    $DataOrderDetail->parameter = json_encode($value->parameter);
                    $DataOrderDetail->regulasi = !empty($value->regulasi) ? json_encode($value->regulasi) : json_encode([]);
                    $DataOrderDetail->created_at = Carbon::now()->format('Y-m-d H:i:s');
                    $DataOrderDetail->created_by = $this->karyawan;
                    $DataOrderDetail->file_koding_sampling = \str_replace("/", "-", $no_sampling) . '.png';
                    $DataOrderDetail->file_koding_sampel = \str_replace("/", "-", $no_sample) . '.png';

                    // =================================================================

                    if (!file_exists(public_path() . '/barcode/sampling')) {
                        mkdir(public_path() . '/barcode/sampling', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sampling/' . \str_replace("/", "-", $no_sampling) . '.png', $generator->getBarcode($no_sampling, $generator::TYPE_CODE_128, 3, 100));

                    if (!file_exists(public_path() . '/barcode/sample')) {
                        mkdir(public_path() . '/barcode/sample', 0777, true);
                    }

                    file_put_contents(public_path() . '/barcode/sample/' . \str_replace("/", "-", $no_sample) . '.png', $generator->getBarcode($no_sample, $generator::TYPE_CODE_128, 3, 100));

                    if (explode("-", $value->kategori_1)[1] == 'Air') {

                        $parameter_names = array_map(function ($p) {
                            return explode(';', $p)[1];
                        }, $value->parameter);

                        $id_kategori = explode("-", $value->kategori_1)[0];

                        $params = HargaParameter::where('id_kategori', $id_kategori)
                            ->where('is_active', true)
                            ->whereIn('nama_parameter', $parameter_names)
                            ->get();

                        $param_map = [];
                        foreach ($params as $param) {
                            $param_map[$param->nama_parameter] = $param;
                        }

                        $botol_volumes = [];
                        foreach ($value->parameter as $parameter) {
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
                            $koding = $no_sampling . strtoupper(Str::random(5));

                            // Hitung jumlah botol yang dibutuhkan
                            $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

                            $botol[] = (object) [
                                'koding' => $koding,
                                'type_botol' => $type,
                                'volume' => $volume,
                                'file' => $koding . '.png',
                                'disiapkan' => $jumlah_botol
                            ];

                            if (!file_exists(public_path() . '/barcode/botol')) {
                                mkdir(public_path() . '/barcode/botol', 0777, true);
                            }

                            // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                            $this->generateQR($koding, '/barcode/botol');
                        }

                        $DataOrderDetail->persiapan = json_encode($botol);
                    } else {
                        /*
                         * Jika kategori bukan air maka tidak perlu membuat botol
                         * cek jika udara dan emisi maka harus di siapkan kertas penjerap
                         */
                        if ($value->kategori_1 == '4-Udara' || $value->kategori_1 == '5-Emisi') {
                            $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                ->whereIn('parameter', $value->parameter)
                                ->where('is_active', true)
                                ->get();
                            $persiapan = [];
                            foreach ($cek_ketentuan_parameter as $ketentuan) {
                                $koding = $no_sampling . strtoupper(Str::random(5));
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
                                $this->generateQR($koding, '/barcode/penjerap');
                            }
                            //2025-03-01 18:28
                            $DataOrderDetail->persiapan = json_encode($persiapan ?? []);
                        }
                    }

                    $DataOrderDetail->save();
                    Ftc::create([
                        'no_sample' => $no_sample
                    ]);

                    FtcT::create([
                        'no_sample' => $no_sample
                    ]);

                    foreach ($value->parameter as $v) {
                        $insert_analisa[] = [
                            'no_order' => $no_order,
                            'no_sampel' => $no_sample,
                            'tanggal_order' => Carbon::now()->format('Y-m-d'),
                            'parameter' => $v
                        ];
                    }

                    ParameterAnalisa::insert($insert_analisa);

                    $kategori = $value->kategori_2;
                    $regulasi = $value->regulasi;
                    $parameter = $value->parameter;
                    $oldPeriode = $value->periode_kontrak;
                }

                if (!empty($mark)) {
                    DB::rollBack();
                    Log::channel('reorder')->info("Terdapat kategori yang belum terjadwal pada", [
                        "no_quotation" => $dataQuotation->no_document,
                        "kategori" => implode(", ", $mark),
                    ]);
                    return response()->json([
                        'status' => 'failed',
                        'message' => 'Terdapat entri jadwal sampling yang belum di-update. Harap koordinasi dengan admin sampling untuk melakukan pembaruan data jadwal tersebut.',
                    ], 401);
                }
            }

            if (!empty($pengurangan_data)) {
                $data = OrderDetail::where('no_order', $no_order)
                    ->whereIn('no_sampel', $pengurangan_data)
                    ->update(['is_active' => 0]);

                Ftc::whereIn('no_sample', $pengurangan_data)->update(['is_active' => 0]);
                FtcT::whereIn('no_sample', $pengurangan_data)->update(['is_active' => 0]);
            }

            $updateHeader = OrderHeader::find($data_lama->id_order);
            $updateHeader->no_document = $dataQuotation->no_document;
            $updateHeader->flag_status = 'ordered';
            $updateHeader->is_revisi = 0;
            $updateHeader->id_cabang = $dataQuotation->id_cabang;
            $updateHeader->nama_perusahaan = $dataQuotation->nama_perusahaan;
            $updateHeader->konsultan = $dataQuotation->konsultan;
            $updateHeader->alamat_kantor = $dataQuotation->alamat_kantor;
            $updateHeader->no_tlp_perusahaan = $dataQuotation->no_tlp_perusahaan;
            $updateHeader->nama_pic_order = $dataQuotation->nama_pic_order;
            $updateHeader->jabatan_pic_order = $dataQuotation->jabatan_pic_order;
            $updateHeader->no_pic_order = $dataQuotation->no_pic_order;
            $updateHeader->email_pic_order = $dataQuotation->email_pic_order;
            $updateHeader->alamat_sampling = $dataQuotation->alamat_sampling;
            $updateHeader->no_tlp_sampling = $dataQuotation->no_tlp_sampling;
            $updateHeader->nama_pic_sampling = $dataQuotation->nama_pic_sampling;
            $updateHeader->jabatan_pic_sampling = $dataQuotation->jabatan_pic_sampling;
            $updateHeader->no_tlp_pic_sampling = $dataQuotation->no_tlp_pic_sampling;
            $updateHeader->email_pic_sampling = $dataQuotation->email_pic_sampling;
            $updateHeader->kategori_customer = $dataQuotation->kategori_customer;
            $updateHeader->sub_kategori = $dataQuotation->sub_kategori;
            $updateHeader->bahan_customer = $dataQuotation->bahan_customer;
            $updateHeader->merk_customer = $dataQuotation->merk_customer;
            $updateHeader->status_wilayah = $dataQuotation->status_wilayah;
            $updateHeader->total_ppn = $dataQuotation->total_ppn;
            $updateHeader->grand_total = $dataQuotation->grand_total;
            $updateHeader->total_dicount = $dataQuotation->total_dicount;
            $updateHeader->total_dpp = $dataQuotation->total_dpp;
            $updateHeader->piutang = $dataQuotation->piutang;
            $updateHeader->biaya_akhir = $dataQuotation->biaya_akhir;
            $updateHeader->wilayah = $dataQuotation->wilayah;
            $updateHeader->syarat_ketentuan = $dataQuotation->syarat_ketentuan;
            $updateHeader->keterangan_tambahan = $dataQuotation->keterangan_tambahan;
            $updateHeader->tanggal_order = Carbon::now()->format('Y-m-d H:i:s');
            $updateHeader->tanggal_penawaran = $dataQuotation->tanggal_penawaran;
            $updateHeader->updated_by = $this->karyawan;
            $updateHeader->updated_at = Carbon::now()->format('Y-m-d H:i:s');
            $updateHeader->save();

            $dataQuotation->flag_status = 'ordered';
            $dataQuotation->save();

            //dedi 2025-02-14 proses fixing jadwal
            Jadwal::where('no_quotation', $dataQuotation->no_document)->update(['status' => '1']);

            $data_detail_baru = OrderDetail::where('id_order_header', $data_lama->id_order)->where('is_active', 1)
                ->select('no_order', 'no_sampel', 'periode', 'tanggal_sampling', 'kategori_1', 'kategori_2', 'keterangan_1', 'regulasi', 'parameter')->get();

            $data_to_log = [
                'data_lama' => $data_detail_lama->toArray(),
                'data_baru' => $data_detail_baru->toArray()
            ];

            $excludes_bcc = ['kharina@intilab.com', 'abidah@intilab.com', 'sucita@intilab.com'];
            $bcc = GetAtasan::where('user_id', 54)->get()->pluck('email')->toArray();
            $bcc = array_filter($bcc, function ($item) use ($excludes_bcc) {
                return !in_array($item, $excludes_bcc);
            });

            // $workerOperation = new WorkerOperation();
            // $workerOperation->index($updateHeader, $data_to_log, $bcc, $this->user_id);
            // dd('stop');
            DB::commit();
            return response()->json([
                'status' => 'success',
                'message' => 'Re-Generate Order kontrak berhasil'
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            dd($e);
            throw new Exception($e->getMessage() . ' in line ' . $e->getLine(), 401);
        }
    }

    public function randomstr($str)
    {
        $result = substr(str_shuffle($str), 0, 12);
        return $result;
    }

    private function generateQR($no_sampel, $directory)
    {
        $filename = \str_replace("/", "_", $no_sampel) . '.png';
        $path = public_path() . "$directory/$filename";

        QrCode::format('png')->size(200)->generate($no_sampel, $path);

        return $filename;
    }

    private function cekParamDirect($value)
    {
        $array = ["230;Ergonomi",
        "2188;Ergonomi (GO-LK)",
        "2116;Ergonomi (GS-LK)",
        "268;Kebisingan",
        "269;Kebisingan (24 Jam)",
        "270;Kebisingan (8 Jam)",
        "2136;Kebisingan SS (LK)",
        "2137;Kebisingan SS (UA)",
        "2234;Kebisingan 24J (UA)",
        "2235;Kebisingan 8J (LK)",
        "271;Kebisingan (P8J)",
        "2236;Kebisingan PR 8J (LK)",
        "2118;Getaran Bangunan (UA-m)",
        "2119;Getaran Bangunan (UA-mm)",
        "2120;Getaran Lingkungan (UA-m)",
        "2121;Getaran Lingkungan (UA-mm)",
        "242;Getaran",
        "243;Getaran (LK) ST",
        "244;Getaran (LK) TL",
        "264;ISBB",
        "2231;ISBB SS",
        "2232;ISBB 8J",
        "265;ISBB (8 Jam)",
        "616;Iklim Kerja Dingin (Cold Stress) - 8 Jam",
        "628;IKD (CS)",
        "2134;IKD 8J (LK-mp)",
        "2135;IKD SS (LK-mp)",
        "2284;IKD 8J (LK-°C)",
        "2286;IKD SS (LK-°C)",
        "272;Kelembaban",
        "275;Laju Ventilasi",
        "333;Suhu",
        "580;Laju Ventilasi (8 Jam)",
        "2281;Laju Ventilasi 8J",
        "1193;Pertukaran Udara",
        "2175;Tekanan Udara (LK)",
        "2176;Tekanan Udara (UA)",
        "277;Medan Listrik",
        "316;Power Density",
        "563;Medan Magnit Statis",
        "2117;Frekuensi Radio (LK)",
        "236;Gelombang Elektro",
        "324;Sinar UV",
        "309;Pencahayaan"];

        return in_array($value, $array);
    }
}
