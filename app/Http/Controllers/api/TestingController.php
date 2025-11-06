<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\{
    QuotationKontrakH,
    SamplingPlan,
    QuotationNonKontrak,
    Jadwal,
    AnalystFormula,
    OrderHeader,
    OrderDetail,
    Invoice,
    PersiapanSampelHeader,
    PersiapanSampelDetail,
    LhpsAirHeader,
    LhpsAirDetail,
    LhpsAirCustom,
    MasterBakumutu,
    HargaParameter,
    KelengkapanKonfirmasiQs,
    Parameter,
    DataLapanganAir,
    LhpUdaraPsikologiHeader,
    SampelTidakSelesai,
    MasterKaryawan,
    QrDocument,
    DataLapanganPartikulatMeter,
    DetailSenyawaVolatile,
    DetailLingkunganHidup,
    DataLapanganDirectLain,
    DetailLingkunganKerja,
    DetailMicrobiologi,
    DataLapanganKebisinganPersonal,
    DataLapanganKebisingan,
    DataLapanganCahaya,
    DataLapanganGetaran,
    DataLapanganGetaranPersonal,
    DataLapanganIklimPanas,
    DataLapanganIklimDingin,
    DataLapanganSwab,
    DataLapanganErgonomi,
    DataLapanganDebuPersonal,
    DataLapanganMedanLM,
    DataLapanganSinarUV,
    DataLapanganPsikologi,
    DataLapanganEmisiKendaraan,
    DataLapanganEmisiCerobong,
    DataLapanganIsokinetikHasil
};
use App\Services\{
    GetAtasan,
    SamplingPlanServices,
    RenderSamplingPlan,
    JadwalServices,
    RenderInvoice,
    RenderInvoiceTitik,
    GeneratePraSampling,
    GenerateQrDocumentLhp,
    LhpTemplate
};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log as FacadesLog;
use Illuminate\Support\Str;
use Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;
use Yajra\DataTables\Facades\DataTables;

use Mpdf\Mpdf;

Carbon::setLocale('id');



class TestingController extends Controller
{
    public function show(Request $request)
    {
        try {
            //code...

            switch ($request->menu) {
                case 'this':
                    dd($this);
                    break;
                case 'attributes':
                    dd($request->attributes->get('user')->karyawan);
                    break;
                case 'request-sampling':
                    $data = QuotationKontrakH::with(['detail', 'sampling'])
                        ->where('id_cabang', $request->cabang);

                    if ($request->has('status')) {
                        $data = $data->where('flag_status', '!=', $request->status);
                    } else {
                        $data = $data->where('is_active', true)
                            ->where('is_approved', true);
                    }

                    if ($request->has('flag')) {
                        $data = $data->where('flag_status', $request->flag);
                    }

                    if ($request->tanggal_penawaran != "") {
                        $data = $data->whereYear('tanggal_penawaran', $request->tanggal_penawaran);
                    }

                    if ($request->has('is_emailed')) {
                        $data = $data->where('is_emailed', $request->is_emailed);
                    }

                    // $idjabatan = $request->attributes->get('user')->karyawan->id_jabatan;
                    // if (!in_array($idjabatan, explode(',', env('JABATAN')))) {
                    //     $data = $data->where('sales_id', $this->user_id);
                    // }
                    // $idjabatan = $request->attributes->get('user')->karyawan->id_jabatan;
                    // if (!in_array('25', explode(',', env('JABATAN')))) {
                    //     $data = $data->where('sales_id', '37');
                    // }

                    return DataTables::of($data->limit(100))
                        ->addColumn('count_jadwal', function ($row) {
                            return $row->sampling->sum(function ($sampling) {
                                return $sampling->jadwal->count();
                            });
                        })
                        ->addColumn('count_detail', function ($row) {
                            return $row->detail->count();
                        })
                        ->make(true);
                case 'table_name':
                    $dev = DB::table('INFORMATION_SCHEMA.COLUMNS')
                        ->select('COLUMN_NAME')
                        ->where('TABLE_NAME', $request->dev)
                        ->where('TABLE_SCHEMA', 'intilab_produksi') // or specify the database name
                        ->pluck('COLUMN_NAME');

                    $intilab = DB::table('INFORMATION_SCHEMA.COLUMNS')
                        ->select('COLUMN_NAME')
                        ->where('TABLE_NAME', $request->intilab)
                        ->where('TABLE_SCHEMA', 'intilab_2024') // or specify the database name
                        ->pluck('COLUMN_NAME');
                    return response()->json([
                        "dev_2024" => $dev,
                        "intilab_2024" => $intilab
                    ], 200);
                case "getAtasan":
                    $data = GetAtasan::where('user_id', 54)->get()->pluck('email')->toArray();
                    $bcc = GetAtasan::where('user_id', 54)->get()->pluck('email')->toArray();
                    $bcc = array_filter($bcc, function ($item) {
                        return $item !== 'kharina@intilab.com';
                    });

                    return response()->json($bcc, 200);

                case 'nodoc':
                    $data = SamplingPlanServices::on('tanggal_penawaran', "2025-01-17")->createNoDocSampling();
                    return response()->json($data, 200);
                case 'getpraNoSample':
                    $data = SamplingPlan::with('praNoSample')->where('no_quotation', $request->no_quotation)->get();

                    return response()->json($data, 200);
                case 'renderJadwal':
                    $data = QuotationNonKontrak::leftjoin('master_karyawan as a', 'request_quotation.sales_id', '=', 'a.id')
                        ->leftjoin('master_karyawan as u', 'request_quotation.updated_by', '=', 'u.id')
                        ->select(
                            'request_quotation.*',
                            'a.nama_lengkap as addby',
                            'u.nama_lengkap as updateby',
                            'a.email as email_addby',
                            'u.email as email_updateby',
                            'a.atasan_langsung as atasan_addby',
                            'u.atasan_langsung as atasan_updateby',
                        )
                        ->where('request_quotation.is_active', true)
                        ->where('request_quotation.no_document', $request->no_document)
                        ->first();

                    $data1 = QuotationNonKontrak::with(['sales:id,nama_lengkap,email,atasan_langsung', 'updatedby:id,nama_lengkap,email,atasan_langsung'])
                        ->where('no_document', $request->no_document)
                        ->where('is_active', true)
                        ->first();
                    return response()->json([
                        'data' => $data,
                        'data1' => $data1
                    ], 200);
                case 'db_name':
                    $tables = DB::table('INFORMATION_SCHEMA.TABLES')
                        ->select('TABLE_NAME')
                        ->where('TABLE_SCHEMA', 'intilab_2024')
                        ->pluck('TABLE_NAME');

                    return response()->json($tables, 200);
                case 'update-nodoc':

                    $rows = SamplingPlan::where('no_document', 'like', '%ISL/SP/25-I%')->orderBy('no_document')->get(['no_document', 'quotation_id', 'status_quotation', 'id'])->toArray();
                    $num = 0;
                    foreach ($rows as $row) {
                        $num++;
                        // Buat nomor baru dengan format 8 digit, misalnya: 00000001, 00000002, ...
                        $newNo = 'ISL/SP/25-I/' . str_pad($num, 8, '0', STR_PAD_LEFT);

                        // Update record berdasarkan id
                        SamplingPlan::where('id', $row['id'])
                            ->update(['no_document' => $newNo]);
                    }
                    return response()->json('berhasil update nodoc', 200);
                case 'render-doc':

                    $rows = SamplingPlan::whereIn('no_document', ['ISL/SP/24-XII/09103597'])
                        ->where('status', 1)
                        ->where('is_active', 1)
                        ->where('is_approved', 1)
                        ->orderBy('no_document', 'asc')
                        ->get(['no_document', 'quotation_id', 'status_quotation', 'id'])
                        ->toArray();
                    // dd($rows);
                    foreach ($rows as $row) {
                        if ($row['status_quotation'] == 'kontrak') {
                            $render = RenderSamplingPlan::onKontrak($row['quotation_id']);
                        } else {
                            $render = RenderSamplingPlan::onNonKontrak($row['quotation_id']);
                        }
                        $render->save();
                    }
                    return response()->json('berhasil render', 200);
                case 'update-jadwal':
                    /* cek no document */
                    $noDoc24 = SamplingPlan::where('no_quotation', 'like', '%ISL/QTC/24-%')
                        ->where('is_active', 'false')->get(['id', 'no_document']);

                    $noDoc25 = SamplingPlan::where('no_quotation', 'like', '%ISL/QTC/25-%')
                        ->where('is_active', 'false')->get(['id', 'no_document']);

                    $jadwal24 = Jadwal::whereIn('id_sampling', $noDoc24->pluck('id'))->where('is_active', true)->update(['is_active' => false]);
                    $jadwal25 = Jadwal::whereIn('id_sampling', $noDoc25->pluck('id'))->where('is_active', true)->update(['is_active' => false]);


                    return response()->json(['jadwal24' => $jadwal24, 'jadwal25' => $jadwal25], 200);
                case 'render-email-sp':
                    // $sp=SamplingPlan::select('no_quotation','quotation_id','status_quotation')->where('is_active',true)
                    // ->where('status',true)
                    // ->where('is_approved',true)
                    // ->groupBy('no_quotation','quotation_id','status_quotation')->get();
                    // return response()->json($sp, 200);

                    $checkJadwal = JadwalServices::on('no_quotation', 'ISL/QTC/24-I/000136')->countJadwalApproved();
                    $chekQoutations = JadwalServices::on('no_quotation', 'ISL/QTC/24-I/000136')
                        ->on('quotation_id', 128)->countQuotation();
                    if ($chekQoutations == $checkJadwal) {
                        return response()->json('sama', 200);
                    } else {
                        return response()->json('selisih ' . $checkJadwal . '/' . $chekQoutations, 200);
                    }
                case 'kategori':
                    $kategori = Jadwal::where('no_quotation', 'LIKE', '%ISL/QTC/25-II/000523R2%')
                        ->where('is_active', 1)
                        ->select(DB::raw('CONCAT(kategori) as kategori'))
                        ->groupBy('kategori')
                        ->pluck('kategori')->toArray();

                    $semua_kategori = [];
                    foreach ($kategori as $sublist) {
                        $semua_kategori = array_merge($semua_kategori, json_decode($sublist, true));
                    }

                    dd($semua_kategori);
                    return response()->json(['data' => $kategori], 200);
                case 'update perisapan':

                    // Build the query to retrieve PersiapanSampelHeader records
                    if ($request->mode == 'byNoOrder') {
                        try {
                            //code...
                            $query = PersiapanSampelHeader::with([
                                'psDetail' => function ($q) {
                                    $q->where('is_active', 1)
                                        ->select('id_persiapan_sampel_header', 'no_sampel', 'parameters');
                                }
                            ])
                                ->whereNotNull('no_sampel')
                                ->whereNotNull('filename')
                                ->where('is_active', 1)
                                ->where('no_order', $request->no_order);

                            if (isset($request->periode) && $request->periode !== null) {
                                $query->where('periode', $request->periode);
                            }
                            $headers = $query->get();
                            // Extract and flatten 'no_sampel' values from the 'psDetail' relationship
                            $sampleNumbers = $headers->flatMap(function ($header) {
                                return $header->psDetail->pluck('no_sampel');
                            })
                                ->unique() // Get only distinct sample numbers
                                ->values(); // Reset array keys after unique()

                            $data = OrderDetail::where('is_active', 1)
                                ->whereIn('no_sampel', $sampleNumbers)
                                ->where(function ($query) {
                                    $query->whereJsonDoesntContain('parameter', '309;Pencahayaan')
                                        ->whereJsonDoesntContain('parameter', '268;Kebisingan')
                                        ->whereJsonDoesntContain('parameter', '318;Psikologi')
                                        ->whereJsonDoesntContain('parameter', '230;Ergonomi');
                                })
                                ->where('kategori_1', '!=', 'SD')
                                ->whereNull('tanggal_terima')
                                ->where('tanggal_sampling', $request->tanggal_sampling);

                            if (isset($request->periode) && $request->periode != null) {
                                $data->where('periode', $request->periode);
                            }
                            $data->update(['persiapan' => '[]']);

                            $updatePersiapan = PersiapanSampelDetail::whereIn('no_sampel', $sampleNumbers)
                                ->where('is_active', 1)
                                ->update(['parameters' => NULL]);
                            return response()->json('berhasil di kosongkan', 200);
                        } catch (\Exception $ex) {
                            //throw $th;
                            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()]);
                        }
                    } else if ($request->mode == 'byTanggal') {
                        try {
                            //code...
                            $query = PersiapanSampelHeader::with([
                                'psDetail' => function ($q) {
                                    $q->where('is_active', 1)
                                        ->select('id_persiapan_sampel_header', 'no_sampel', 'parameters');
                                }
                            ])
                                ->whereNotNull('no_sampel')
                                ->where('is_active', 1)
                                ->where('no_quotation', 'like', '%24-%')
                                ->whereBetween('tanggal_sampling', ['2025-08-07', '2025-08-31'])
                                ->whereRaw('JSON_LENGTH(no_sampel) > 0');

                            $headers = $query->get();

                            // Flatten 'no_sampel' dari relasi psDetail
                            $sampleNumbers = $headers->flatMap(function ($header) {
                                return $header->psDetail->pluck('no_sampel');
                            })->unique()->values();

                            $data = OrderDetail::where('is_active', 1)
                                ->whereIn('no_sampel', $sampleNumbers)
                                ->where(function ($query) {
                                    $query->whereJsonDoesntContain('parameter', '309;Pencahayaan')
                                        ->whereJsonDoesntContain('parameter', '268;Kebisingan')
                                        ->whereJsonDoesntContain('parameter', '318;Psikologi')
                                        ->whereJsonDoesntContain('parameter', '230;Ergonomi');
                                })
                                ->where('kategori_1', '!=', 'SD')
                                ->whereNull('tanggal_terima');

                            if (isset($request->periode) && $request->periode != null) {
                                $data->where('periode', $request->periode);
                            }
                            $data->update(['persiapan' => '[]']);

                            $updatePersiapan = PersiapanSampelDetail::whereIn('no_sampel', $sampleNumbers)
                                ->where('is_active', 1)
                                ->update(['parameters' => NULL]);
                            return response()->json('berhasil di kosongkan', 200);
                        } catch (\Exception $ex) {
                            //throw $th;
                            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()]);
                        }
                    } else if ($request->mode == 'spek') {
                        try {
                            //code...
                            $query = PersiapanSampelHeader::with([
                                'psDetail' => function ($q) {
                                    $q->where('is_active', 1)
                                        ->select('id_persiapan_sampel_header', 'no_sampel', 'parameters');
                                }
                            ])
                                ->whereNotNull('no_sampel')
                                ->where('is_active', 1)
                                ->where('no_order', $request->no_order)
                                ->where('tanggal_sampling', $request->tanggal_sampling)
                                ->whereRaw('JSON_LENGTH(no_sampel) > 0');

                            $headers = $query->get();

                            // Flatten 'no_sampel' dari relasi psDetail
                            $sampleNumbers = $headers->flatMap(function ($header) {
                                return $header->psDetail->pluck('no_sampel');
                            })->unique()->values();

                            $data = OrderDetail::where('is_active', 1)
                                ->whereIn('no_sampel', $sampleNumbers)
                                ->where(function ($query) {
                                    $query->whereJsonDoesntContain('parameter', '309;Pencahayaan')
                                        ->whereJsonDoesntContain('parameter', '268;Kebisingan')
                                        ->whereJsonDoesntContain('parameter', '318;Psikologi')
                                        ->whereJsonDoesntContain('parameter', '230;Ergonomi');
                                })
                                ->where('kategori_1', '!=', 'SD')
                                ->whereNull('tanggal_terima');

                            if (isset($request->periode) && $request->periode != null) {
                                $data->where('periode', $request->periode);
                            }
                            $data->update(['persiapan' => '[]']);

                            $updatePersiapan = PersiapanSampelDetail::whereIn('no_sampel', $sampleNumbers)
                                ->where('is_active', 1)
                                ->update(['parameters' => NULL]);
                            return response()->json('berhasil di kosongkan', 200);
                        } catch (\Exception $ex) {
                            //throw $th;
                            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()]);
                        }
                    }
                case 'get no order':
                    try {
                        $noOrder = OrderHeader::where('no_document', $request->noqt)
                            ->where('is_revisi', 0)
                            ->where('is_active', 1)
                            ->first();
                        if ($noOrder != null) {
                            return response()->json(["no_order" => $noOrder->no_order], 200);
                        } else {
                            return response()->json(["no_order" => 'sedang revisi'], 200);
                        }
                    } catch (\Exception $ex) {
                        //throw $th;
                        return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine()], 400);
                    }
                case 'global label':

                    if ($request->mode == 'byrangetanggal') {
                        DB::beginTransaction();
                        try {
                            $data = OrderHeader::select('id', 'no_document')
                                ->whereBetween(
                                    DB::raw("SUBSTRING_INDEX(SUBSTRING_INDEX(no_document, '/', 3), '/', -1)"),
                                    ['24-V', '25-III']
                                )
                                ->where('is_revisi', 0)
                                ->get()
                                ->pluck('id')
                                ->toArray();
                            $allNoSampel = [];
                            OrderDetail::whereIn('id_order_header', $data)
                                ->where('kategori_1', '!=', 'SD')
                                ->whereNull('tanggal_terima')
                                ->whereBetween('tanggal_sampling', ['2025-08-15', '2025-08-31'])
                                ->where('is_active', 1)
                                ->chunk(500, function ($details) use (&$allNoSampel) {
                                    foreach ($details as $value) {
                                        $allNoSampel[] = $value->no_sampel;
                                        // if (explode("-", $value->kategori_2)[1] == 'Air') {
                                        //     $parameter_names = array_map(function ($p) {
                                        //         return explode(';', $p)[1];
                                        //     }, json_decode($value->parameter) ?? []);

                                        //     $id_kategori = explode("-", $value->kategori_2)[0];
                                        //     $params = HargaParameter::where('id_kategori', $id_kategori)
                                        //         ->where('is_active', true)
                                        //         ->whereIn('nama_parameter', $parameter_names)
                                        //         ->get();

                                        //     $param_map = [];
                                        //     foreach ($params as $param) {
                                        //         $param_map[$param->nama_parameter] = $param;
                                        //     }

                                        //     $botol_volumes = [];
                                        //     foreach (json_decode($value->parameter) ?? [] as $parameter) {
                                        //         $param_name = explode(';', $parameter)[1];
                                        //         if (isset($param_map[$param_name])) {
                                        //             $param = $param_map[$param_name];
                                        //             if (!isset($botol_volumes[$param->regen])) {
                                        //                 $botol_volumes[$param->regen] = 0;
                                        //             }
                                        //             $botol_volumes[$param->regen] += ($param->volume != "" && $param->volume != "-" && $param->volume != null) ? (float) $param->volume : 0;
                                        //         }
                                        //     }

                                        //     // Generate botol dan barcode
                                        //     $botol = [];

                                        //     $ketentuan_botol = [
                                        //         'ORI' => 1000,
                                        //         'H2SO4' => 1000,
                                        //         'M100' => 100,
                                        //         'HNO3' => 500,
                                        //         'M1000' => 1000,
                                        //         'BENTHOS' => 100
                                        //     ];

                                        //     foreach ($botol_volumes as $type => $volume) {
                                        //         $typeUpper = strtoupper($type);
                                        //         if (!isset($ketentuan_botol[$typeUpper])) {
                                        //             // kalau ketentuan botol tidak ditemukan, skip atau kasih default
                                        //             continue;
                                        //         }
                                        //         $koding = $value->koding_sampling . strtoupper(Str::random(5));

                                        //         // Hitung jumlah botol yang dibutuhkan
                                        //         $jumlah_botol = ceil($volume / $ketentuan_botol[$typeUpper]);

                                        //         $botol[] = (object) [
                                        //             'koding' => $koding,
                                        //             'type_botol' => $type,
                                        //             'volume' => $volume,
                                        //             'file' => $koding . '.png',
                                        //             'disiapkan' => (int) $jumlah_botol
                                        //         ];

                                        //         if (!file_exists(public_path() . '/barcode/botol')) {
                                        //             mkdir(public_path() . '/barcode/botol', 0777, true);
                                        //         }

                                        //         // file_put_contents(public_path() . '/barcode/botol/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                        //         self::generateQR($koding, '/barcode/botol');
                                        //     }

                                        //     $value->persiapan = json_encode($botol);
                                        //     $value->save();
                                        // } else {
                                        //     if ($value->kategori_2 == '4-Udara' || $value->kategori_2 == '5-Emisi') {
                                        //         $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                        //             ->whereIn('parameter', json_decode($value->parameter) ?? [])
                                        //             ->where('is_active', 1)
                                        //             ->get();
                                        //         $persiapan = []; // Pastikan inisialisasi array sebelum digunakan
                                        //         foreach ($cek_ketentuan_parameter as $ketentuan) {
                                        //             $koding = $value->koding_sampling . strtoupper(Str::random(5));
                                        //             $persiapan[] = [
                                        //                 'parameter' => \explode(';', $ketentuan->parameter)[1],
                                        //                 'disiapkan' => $ketentuan->ketentuan,
                                        //                 'koding' => $koding,
                                        //                 'file' => $koding . '.png'
                                        //             ];
                                        //             if (!file_exists(public_path() . '/barcode/penjerap')) {
                                        //                 mkdir(public_path() . '/barcode/penjerap', 0777, true);
                                        //             }
                                        //             // file_put_contents(public_path() . '/barcode/penjerap/' . $koding . '.png', $generator->getBarcode($koding, $generator::TYPE_CODE_128, 3, 100));
                                        //             self::generateQR($koding, '/barcode/penjerap');
                                        //         }
                                        //         // dd($persiapan, 'persiapan');
                                        //         $value->persiapan = json_encode($persiapan ?? []);
                                        //         $value->save();
                                        //     }
                                        // }
                                    }
                                });
                            DB::commit();
                        } catch (\Exception $ex) {
                            //throw $th;
                            DB::rollback();
                            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine(), 'file' => $ex->getFile()], 400);
                        }
                        return response()->json($allNoSampel);
                    } else if ($request->mode == 'bynosampel') {
                        DB::beginTransaction();
                        try {
                            $data = OrderDetail::where('kategori_1', '!=', 'SD')
                                ->whereNull('tanggal_terima')
                                ->whereIn('no_sampel', $request->no_sampel)
                                ->where('is_active', 1)
                                ->get();

                            if ($data->isEmpty()) {
                                return response()->json(['error' => 'Data tidak ditemukan'], 404);
                            }

                            foreach ($data as $value) {
                                $kategoriParts = explode("-", $value->kategori_2);
                                $kategoriType = $kategoriParts[1] ?? null;
                                $id_kategori = $kategoriParts[0] ?? null;

                                if ($kategoriType === 'Air') {
                                    // Ambil nama parameter
                                    $parameter_names = array_map(function ($p) {
                                        return explode(';', $p)[1] ?? null;
                                    }, json_decode($value->parameter, true) ?? []);

                                    // Ambil parameter dari HargaParameter
                                    $params = HargaParameter::where('id_kategori', $id_kategori)
                                        ->where('is_active', true)
                                        ->whereIn('nama_parameter', $parameter_names)
                                        ->get();

                                    // Mapping parameter -> object
                                    $param_map = [];
                                    foreach ($params as $param) {
                                        $param_map[$param->nama_parameter] = $param;
                                    }

                                    // Hitung volume botol
                                    $botol_volumes = [];
                                    foreach (json_decode($value->parameter, true) ?? [] as $parameter) {
                                        $param_name = explode(';', $parameter)[1] ?? null;
                                        if ($param_name && isset($param_map[$param_name])) {
                                            $param = $param_map[$param_name];
                                            if (!isset($botol_volumes[$param->regen])) {
                                                $botol_volumes[$param->regen] = 0;
                                            }
                                            $botol_volumes[$param->regen] += ($param->volume && $param->volume !== '-')
                                                ? (float) $param->volume
                                                : 0;
                                        }
                                    }

                                    // Generate botol & barcode
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
                                            // kalau ketentuan botol tidak ditemukan → skip
                                            continue;
                                        }

                                        $koding = $value->koding_sampling . strtoupper(Str::random(5));

                                        // Hitung jumlah botol
                                        $jumlah_botol = ceil($volume / $ketentuan_botol[$typeUpper]);

                                        $botol[] = (object) [
                                            'koding' => $koding,
                                            'type_botol' => $type,
                                            'volume' => $volume,
                                            'file' => $koding . '.png',
                                            'disiapkan' => (int) $jumlah_botol
                                        ];

                                        if (!file_exists(public_path('barcode/botol'))) {
                                            mkdir(public_path('barcode/botol'), 0777, true);
                                        }

                                        // generate barcode/QR
                                        self::generateQR($koding, '/barcode/botol');
                                    }

                                    $value->persiapan = json_encode($botol);
                                    $value->save();
                                } else {
                                    if (in_array($value->kategori_2, ['4-Udara', '5-Emisi'])) {
                                        $cek_ketentuan_parameter = DB::table('konfigurasi_pra_sampling')
                                            ->whereIn('parameter', json_decode($value->parameter, true) ?? [])
                                            ->where('is_active', 1)
                                            ->get();

                                        $persiapan = [];
                                        foreach ($cek_ketentuan_parameter as $ketentuan) {
                                            $koding = $value->koding_sampling . strtoupper(Str::random(5));

                                            $persiapan[] = [
                                                'parameter' => explode(';', $ketentuan->parameter)[1] ?? null,
                                                'disiapkan' => $ketentuan->ketentuan,
                                                'koding' => $koding,
                                                'file' => $koding . '.png'
                                            ];

                                            if (!file_exists(public_path('barcode/penjerap'))) {
                                                mkdir(public_path('barcode/penjerap'), 0777, true);
                                            }

                                            // generate barcode/QR
                                            self::generateQR($koding, '/barcode/penjerap');
                                        }

                                        $value->persiapan = json_encode($persiapan);
                                        $value->save();
                                    }
                                }
                            }

                            DB::commit();
                            return response()->json(['message' => "berhasil update"], 200);
                        } catch (\Exception $ex) {
                            //throw $th;
                            DB::rollback();
                            return response()->json(['message' => $ex->getMessage(), 'line' => $ex->getLine(), 'file' => $ex->getFile()], 400);
                        }
                    }
                case 'app bas':
                    try {

                        // Filter data untuk hanya mendapatkan data yang memiliki 'sampler' sesuai dengan $this->karyawan
                        $isProgrammer = MasterKaryawan::where('nama_lengkap', 'Afdhal Luthfi')->whereIn('id_jabatan', [41, 42])->exists();
                        $orderDetail = OrderDetail::with([
                            'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi,email_pic_order,email_pic_sampling',
                            'orderHeader.samplingPlan',
                            'orderHeader.samplingPlan.jadwal' => function ($q) use ($isProgrammer) {
                                $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                                    ->where('is_active', true)
                                    ->when(!$isProgrammer, function ($query) {
                                        $query->where('sampler', $this->karyawan);
                                    })
                                    ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai']);
                            },
                            'orderHeader.docCodeSampling' => function ($q) {
                                $q->where('menu', 'STPS');
                            }
                        ])
                            ->select(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1'])
                            ->where('is_active', true)
                            ->where('kategori_1', '!=', 'SD')
                            ->where('no_quotation', 'ISL/QTC/25-I/000041R13');
                        if ($isProgrammer) {
                            // $orderDetail->whereBetween('tanggal_sampling', [
                            //     Carbon::now()->startOfMonth()->toDateString(),
                            //     Carbon::now()->endOfMonth()->toDateString()
                            // ]);
                            $orderDetail->where('tanggal_sampling', '2025-08-05');
                        } else {
                            $orderDetail->whereBetween('tanggal_sampling', [
                                // "2025-04-31",
                                Carbon::now()->subDays(8)->toDateString(),
                                Carbon::now()->toDateString()
                            ]);
                        }
                        $orderDetail->groupBy(['id_order_header', 'no_order', 'kategori_2', 'periode', 'tanggal_sampling', 'parameter', 'no_sampel', 'keterangan_1']);

                        $orderDetail = $orderDetail->get()->toArray();

                        $formattedData = array_reduce($orderDetail, function ($carry, $item) {
                            if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                                return $carry;

                            $samplingPlan = $item['order_header']['sampling'];
                            $periode = $item['periode'] ?? '';

                            $targetPlan = $periode ? current(array_filter($samplingPlan, fn($plan) => isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode)) : current($samplingPlan);

                            if (!$targetPlan)
                                return $carry;

                            $results = [];
                            $jadwal = $targetPlan['jadwal'] ?? [];

                            // dd($jadwal);
                            foreach ($jadwal as $schedule) {
                                if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                                    $results[] = [
                                        'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                                        'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                                        'status_sampling' => $item['kategori_1'] ?? '',
                                        'periode' => $periode,
                                        'jadwal' => $schedule['tanggal'],
                                        'durasi' => $schedule['durasi'],
                                        'jadwal_jam_mulai' => $schedule['jam_mulai'],
                                        'jadwal_jam_selesai' => $schedule['jam_selesai'],
                                        'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                                        'sampler' => $schedule['sampler'] ?? '',
                                        'no_order' => $item['no_order'] ?? '',
                                        'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                                        'konsultan' => $item['order_header']['konsultan'] ?? '',
                                        'is_revisi' => $item['order_header']['is_revisi'] ?? '',
                                        'info_pendukung' => json_encode([
                                            'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                            'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                            'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                            'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                            'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                                        ]),
                                        'info_sampling' => json_encode([
                                            'id_sp' => $targetPlan['id'],
                                            'id_request' => $targetPlan['quotation_id'],
                                            'status_quotation' => $targetPlan['status_quotation'],
                                        ]),
                                        'email_pic_sampling' => $item['order_header']['email_pic_sampling'] ?? '',
                                        'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'] ?? '',
                                        'parameter' => $item['parameter'],
                                        'kategori_2' => $item['kategori_2'],
                                        'no_sample' => $item['no_sampel'],
                                        'keterangan_1' => $item['keterangan_1']
                                    ];
                                }
                            }

                            return array_merge($carry, $results);
                        }, []);

                        $groupedData = [];

                        // dd(json_decode($formattedData[0]['parameters'], true));

                        foreach ($formattedData as $item) {
                            // Group TANPA field 'sampler'
                            $key = implode('|', [
                                $item['nomor_quotation'],
                                $item['nama_perusahaan'],
                                $item['status_sampling'],
                                $item['periode'],
                                $item['jadwal'],
                                $item['durasi'],
                                $item['no_order'],
                                $item['alamat_sampling'],
                                $item['konsultan'],
                                $item['kategori'],
                                $item['info_pendukung'],
                                $item['jadwal_jam_mulai'],
                                $item['jadwal_jam_selesai'],
                                $item['info_sampling'],
                                $item['email_pic_sampling'],
                                $item['nama_pic_sampling'],
                            ]);

                            if (!isset($groupedData[$key])) {
                                // Simpan semua data kecuali sampler ke dalam base_data
                                $groupedData[$key] = [
                                    'base_data' => [
                                        'nomor_quotation' => $item['nomor_quotation'],
                                        'nama_perusahaan' => $item['nama_perusahaan'],
                                        'status_sampling' => $item['status_sampling'],
                                        'periode' => $item['periode'],
                                        'jadwal' => $item['jadwal'],
                                        'durasi' => $item['durasi'],
                                        'kategori' => $item['kategori'],
                                        'no_order' => $item['no_order'],
                                        'alamat_sampling' => $item['alamat_sampling'],
                                        'konsultan' => $item['konsultan'],
                                        'info_pendukung' => $item['info_pendukung'],
                                        'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                                        'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                                        'info_sampling' => $item['info_sampling'],
                                        'is_revisi' => $item['is_revisi'],
                                        'email_pic_sampling' => $item['email_pic_sampling'],
                                        'nama_pic_sampling' => $item['nama_pic_sampling'],
                                        'parameter' => $item['parameter'],
                                        'no_sample' => $item['no_sample'],
                                        'kategori_2' => $item['kategori_2'],
                                        'keterangan_1' => $item['keterangan_1'],
                                    ],
                                    'samplers' => [],
                                ];
                            }

                            // Hindari duplicate sampler
                            if (!in_array($item['sampler'], $groupedData[$key]['samplers'])) {
                                $groupedData[$key]['samplers'][] = $item['sampler'];
                            }
                        }

                        // dd($groupedData);

                        // Buat final result: 1 data per sampler
                        $finalResult = [];

                        foreach ($groupedData as $group) {
                            foreach ($group['samplers'] as $sampler) {
                                $finalResult[] = array_merge($group['base_data'], [
                                    'sampler' => $sampler
                                ]);
                            }
                        }

                        $finalResult = array_values($finalResult);

                        // Ambil semua no_order dari hasil akhir
                        $orderNos = array_column($finalResult, 'no_order');

                        // Ambil data catatan, informasi teknis, dan tanda_tangan_bas dari tabel PersiapanSampelHeader berdasarkan no_order

                        // Add detail_bas_documents to each item
                        foreach ($finalResult as &$item) {
                            $persiapanHeaders = PersiapanSampelHeader::where('no_order', $item['no_order'])->where('is_active', true)->where('tanggal_sampling', $item['jadwal'])->orderBy('id', 'desc')->first();
                            // dd($persiapanHeaders);
                            if (isset($persiapanHeaders)) {
                                $header = $persiapanHeaders;
                                // dd($item);
                                if ($header->detail_bas_documents) {
                                    $item['detail_bas_documents'] = json_decode($header->detail_bas_documents, true);

                                    // Iterasi untuk setiap dokumen
                                    foreach ($item['detail_bas_documents'] as $docIndex => $document) {
                                        if (isset($document['tanda_tangan']) && is_array($document['tanda_tangan'])) {
                                            foreach ($document['tanda_tangan'] as $key => $ttd) {
                                                // Lakukan pengecekan apakah data sudah berupa data URI (data:image/png;base64,...)
                                                if (strpos($ttd['tanda_tangan'], 'data:') === 0) {
                                                    $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                                } else {
                                                    $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                                    if ($sign->status != 'error') {
                                                        $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                                        $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan'] = $sign->base64;
                                                    } else {
                                                        $item['detail_bas_documents'][$docIndex]['tanda_tangan'][$key]['tanda_tangan_lama'] = $ttd['tanda_tangan'];
                                                    }
                                                }
                                            }
                                        }
                                    }
                                } else {
                                    $item['detail_bas_documents'] = [];

                                    if ($header->catatan || $header->informasi_teknis || $header->tanda_tangan_bas || $header->waktu_mulai || $header->waktu_selesai) {
                                        $document = [
                                            'tanda_tangan' => [],
                                            'filename' => $header->filename_bas ?? '',
                                            'catatan' => $header->catatan ?? '',
                                            'informasi_teknis' => $header->informasi_teknis ?? '',
                                            'waktu_mulai' => $header->waktu_mulai ?? '',
                                            'waktu_selesai' => $header->waktu_selesai ?? '',
                                            'no_sampel' => []
                                        ];

                                        if ($header->tanda_tangan_bas) {
                                            $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                                            $signatures = [];

                                            foreach ($ttd_bas as $ttd) {
                                                $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                                if ($sign->status != 'error') {
                                                    $signatures[] = [
                                                        'nama' => $ttd['nama'],
                                                        'role' => $ttd['role'],
                                                        'tanda_tangan' => $sign->base64,
                                                        'tanda_tangan_lama' => $ttd['tanda_tangan']
                                                    ];
                                                }
                                            }

                                            $document['tanda_tangan'] = $signatures;
                                        }

                                        $item['detail_bas_documents'][] = $document;
                                    }
                                }

                                $item['catatan'] = $header->catatan ?? '';
                                $item['informasi_teknis'] = $header->informasi_teknis ?? '';
                                $item['waktu_mulai'] = $header->waktu_mulai ?? '';
                                $item['waktu_selesai'] = $header->waktu_selesai ?? '';

                                if ($header->tanda_tangan_bas) {
                                    $ttd_bas = json_decode($header->tanda_tangan_bas, true) ?? [];
                                    $signature = array_map(function ($ttd) {
                                        $sign = $this->decodeImageToBase64($ttd['tanda_tangan']);
                                        if ($sign->status == 'error') {
                                            return null;
                                        }

                                        return [
                                            'nama' => $ttd['nama'],
                                            'role' => $ttd['role'],
                                            'tanda_tangan' => $sign->base64,
                                            'tanda_tangan_lama' => $ttd['tanda_tangan']
                                        ];
                                    }, $ttd_bas);
                                    $signature = array_filter($signature, function ($item) {
                                        return $item !== null;
                                    });
                                    $item['tanda_tangan_bas'] = $signature;
                                } else {
                                    $item['tanda_tangan_bas'] = [];
                                }
                            } else {
                                $item['detail_bas_documents'] = [];
                                $item['catatan'] = '';
                                $item['informasi_teknis'] = '';
                                $item['waktu_mulai'] = '';
                                $item['waktu_selesai'] = '';
                                $item['tanda_tangan_bas'] = [];
                            }
                        }
                        unset($item);
                        if ($isProgrammer) {
                            $filteredResult = $finalResult;
                        } else {
                            $filteredResult = array_filter($finalResult, function ($item) {
                                return isset($item['sampler']) && $item['sampler'] == $this->karyawan;
                            });
                        }

                        // Reindex array setelah filter jika diperlukan
                        $filteredResult = array_values($filteredResult);

                        // Jika tidak ada hasil yang sesuai, bisa mengembalikan pesan atau melakukan tindakan lain
                        if (count($filteredResult) === 0) {
                            return response()->json([
                                'message' => 'Data tidak ditemukan untuk sampler yang sesuai dengan karyawan.'
                            ], 200);
                        }

                        // filter tanggal sampling sesuai durasi jadwal
                        $today = Carbon::today();
                        $filtered = [];

                        foreach ($filteredResult as $item) {
                            $jadwal = Carbon::parse($item['jadwal']);
                            $durasi = (int) $item['durasi'];

                            if ($durasi <= 1) { // sesaat ato 8jam
                                if ($jadwal->isSameDay($today))
                                    $filtered[] = $item;
                            } else {
                                $endDate = $jadwal->copy()->addDays($durasi - 1);
                                if ($today->between($jadwal, $endDate))
                                    $filtered[] = $item;
                            }
                        }

                        $orderD = OrderDetail::where('no_order', $request->no_order)
                            ->where('is_active', true)
                            ->where('tanggal_sampling', $request->tanggal_sampling)
                            ->get()
                            ->map(function ($item) {
                                return (object) $item->toArray(); // ubah ke stdClass
                            });

                        if (!$orderD->isEmpty()) {
                            $detail_sampling_sampel = [];

                            foreach ($orderD as $key => $item) {
                                $item->no_sample = $item->no_sampel;
                                if ($item->kategori_2 === "1-Air") {
                                    $exists = DataLapanganAir::where('no_sampel', $item->no_sample)->exists();
                                    $detail_sampling_sampel[$key]['status'] = $exists ? 'selesai' : 'belum selesai';
                                    $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
                                    $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
                                    $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
                                    $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

                                    $dataSampelBelumSelesai = SampelTidakSelesai::where('no_sampel', $item->no_sample)->first();
                                    $detail_sampling_sampel[$key]['status_sampel'] = (bool) $dataSampelBelumSelesai;
                                } else {
                                    $detail_sampling_sampel[$key]['status'] = $this->getStatusSampling($item);
                                    $detail_sampling_sampel[$key]['no_sampel'] = $item->no_sample;
                                    $detail_sampling_sampel[$key]['kategori_3'] = $item->kategori_3;
                                    $detail_sampling_sampel[$key]['keterangan_1'] = $item->keterangan_1;
                                    $detail_sampling_sampel[$key]['parameter'] = $item->parameter;

                                    $dataSampelBelumSelesai = SampelTidakSelesai::where('no_sampel', $item->no_sample)->first();
                                    $detail_sampling_sampel[$key]['status_sampel'] = (bool) $dataSampelBelumSelesai;
                                }
                            }
                            // dd($detail_sampling_sampel);

                            // Gabungkan detail_sampling_sampel ke filteredResult
                            foreach ($filteredResult as $key => $value) {
                                $kategoriItems = explode(',', $value['kategori']);

                                $matchedDetails = [];

                                foreach ($kategoriItems as $item) {
                                    $parts = explode('-', $item);
                                    $nomor = trim(end($parts));

                                    $katNoOrder = $value['no_order'] . '/' . $nomor;

                                    foreach ($detail_sampling_sampel as $detail) {
                                        if ($detail['no_sampel'] === $katNoOrder) {
                                            $matchedDetails[] = $detail;
                                            break;
                                        }
                                    }
                                }
                                $filteredResult[$key]['detail_sampling_sampel'] = $matchedDetails;
                            }
                        }
                        return response()->json($filteredResult, 200);
                        return DataTables::of($filteredResult)->make(true);
                    } catch (\Exception $ex) {
                        dd($ex);
                        return response()->json([
                            'message' => $ex->getMessage(),
                            'line' => $ex->getLine(),
                        ], 500);
                    }
                case 'decode':
                    $decrypt = $this->makeDecrypt($request->decrypt);
                    return response()->json($decrypt);
                case 'chekregen':
                    $db = OrderDetail::where('no_sampel', $request->no_sampel)
                        ->where('is_active', 1)->first();
                    if ($db != null) {

                        $raw = json_decode($db->parameter, true);

                        $parameter = array_map(function ($item) {
                            $parts = explode(';', $item);
                            return $parts[1] ?? null;
                        }, $raw);
                        $chekRegen = HargaParameter::whereIn('nama_parameter', $parameter)->get(['nama_parameter', 'regen', 'nama_kategori']);
                        return response()->json(["data" => $chekRegen], 200);
                    }
                case 'cs_render':
                    $orderDetail =OrderDetail::where('tanggal_sampling',$request->tanggal_sampling)
                        ->where('no_order',$request->no_order)
                        ->where('is_active',1)
                        ->select('no_quotation','no_sampel','tanggal_sampling','file_koding_sampel','parameter','kategori_2','kategori_3','konsultan','no_order','keterangan_1')
                        ->get();
                    $noSampel = $orderDetail->pluck('no_sampel')->unique();
                    $pSDetailMap =PersiapanSampelDetail::whereIn('no_sampel', $noSampel)
                    ->select('id_persiapan_sampel_header','no_sampel', 'parameters') // Hanya ambil kolom yg perlu
                    ->get()
                    ->keyBy('no_sampel');
                    $idPsh=null;
                    foreach ($orderDetail as $item) {
                        $jumlahBotol = 0;
                        $jumlahLabel = 0;

                        // Cek apakah 'no_sampel' ada di map kita (lookup O(1) - sangat cepat)
                        if (isset($pSDetailMap[$item->no_sampel])) {
                            
                            $psd = $pSDetailMap[$item->no_sampel]; // Langsung ambil data, tanpa loop
                            $idPsh=$psd->id_persiapan_sampel_header;
                            $parameters = json_decode($psd->parameters);

                            if (is_array($parameters) || is_object($parameters)) {
                                foreach ($parameters as $category) {
                                    foreach ($category as $key => $values) {
                                        if (is_object($values) && isset($values->disiapkan)) {
                                            $disiapkan = (int) $values->disiapkan;
                                            $jumlahBotol += $disiapkan;
                                            $jumlahLabel += ($disiapkan * 2);
                                        }
                                    }
                                }
                            }
                        }

                        $item->jumlah_botol = $jumlahBotol;
                        $item->jumlah_label = $jumlahLabel;
                        $item->status_c1 = $this->checkLengthData($item->kategori_2, $item->kategori_3, json_decode($item->parameter), $item->no_sampel);
                    }
                    $psHeader =PersiapanSampelHeader::where('id',$idPsh)->where('is_active',1)->first();
                    $ttd =json_decode($psHeader->detail_cs_documents,true);
                    
                    $noDocument = explode('/', $psHeader->no_document);
                    $noDocument[1] = 'CS';
                    $noDocument = implode('/', $noDocument);
                    
                    $qr_img = '';
                    $qr = QrDocument::where('id_document', $psHeader->id)
                        ->where('type_document', 'coding_sample')
                        ->whereJsonContains('data->no_document', $noDocument)
                        ->first();
                    if ($qr) {
                        $qr_data = json_decode($qr->data, true);
                        if (isset($qr_data['no_document']) && $qr_data['no_document'] == $noDocument) {
                            $qr_img = '<img src="' . public_path() . '/qr_documents/' . $qr->file . '.svg" width="50px" height="50px"><br>' . $qr->kode_qr;
                        }
                    }

                    $signatureData = (object) [
                        'ttd_sampler' => $ttd[0]['ttd_sampler_cs'],
                        'ttd_pic'     => $ttd[0]['ttd_pic_cs'],
                        'nama_pic'    => $ttd[0]['nama_pic_cs'],
                        'nama_sampler'=> $ttd[0]['nama_sampler_cs'],
                    ];
                    
                    try {
                        $pdf = new Mpdf([
                            'mode' => 'utf-8',
                            'format' => 'A4',
                            'margin_header' => 3,
                            'margin_bottom' => 3,
                            'margin_footer' => 3,
                            'setAutoTopMargin' => 'stretch',
                            'setAutoBottomMargin' => 'stretch',
                            'orientation' => 'P'
                        ]);

                        $konsultan = '';
                        if ($orderDetail->first()->konsultan)
                            $konsultan = ' (' . $orderDetail->first()->konsultan . ')';

                        $filename = 'RE_DOC_CS_' . $orderDetail->first()->no_order . '.pdf';
                        
                        $pdf->setFooter([
                            'odd' => [
                                'C' => [
                                    'content' => 'Hal {PAGENO} dari {nbpg}',
                                    'font-size' => 6,
                                    'font-style' => 'I',
                                    'font-family' => 'serif',
                                    'color' => '#606060'
                                ],
                                'R' => [
                                    'content' => 'Note : Dokumen ini diterbitkan otomatis oleh sistem <br> {DATE YmdGi}',
                                    'font-size' => 5,
                                    'font-style' => 'I',
                                    'font-family' => 'serif',
                                    'color' => '#000000'
                                ],
                                'L' => [
                                    'content' => '' . $qr_img . '',
                                    'font-size' => 4,
                                    'font-style' => 'I',
                                    'font-family' => 'serif',
                                    'color' => '#000000'
                                ],
                                'line' => -1,
                            ]
                        ]);

                        $pdf->WriteHTML('
                            <!DOCTYPE html>
                                <html>
                                    <head>
                                        <style>
                                            .custom1 { font-size: 12px; font-weight: bold; }
                                            .custom2 { font-size: 15px; font-weight: bold; text-align: center; padding: 5px; }
                                            .custom3 { font-size: 12px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                                            .custom4 { font-size: 12px; font-weight: bold; border: 1px solid #000000;padding: 5px;}
                                            .custom5 { font-size: 10px; border: 1px solid #000000; padding: 5px;}
                                            .custom6 { font-size: 10px; font-weight: bold; text-align: center; border: 1px solid #000000; padding: 5px;}
                                        </style>
                                    </head>
                                    <body>
                                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                        <tr>
                                            <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                            <td class="custom2" width="320">CODING SAMPLE</br><p style="text-align: center; font-size: x-small;">' . $noDocument . '</p></td>
                                            <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                                        </tr>
                                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                                        <tr>
                                            <td class="custom4">
                                                <table width="100%">
                                                    <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                                    <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                                </table>
                                            </td>
                                            <td width="120" class="custom4" style="text-align: center;">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                            <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                                        </tr>
                                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                                    </table>
                        ');

                        $pdf->defaultheaderline = 0;
                        $pdf->SetHeader('
                                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                        <tr>
                                            <td class="custom1" width="200">PT INTI SURYA LABORATORIUM</td>
                                            <td class="custom2" width="320">CODING SAMPLING</td>
                                            <td class="custom3">' . Carbon::parse($orderDetail->first()->tanggal_sampling)->translatedFormat('d F Y') . '</td>
                                        </tr>
                                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                                        <tr>
                                            <td class="custom4">
                                                <table width="100%">
                                                    <tr><td style="font-size: 9px;">NO QUOTE :</td></tr>
                                                    <tr><td style="text-align: center;">' . $orderDetail->first()->no_quotation . '</td></tr>
                                                </table>
                                            </td>
                                            <td width="120" class="custom4">' . $orderDetail->first()->nama_perusahaan . $konsultan . '</td>
                                            <td class="custom3">' . $orderDetail->first()->no_order . '</td>
                                        </tr>

                                        <tr><td colspan="3" style="padding: 2px;"></td></tr>
                                    </table>
                        ');
                        
                        $pdf->WriteHTML('
                                    <table width="100%" style="border-collapse: collapse; font-family: Arial, Helvetica, sans-serif;">
                                        <tr>
                                            <th class="custom6" width="90">CS</th>
                                            <th class="custom6" width="70">KATEGORI</th>
                                            <th class="custom6">DESKRIPSI</th>
                                            <th class="custom6" width="128">BARCODE</th>
                                            <th class="custom6" width="28">CS</t>
                                            <th class="custom6" width="28">C-1</th>
                                            <th class="custom6" width="28">C-2</t>
                                            <th class="custom6" width="28">C-3</th>
                                        </tr>
                        ');

                        foreach ($orderDetail as $item) {
                            
                            $pdf->WriteHTML('
                                        <tr>
                                            <td class="custom5" width="90">' . $item->no_sampel . '</td>
                                            <td class="custom5" width="70">' . explode("-", $item->kategori_3)[1] . '</td>
                                            <td class="custom5" height="60">' . $item->keterangan_1 . '</td>
                                            <td class="custom5" width="128"><img src="' . public_path() . '/barcode/sample/' . $item->file_koding_sampel . '" style="height: 30px; width:180px;"></td>
                                            <td class="custom5" width="28">' . $item->jumlah_botol . '</td>
                                            <td class="custom5" width="28" style="text-align: center;">' . ($item->status_c1 == 1 ? '✔' : '') . '</td>
                                            <td class="custom5" width="28"></td>
                                            <td class="custom5" width="28"></td>
                                        </tr>
                            ');
                        }
                        $sign_sampler = $this->decodeImageToBase64($signatureData->ttd_sampler);
            $sign_pic = null;
            if($signatureData->ttd_pic != null)$sign_pic = $this->decodeImageToBase64($signatureData->ttd_pic);
            if($sign_sampler->status === 'error' || $sign_pic && $sign_pic->status === 'error'){
                return response()->json([
                    'message' => $sign_pic->message ?? $sign_sampler->message
                ],400);
            }
            $ttd_sampler = $signatureData->ttd_sampler && $sign_sampler->status !== 'error' ? '<img src="' . $sign_sampler->base64 . '" style="height: 60px; max-width: 150px;">' : '';
            $ttd_pic = $signatureData->ttd_pic && $sign_pic->status !== 'error' ? '<img src="' . $sign_pic->base64 . '" style="height: 60px; max-width: 150px;">' : '';

            $pdf->WriteHTML('</table>
                            <table class="table" width="100%" style="border: none;margin-top: 20px">
                                <tr>
                                    <td style="border: none;width: 30%; text-align: center;height: 80px;">' . $ttd_sampler . '</td>
                                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                                    <td style="border: none;width: 20%; text-align: center;height: 80px;"></td>
                                    <td style="border: none;width: 30%; text-align: center;height: 80px;">' . $ttd_pic . '</td>
                                </tr>
                                <tr>
                                    <td style="border: none;width: 30%;text-align: center;"><p><strong>' . strtoupper($signatureData->nama_sampler) . '</strong></p></td>
                                    <td style="border: none;width: 20%;text-align: center;"></td>
                                    <td style="border: none;width: 20%;text-align: center;"></td>
                                    <td style="border: none;width: 30%;text-align: center;"><p><strong>' . strtoupper($signatureData->nama_pic) . '</strong></p></td>
                                </tr>
                                <tr>
                                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Sampler</strong></p></td>
                                    <td style="border: none;width: 20%; text-align: center;"></td>
                                    <td style="border: none;width: 20%; text-align: center;"></td>
                                    <td style="border: none;width: 30%; text-align: center;"><p><strong>Penanggung Jawab</strong></p></td>
                                </tr>
                            </table>');
                       
                        $pdf->WriteHTML('</table></body></html>');
                        $dir = public_path("cs");

                        if (!file_exists($dir)) {
                            mkdir($dir, 0755, true);
                        }
                        $pdf->Output(public_path() . '/cs/' . $filename, 'F');
                        return response()->json(['status' => false, 'data' => $filename], 200);
                    } catch (\Exception $ex) {
                        return response()->json([
                            'message' => $ex->getMessage(),
                            'line' => $ex->getLine(),
                        ], 500);
                    }
                    
                default:
                    return response()->json("Menu tidak ditemukanXw", 404);
            }
        } catch (\Throwable $th) {
            //throw $th;
            dd($th);
        }
    }

    public function bulkRenderInvoice(Request $request)
    {
        if ($request->mode == 'copy') {
            foreach ($request->no_invoice as $item) {
                $render = new RenderInvoiceTitik();
                $render->renderInvoice($item);
            }
        } else {
            foreach ($request->no_invoice as $item) {
                $render = new RenderInvoice();
                $render->renderInvoice($item);
            }
        }
    }

    public function renderDraftAir(Request $request)
    {
        DB::beginTransaction();
        try {
            $header = LhpsAirHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            $detail = LhpsAirDetail::where('id_header', $header->id)->get();
            $custom = LhpsAirCustom::where('id_header', $header->id)->get();

            if ($header != null) {
                if ($header->file_qr == null) {
                    $file_qr = new GenerateQrDocumentLhp();
                    $file_qr_path = $file_qr->insert('LHP_AIR', $header, 'Abidah Walfathiyyah');
                    if ($file_qr_path) {
                        $header->file_qr = $file_qr_path;
                        $header->save();
                    }
                }

                $groupedByPage = [];
                if (!empty($custom)) {
                    foreach ($custom->toArray() as $item) {
                        $page = $item['page'];
                        if (!isset($groupedByPage[$page])) {
                            $groupedByPage[$page] = [];
                        }
                        $groupedByPage[$page][] = $item;
                    }
                }

                $fileName = LhpTemplate::setDataDetail($detail)
                    ->setDataHeader($header)
                    ->setDataCustom($groupedByPage)
                    ->whereView('DraftAir')
                    ->render('downloadLHP');

                $header->file_lhp = $fileName;
                $header->save();
                DB::commit();
                return response()->json([
                    'message' => 'Draft lhp air no sampel ' . $request->no_sampel . ' berhasil dirender',
                    'status' => true
                ], 201);
            } else {
                DB::rollBack();
                return response()->json([
                    'message' => 'Draft lhp air no sampel ' . $request->no_sampel . ' tidak ditemukan',
                    'status' => false
                ], 404);
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Error: ' . $e->getMessage() . ', Line: ' . $e->getLine(), ', File: ' => $e->getFile()], 500);
        }
    }

    public function generatePraSampling(Request $request)
    {
        if (str_contains($request->no_document, '/QTC/')) {
            $data = QuotationKontrakH::where('no_document', $request->no_document)->first();
            $type = 'QTC';
        } else if (str_contains($request->no_document, '/QT/')) {
            $data = QuotationNonKontrak::where('no_document', $request->no_document)->first();
            $type = 'QT';
        } else {
            return response()->json(['message' => 'No document tidak valid'], 404);
        }

        $data_lama = null;
        if ($data->data_lama != null)
            $data_lama = json_decode($data->data_lama);
        if ($data_lama != null) {
            if (isset($data_lama->id_order) && $data_lama->id_order != null) {
                $cek_order = OrderHeader::where('id', $data_lama->id_order)->where('is_active', true)->first();
                $no_qt_lama = $cek_order->no_document;
                $no_qt_baru = $data->no_document;
                $id_order = $data_lama->id_order;

                $parse = new GeneratePraSampling;
                $type == 'QT' ? $parse->type('QT') : $parse->type('QTC');
                $parse->where('no_qt_lama', $no_qt_lama);
                $parse->where('no_qt_baru', $no_qt_baru);
                $parse->where('id_order', $id_order);
                $parse->save();
            } else {
                $parse = new GeneratePraSampling;
                $type == 'QT' ? $parse->type('QT') : $parse->type('QTC');
                $parse->where('no_qt_baru', $data->no_document);
                $parse->where('generate', 'new');
                $parse->save();
            }
        } else {
            $parse = new GeneratePraSampling;
            $type == 'QT' ? $parse->type('QT') : $parse->type('QTC');
            $parse->where('no_qt_baru', $data->no_document);
            $parse->where('generate', 'new');
            $parse->save();
        }

        return response()->json(['message' => 'Pra sampling ' . $data->no_document . ' berhasil di generate'], 201);
    }

    public function changeInvoice(Request $request)
    {
        DB::beginTransaction();
        try {
            $kontrak = Invoice::whereHas('quotationKontrak', function ($q) {
                $q->where('flag_status', 'ordered');
            })
                ->where('is_generate', false)
                ->where('is_emailed', false)
                ->update([
                    'is_generate' => true,
                    'is_emailed' => true
                ]);

            $nonKontrak = Invoice::whereHas('quotationNonKontrak', function ($q) {
                $q->where('flag_status', 'ordered');
            })
                ->where('is_generate', false)
                ->where('is_emailed', false)
                ->update([
                    'is_generate' => true,
                    'is_emailed' => true
                ]);

            if ($kontrak && $nonKontrak) {
                DB::commit();
                return response()->json([
                    'message' => 'Success'
                ], 200);
            }
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error : ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function changeDataPendukungSamplingNonKontrak(Request $request)
    {
        try {
            $dataList = QuotationNonKontrak::with('orderDetail')
                ->whereNotIn('flag_status', ['void', 'rejected'])
                ->where('is_active', true);

            if ($request->has('no_document')) {
                $dataList = $dataList->whereIn('no_document', $request->no_document);
            }
            $dataList = $dataList->orderByDesc('id')
                ->get();


            $processedCount = 0;
            $errorCount = 0;

            foreach ($dataList as $data) {
                DB::beginTransaction();

                try {
                    $pendukung_sampling = json_decode($data->data_pendukung_sampling, true);

                    if (empty($pendukung_sampling) || !is_array($pendukung_sampling)) {
                        DB::rollBack();
                        continue;
                    }

                    $sortedOrderDetail = null;
                    $hasOrderDetail = $data->orderDetail->isNotEmpty();
                    if ($hasOrderDetail) {
                        $sortedOrderDetail = $data->orderDetail->map(function ($item) {
                            // Sort regulasi
                            if (!empty($item->regulasi)) {
                                $regulasi = json_decode($item->regulasi, true);
                                if (is_array($regulasi)) {
                                    $regulasi = array_values(array_filter($regulasi));
                                    sort($regulasi);
                                    $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                }
                            }

                            // Sort parameter
                            if (!empty($item->parameter)) {
                                $parameter = json_decode($item->parameter, true);
                                if (is_array($parameter)) {
                                    sort($parameter);
                                    $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                }
                            }

                            return $item;
                        });
                    } else if (!empty($data->data_lama) && !$hasOrderDetail) {
                        $dataLama = json_decode($data->data_lama);
                        if (isset($dataLama->no_order)) {
                            $orderLama = OrderDetail::where('no_order', $dataLama->no_order)->where('is_active', true)->get();
                            $sortedOrderDetail = $orderLama->map(function ($item) {
                                // Sort regulasi
                                if (!empty($item->regulasi)) {
                                    $regulasi = json_decode($item->regulasi, true);
                                    if (is_array($regulasi)) {
                                        $regulasi = array_values(array_filter($regulasi));
                                        sort($regulasi);
                                        $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                    }
                                }

                                // Sort parameter
                                if (!empty($item->parameter)) {
                                    $parameter = json_decode($item->parameter, true);
                                    if (is_array($parameter)) {
                                        sort($parameter);
                                        $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                    }
                                }

                                return $item;
                            });
                        }
                    }

                    $lastSampel = 0;
                    if ($sortedOrderDetail) {
                        // Proses dengan order detail
                        $highestSampel = (int) substr($sortedOrderDetail->max('no_sampel'), -3) ?: 0;
                        $lastSampel = max($lastSampel, $highestSampel);
                        $substract = [];
                        foreach ($pendukung_sampling as &$detail) {

                            if (!isset($detail['kategori_1'], $detail['kategori_2'], $detail['parameter'], $detail['jumlah_titik'])) {
                                continue;
                            }

                            // Sort regulasi dan parameter
                            $regulasi = $detail['regulasi'] ?? [];
                            $parameter = $detail['parameter'] ?? [];

                            if (is_array($regulasi)) {
                                $regulasi = array_values(array_filter($regulasi));
                                sort($regulasi);
                            }

                            if (is_array($parameter)) {
                                $parameter = array_values($parameter);
                                sort($parameter);
                            }

                            $regulasiJson = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                            $parameterJson = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));

                            // Filter order detail
                            // $order_detail = $sortedOrderDetail
                            //     ->whereNotIn('no_sampel', $substract)
                            //     ->where('regulasi', $regulasiJson)
                            //     ->where('kategori_2', $detail['kategori_1'])
                            //     ->where('kategori_3', $detail['kategori_2'])
                            //     ->where('parameter', $parameterJson)
                            //     ->where('is_active', true)
                            //     ->values();

                            // Filter order detail sesuai criteria
                            $order_detail = $sortedOrderDetail
                                ->whereNotIn('no_sampel', $substract)
                                ->where('is_active', true)
                                // ->where('regulasi', $regulasiJson)
                                ->where('kategori_2', $detail['kategori_1'])
                                ->where('kategori_3', $detail['kategori_2'])
                                // ->where('parameter', $parameterJson)
                                ->values();

                            // Filter regulasi
                            $order_detail = $order_detail->filter(function ($item) use ($regulasi) {
                                $item_regulasi = json_decode($item->regulasi, true) ?? [];
                                return count(array_intersect($item_regulasi, $regulasi)) > 0;
                            });

                            // Filter parameter
                            $order_detail = $order_detail->filter(function ($item) use ($parameter) {
                                $item_parameter = json_decode($item->parameter, true) ?? [];
                                return count(array_intersect($item_parameter, $parameter)) > 0;
                            });

                            // Reset key index
                            $order_detail = $order_detail->values();

                            $penamaan_titik = [];
                            $jumlah_titik = (int) $detail['jumlah_titik'];
                            $defaultNaming = is_string($detail['penamaan_titik']) ? $detail['penamaan_titik'] : "";

                            // Penamaan titik sebanyak jumlah titik
                            for ($i = 0; $i < $jumlah_titik; $i++) {
                                $order = $order_detail[$i] ?? null;
                                if ($order && !empty($order->no_sampel)) {
                                    $parts = explode('/', $order->no_sampel);
                                    if (count($parts) >= 2) {
                                        $no_sample = $parts[1];
                                        $keterangan = !empty($order->keterangan_1) ? $order->keterangan_1 : $defaultNaming;
                                        $penamaan_titik[] = (object) [$no_sample => $keterangan];
                                        $substract[] = $order->no_sampel;
                                    }

                                    $lastSampel = max($lastSampel, (int) $parts[1]);
                                } else {
                                    $lastSampel++;
                                    $no_sample = str_pad($lastSampel, 3, '0', STR_PAD_LEFT);
                                    $penamaan_titik[] = (object) [$no_sample => $defaultNaming];
                                }
                            }

                            $detail['penamaan_titik'] = $penamaan_titik;
                        }
                    } else {
                        // Proses tanpa order detail
                        foreach ($pendukung_sampling as &$detail) {
                            if (!isset($detail['jumlah_titik'])) {
                                continue;
                            }

                            $jumlah_titik = (int) $detail['jumlah_titik'];
                            $defaultNaming = is_string($detail['penamaan_titik']) ? $detail['penamaan_titik'] : "";

                            // Penamaan titik sebanyak jumlah titik
                            $penamaan_titik = array_map(function ($i) use ($lastSampel, $defaultNaming) {
                                $no_sample = str_pad(($lastSampel + $i), 3, '0', STR_PAD_LEFT);
                                return (object) [$no_sample => $defaultNaming];
                            }, range(1, $jumlah_titik));

                            $detail['penamaan_titik'] = $penamaan_titik;
                            $lastSampel += $jumlah_titik;
                        }
                    }

                    // Update data
                    $data->data_pendukung_sampling = json_encode($pendukung_sampling, JSON_UNESCAPED_UNICODE);
                    $data->save();

                    DB::commit();
                    $processedCount++;
                } catch (Exception $e) {
                    DB::rollBack();
                    $errorCount++;

                    Log::error('Error processing document: ' . $data->no_document, [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine(),
                        'file' => $e->getFile()
                    ]);

                    continue;
                }
            }

            return response()->json([
                'message' => 'Proses selesai',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => $dataList->count()
            ], 200);
        } catch (Exception $e) {
            Log::error('Critical error in changeDataPendukungSamplingNonKontrak: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function changeDataPendukungSamplingKontrak(Request $request)
    {
        // dd($request->all());
        try {
            $dataList = QuotationKontrakH::with('orderDetail', 'quotationKontrakD')
                ->whereNotIn('flag_status', ['void', 'rejected'])
                ->where('is_active', true);

            if ($request->has('no_document')) {
                $dataList = $dataList->whereIn('no_document', $request->no_document);
            }

            $dataList = $dataList->orderByDesc('id')
                ->get();

            $processedCount = 0;
            $errorCount = 0;

            foreach ($dataList as $data) {
                DB::beginTransaction();
                try {
                    // Cek basic data
                    if (!$data->data_pendukung_sampling || !$data->quotationKontrakD) {
                        DB::rollBack();
                        $errorCount++;
                        continue;
                    }

                    $pendukung_sampling_header = json_decode($data->data_pendukung_sampling, true);
                    if (!$pendukung_sampling_header) {
                        DB::rollBack();
                        $errorCount++;
                        continue;
                    }

                    $detailList = $data->quotationKontrakD;
                    $hasOrderDetail = $data->orderDetail && $data->orderDetail->isNotEmpty();

                    // Order Detail
                    $sortedOrderDetail = null;
                    if ($hasOrderDetail) {
                        $sortedOrderDetail = $data->orderDetail->map(function ($item) {
                            // Sort regulasi
                            if ($item->regulasi) {
                                $regulasi = json_decode($item->regulasi, true);
                                if (is_array($regulasi)) {
                                    $regulasi = array_values(array_filter($regulasi));
                                    sort($regulasi);
                                    $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                }
                            }

                            // Sort parameter
                            if ($item->parameter) {
                                $parameter = json_decode($item->parameter, true);
                                if (is_array($parameter)) {
                                    sort($parameter);
                                    $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                }
                            }

                            return $item;
                        });
                    } else if (!empty($data->data_lama) && !$hasOrderDetail) {
                        $dataLama = json_decode($data->data_lama, true);
                        if ($dataLama && isset($dataLama['no_order'])) {
                            $orderLama = OrderDetail::where('no_order', $dataLama['no_order'])->get();
                            if ($orderLama->isNotEmpty()) {
                                $sortedOrderDetail = $orderLama->map(function ($item) {
                                    // Sort regulasi
                                    if ($item->regulasi) {
                                        $regulasi = json_decode($item->regulasi, true);
                                        if (is_array($regulasi)) {
                                            $regulasi = array_values(array_filter($regulasi));
                                            sort($regulasi);
                                            $item->regulasi = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                        }
                                    }

                                    // Sort parameter
                                    if ($item->parameter) {
                                        $parameter = json_decode($item->parameter, true);
                                        if (is_array($parameter)) {
                                            sort($parameter);
                                            $item->parameter = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));
                                        }
                                    }

                                    return $item;
                                });
                            }
                        }
                    }
                    // dd($detailList);
                    // Proses quotation kontrak detail
                    $lastSampel = 0;
                    foreach ($detailList as $detail) {
                        if (!$detail->data_pendukung_sampling)
                            continue;

                        $pendukung_sampling_detail = json_decode($detail->data_pendukung_sampling, true);
                        if (!$pendukung_sampling_detail)
                            continue;

                        $key = array_key_first($pendukung_sampling_detail);
                        if (!$key || !isset($pendukung_sampling_detail[$key]['data_sampling']))
                            continue;

                        $periode = $pendukung_sampling_detail[$key]['periode_kontrak'];
                        $dataSampling = &$pendukung_sampling_detail[$key]['data_sampling'];

                        if ($sortedOrderDetail && $sortedOrderDetail->isNotEmpty()) {
                            $highestSampel = 0;
                            foreach ($sortedOrderDetail as $order) {
                                if ($order->no_sampel) {
                                    $parts = explode('/', $order->no_sampel);
                                    if (count($parts) >= 2 && is_numeric($parts[1])) {
                                        $highestSampel = max($highestSampel, (int) $parts[1]);
                                    }
                                }
                            }
                            $lastSampel = max($lastSampel, $highestSampel);

                            $substract = [];
                            foreach ($dataSampling as &$detailSampling) {
                                if (
                                    !isset(
                                        $detailSampling['kategori_1'],
                                        $detailSampling['kategori_2'],
                                        $detailSampling['parameter'],
                                        $detailSampling['jumlah_titik']
                                    )
                                ) {
                                    continue;
                                }

                                // Sort regulasi dan parameter
                                $regulasi = $detailSampling['regulasi'] ?? [];
                                $parameter = $detailSampling['parameter'] ?? [];

                                if (is_array($regulasi)) {
                                    $regulasi = array_values(array_filter($regulasi));
                                    sort($regulasi);
                                } else {
                                    $regulasi = [$regulasi];
                                }

                                if (is_array($parameter)) {
                                    $parameter = array_values($parameter);
                                    sort($parameter);
                                } else {
                                    $parameter = [$parameter];
                                }

                                $regulasiJson = json_encode($regulasi, JSON_UNESCAPED_UNICODE);
                                $parameterJson = str_replace('\\', '', str_replace(',', ', ', json_encode($parameter, JSON_UNESCAPED_UNICODE)));

                                // Filter order detail sesuai criteria
                                $order_detail = $sortedOrderDetail
                                    ->whereNotIn('no_sampel', $substract)
                                    ->where('periode', $periode)
                                    ->where('is_active', true)
                                    // ->where('regulasi', $regulasiJson)
                                    ->where('kategori_2', $detailSampling['kategori_1'])
                                    ->where('kategori_3', $detailSampling['kategori_2'])
                                    // ->where('parameter', $parameterJson)
                                    ->values();

                                // Filter regulasi
                                // dump($regulasi);
                                $order_detail = $order_detail->filter(function ($item) use ($regulasi) {
                                    $item_regulasi = json_decode($item->regulasi, true) ?? [];
                                    return count(array_intersect($item_regulasi, $regulasi)) > 0;
                                });

                                // Filter parameter
                                $order_detail = $order_detail->filter(function ($item) use ($parameter) {
                                    $item_parameter = json_decode($item->parameter, true) ?? [];
                                    return count(array_intersect($item_parameter, $parameter)) > 0;
                                });

                                // Reset key index
                                $order_detail = $order_detail->values();

                                $penamaan_titik = [];
                                $jumlah_titik = (int) $detailSampling['jumlah_titik'];
                                $defaultNaming = is_string($detailSampling['penamaan_titik']) ? $detailSampling['penamaan_titik'] : "";

                                // Penamaan titik sebanyak jumlah titik detail
                                for ($i = 0; $i < $jumlah_titik; $i++) {
                                    $order = $order_detail[$i] ?? null;
                                    if ($order && $order->no_sampel) {
                                        $parts = explode('/', $order->no_sampel);
                                        if (count($parts) >= 2 && is_numeric($parts[1])) {
                                            $no_sample = str_pad((int) $parts[1], 3, '0', STR_PAD_LEFT);
                                            $keterangan = $order->keterangan_1 ?: $defaultNaming;
                                            $penamaan_titik[] = (object) [$no_sample => $keterangan];
                                            $substract[] = $order->no_sampel;
                                            $lastSampel = max($lastSampel, (int) $parts[1]);
                                        }
                                    } else {
                                        $lastSampel++;
                                        $no_sample = str_pad($lastSampel, 3, '0', STR_PAD_LEFT);
                                        $penamaan_titik[] = (object) [$no_sample => $defaultNaming];
                                    }
                                }

                                $detailSampling['penamaan_titik'] = $penamaan_titik;
                            }
                        } else {
                            // Proses tanpa order detail
                            foreach ($dataSampling as &$detailSampling) {
                                if (!isset($detailSampling['jumlah_titik']))
                                    continue;

                                $jumlah_titik = (int) $detailSampling['jumlah_titik'];
                                $defaultNaming = is_string($detailSampling['penamaan_titik']) ? $detailSampling['penamaan_titik'] : "";

                                // Penamaan titik sebanyak jumlah titik detail
                                $penamaan_titik = [];
                                for ($j = 0; $j < $jumlah_titik; $j++) {
                                    $lastSampel++;
                                    $no_sample = str_pad($lastSampel, 3, '0', STR_PAD_LEFT);
                                    $penamaan_titik[] = (object) [$no_sample => $defaultNaming];
                                }
                                $detailSampling['penamaan_titik'] = $penamaan_titik;
                            }
                        }
                        // dump($penamaan_titik);
                        // Update data detail
                        $detail->data_pendukung_sampling = json_encode($pendukung_sampling_detail, JSON_UNESCAPED_UNICODE);
                        // dump($detail->data_pendukung_sampling);
                        $detail->save();
                    }

                    // Proses Header
                    foreach ($pendukung_sampling_header as &$header) {
                        if (!isset($header['periode'], $header['jumlah_titik']))
                            continue;

                        $penamaan_sampling_all = [];

                        foreach ($header['periode'] as $periode) {
                            $kontrakDetail = $detailList->where('periode_kontrak', $periode)->first();
                            if (!$kontrakDetail)
                                continue;

                            $data_sampling_detail = json_decode($kontrakDetail->data_pendukung_sampling, true);
                            if (!$data_sampling_detail)
                                continue;

                            $data_sampling_detail = reset($data_sampling_detail)['data_sampling'] ?? [];

                            // Pencarian matched record dengan early break
                            foreach ($data_sampling_detail as $item) {
                                if (
                                    !isset($item['kategori_1'], $item['kategori_2']) ||
                                    !isset($header['kategori_1'], $header['kategori_2'])
                                ) {
                                    continue;
                                }

                                // Normalisasi array
                                $itemRegulasi = $item['regulasi'] ?? [];
                                $itemParameter = $item['parameter'] ?? [];
                                $headerRegulasi = $header['regulasi'] ?? [];
                                $headerParameter = $header['parameter'] ?? [];

                                if (is_array($itemRegulasi)) {
                                    $itemRegulasi = array_values(array_filter($itemRegulasi));
                                    sort($itemRegulasi);
                                }
                                if (is_array($itemParameter))
                                    sort($itemParameter);
                                if (is_array($headerRegulasi)) {
                                    $headerRegulasi = array_values(array_filter($headerRegulasi));
                                    sort($headerRegulasi);
                                }
                                if (is_array($headerParameter))
                                    sort($headerParameter);

                                if (
                                    $item['kategori_1'] === $header['kategori_1'] &&
                                    $item['kategori_2'] === $header['kategori_2'] &&
                                    $itemRegulasi === $headerRegulasi &&
                                    $itemParameter === $headerParameter
                                ) {

                                    $penamaan_sampling_all[] = $item['penamaan_titik'];
                                    break; // Early break
                                }
                            }
                        }

                        // Filter penamaan titik
                        $penamaan_sampling_all = array_filter($penamaan_sampling_all, function ($group) {
                            if (!is_array($group))
                                return false;
                            foreach ($group as $item) {
                                if (is_array($item) || is_object($item)) {
                                    foreach ($item as $value) {
                                        if (!empty($value))
                                            return true;
                                    }
                                }
                            }
                            return false;
                        });

                        // Proses penamaan titik Header
                        if ($penamaan_sampling_all) {
                            $penamaan_sampling = array_map(function ($item) {
                                return array_values($item)[0] ?? "";
                            }, reset($penamaan_sampling_all));
                        } else {
                            $penamaan_sampling = array_fill(0, $header['jumlah_titik'], "");
                        }

                        $header['penamaan_titik'] = $penamaan_sampling;
                    }

                    // Update data utama
                    $data->data_pendukung_sampling = json_encode($pendukung_sampling_header, JSON_UNESCAPED_UNICODE);
                    $data->save();
                    // dd('stop');
                    $processedCount++;
                    DB::commit();
                } catch (Throwable $e) {
                    dd($e);
                    DB::rollBack();
                    $errorCount++;
                    Log::error('Error processing document: ' . $data->no_document, [
                        'error' => $e->getMessage(),
                        'line' => $e->getLine()
                    ]);
                    continue;
                }
            }

            return response()->json([
                'message' => 'Process completed',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => $dataList->count()
            ], 200);
        } catch (Exception $e) {
            Log::error('Critical error in changeDataPendukungSamplingKontrak: ' . $e->getMessage(), [
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $e->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function fillPersiapanHeaderSample()
    {
        DB::beginTransaction();
        try {
            $dataList = PersiapanSampelHeader::with('psDetail')
                // ->whereNull('no_sampel')
                // ->whereRaw('CAST(tanggal_sampling AS DATE) < CURDATE()')
                // ->limit(5)
                ->get();

            foreach ($dataList as &$data) {
                if (!empty(json_decode($data->no_sampel, true)))
                    continue;
                $psDetail = $data->psDetail->pluck('no_sampel')->toArray();
                $data->no_sampel = !empty($psDetail) ? json_encode($psDetail, JSON_UNESCAPED_SLASHES) : null;
                $data->save();
            }
            // dd($dataList);
            DB::commit();
            return response()->json([
                'message' => 'Success',
            ], 200);
        } catch (Throwable $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error : ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function rollbackOrderDetailNonKontrak(Request $request)
    {
        DB::beginTransaction();
        try {
            $dataQuotation = QuotationNonKontrak::where('no_document', $request->no_document)->where('is_active', false)->first();
            $no_order = json_decode($dataQuotation->data_lama)->no_order ?? null;
            // dd($no_order);
            if (!$dataQuotation) {
                return response()->json(['message' => 'Data not found'], 404);
            }

            $dataPendukungSampling = json_decode($dataQuotation->data_pendukung_sampling);
            foreach ($dataPendukungSampling as $dps) {
                foreach ($dps->penamaan_titik as $pt) {
                    $props = get_object_vars($pt);
                    $noUrutSampel = key($props);
                    $namaTitik = $props[$noUrutSampel];
                    $noSampel = "$no_order/$noUrutSampel";

                    $orderDetail = OrderDetail::where('no_sampel', $noSampel)->first();
                    // dd($orderDetail);
                    // UPDATE ORDER DETAIL
                    if ($orderDetail) {
                        // $search_kategori = '%' . \explode('-', $dps->kategori_2)[1] . ' - ' . substr($orderDetail->no_sampel, -3) . '%';
                        // $cek_jadwal = Jadwal::where('no_quotation', $dataQuotation->no_document)
                        //     ->where('is_active', 1)
                        //     ->where('kategori', 'like', $search_kategori)
                        //     ->select('tanggal', 'kategori')
                        //     ->groupBy('tanggal', 'kategori')
                        //     ->first();

                        $orderDetail->keterangan_1 = $namaTitik;
                        $orderDetail->kategori_2 = $dps->kategori_1;
                        $orderDetail->kategori_3 = $dps->kategori_2;
                        $orderDetail->parameter = json_encode($dps->parameter);
                        $orderDetail->regulasi = json_encode($dps->regulasi);
                        $orderDetail->updated_at = Carbon::now()->format('Y-m-d H:i:s');
                        $orderDetail->save();
                    }
                }
            }

            DB::commit();
            return response()->json([
                'message' => 'Success',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            dd($e);
            return response()->json([
                'message' => 'Error : ' . $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function generatePersiapanHeader(Request $request)
    {
        try {
            $periode_awal = Carbon::parse($request->periode_awal); // format dari frontend YYYY-MM
            $periode_akhir = Carbon::parse($request->periode_akhir)->endOfMonth(); // mengambil tanggal terakhir dari bulan terpilih
            // $interval = $periode_awal->diff($periode_akhir);
            // // dd($periode_awal, $periode_akhir, $interval->days);
            // if ($interval->days > 31)
            //     return response()->json(['message' => 'Periode tidak boleh lebih dari 1 bulan'], 403);

            $data = OrderDetail::with([
                'orderHeader:id,tanggal_order,nama_perusahaan,konsultan,no_document,alamat_sampling,nama_pic_order,nama_pic_sampling,no_tlp_pic_sampling,jabatan_pic_sampling,jabatan_pic_order,is_revisi',
                'orderHeader.persiapanHeader',
                'orderHeader.samplingPlan',
                'orderHeader.samplingPlan.jadwal' => function ($q) {
                    $q->select(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang', DB::raw('GROUP_CONCAT(DISTINCT sampler SEPARATOR ",") AS sampler')])
                        ->where('is_active', true)
                        ->groupBy(['id_sampling', 'kategori', 'tanggal', 'durasi', 'jam_mulai', 'jam_selesai', 'id_cabang']);
                }
            ])
                ->select(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling'])
                ->where('is_active', true)
                // ->where('order_detail.no_order', 'DTPE012501')
                // ->where('order_detail.tanggal_sampling', '2025-08-11')
                ->whereHas('orderHeader', function ($q) {
                    $q->where('is_revisi', false);
                })
                ->whereBetween('tanggal_sampling', [
                    $periode_awal->format('Y-m-01'),
                    $periode_akhir->format('Y-m-t')
                ])
                ->whereNotExists(function ($query) {
                    $query->select(DB::raw(1))
                        ->from('persiapan_sampel_header')
                        ->whereColumn('persiapan_sampel_header.no_order', 'order_detail.no_order')
                        ->whereColumn('persiapan_sampel_header.tanggal_sampling', 'order_detail.tanggal_sampling')
                        ->where('persiapan_sampel_header.is_active', true);
                })
                ->groupBy(['id_order_header', 'no_order', 'kategori_2', 'kategori_3', 'periode', 'tanggal_sampling']);

            $data = $data->get()->toArray();

            $formattedData = array_reduce($data, function ($carry, $item) {

                if (empty($item['order_header']) || empty($item['order_header']['sampling']))
                    return $carry;

                $samplingPlan = $item['order_header']['sampling'];
                $periode = $item['periode'] ?? '';

                $targetPlan = $periode ?
                    current(array_filter(
                        $samplingPlan,
                        fn($plan) =>
                        isset($plan['periode_kontrak']) && $plan['periode_kontrak'] == $periode
                    )) :

                    current($samplingPlan);

                if (!$targetPlan)
                    return $carry;

                $jadwal = $targetPlan['jadwal'] ?? [];
                $results = [];

                foreach ($jadwal as $schedule) {
                    if ($schedule['tanggal'] == $item['tanggal_sampling']) {
                        $results[] = [
                            'nomor_quotation' => $item['order_header']['no_document'] ?? '',
                            'nama_perusahaan' => $item['order_header']['nama_perusahaan'] ?? '',
                            'status_sampling' => $item['kategori_1'] ?? '',
                            'periode' => $periode,
                            'jadwal' => $schedule['tanggal'],
                            'jadwal_jam_mulai' => $schedule['jam_mulai'],
                            'jadwal_jam_selesai' => $schedule['jam_selesai'],
                            'kategori' => implode(',', json_decode($schedule['kategori'], true) ?? []),
                            'sampler' => $schedule['sampler'] ?? '',
                            'no_order' => $item['no_order'] ?? '',
                            'alamat_sampling' => $item['order_header']['alamat_sampling'] ?? '',
                            'konsultan' => $item['order_header']['konsultan'] ?? '',
                            'info_pendukung' => json_encode([
                                'nama_pic_order' => $item['order_header']['nama_pic_order'],
                                'nama_pic_sampling' => $item['order_header']['nama_pic_sampling'],
                                'no_tlp_pic_sampling' => $item['order_header']['no_tlp_pic_sampling'],
                                'jabatan_pic_sampling' => $item['order_header']['jabatan_pic_sampling'],
                                'jabatan_pic_order' => $item['order_header']['jabatan_pic_order']
                            ]),
                            'info_sampling' => json_encode([
                                'id_request' => $targetPlan['quotation_id'],
                                'status_quotation' => $targetPlan['status_quotation']
                            ]),
                            'is_revisi' => $item['order_header']['is_revisi'],
                            'id_cabang' => $schedule['id_cabang'] ?? null,
                            'nama_cabang' => isset($schedule['id_cabang']) ? (
                                $schedule['id_cabang'] == 4 ? 'RO-KARAWANG' : ($schedule['id_cabang'] == 5 ? 'RO-PEMALANG' : ($schedule['id_cabang'] == 1 ? 'HEAD OFFICE' : 'UNKNOWN'))
                            ) : 'HEAD OFFICE (Default)',
                        ];
                    }
                }

                return array_merge($carry, $results);
            }, []);

            $groupedData = [];
            foreach ($formattedData as $item) {
                $key = implode('|', [
                    $item['nomor_quotation'],
                    $item['nama_perusahaan'],
                    $item['status_sampling'],
                    $item['periode'],
                    $item['jadwal'],
                    $item['no_order'],
                    $item['alamat_sampling'],
                    $item['konsultan'],
                    $item['kategori'],
                    $item['info_pendukung'],
                    $item['jadwal_jam_mulai'],
                    $item['jadwal_jam_selesai'],
                    $item['info_sampling'],
                    $item['nama_cabang'] ?? '',
                ]);

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'nomor_quotation' => $item['nomor_quotation'],
                        'nama_perusahaan' => $item['nama_perusahaan'],
                        'status_sampling' => $item['status_sampling'],
                        'periode' => $item['periode'],
                        'jadwal' => $item['jadwal'],
                        'kategori' => $item['kategori'],
                        'sampler' => $item['sampler'],
                        'no_order' => $item['no_order'],
                        'alamat_sampling' => $item['alamat_sampling'],
                        'konsultan' => $item['konsultan'],
                        'info_pendukung' => $item['info_pendukung'],
                        'jadwal_jam_mulai' => $item['jadwal_jam_mulai'],
                        'jadwal_jam_selesai' => $item['jadwal_jam_selesai'],
                        'info_sampling' => $item['info_sampling'],
                        'is_revisi' => $item['is_revisi'],
                        'nama_cabang' => $item['nama_cabang'] ?? '',
                    ];
                } else {
                    $groupedData[$key]['sampler'] .= ',' . $item['sampler'];
                }

                $uniqueSampler = explode(',', $groupedData[$key]['sampler']);
                $uniqueSampler = array_unique($uniqueSampler);
                $groupedData[$key]['sampler'] = implode(',', $uniqueSampler);
            }

            $finalResult = array_values($groupedData);
            // dd(count($finalResult)); // 461 total di produksi saat ini running

            function flattenUnique(array $arr, $defaultValue = "2"): array
            {
                $flattened = array_values(array_unique(
                    array_reduce($arr, function ($carry, $item) {
                        return array_merge($carry, $item);
                    }, [])
                ));

                return array_fill_keys($flattened, $defaultValue);
            }

            $processedCount = 0;
            $errorCount = 0;
            foreach ($finalResult as $data) {
                // try {
                $parsed = array_map(function ($value) {
                    return explode(' - ', $value);
                }, explode(',', $data['kategori']));

                $no_sampel = array_map(function ($item) use ($data) {
                    return $data['no_order'] . "/" . $item;
                }, array_column($parsed, 1));

                $psController = new PersiapanSampleController($request);
                $requestPreview = new Request([
                    "no_document" => $data['nomor_quotation'],
                    "no_order" => $data['no_order'],
                    "periode" => $data['periode'] ?: null,
                    "no_sampel" => $no_sampel,
                ]);

                $previewResp = $psController->preview($requestPreview);
                $previews = json_decode($previewResp->getContent(), true);

                $detail = [];
                $tambahan_parameters = [];
                foreach ($previews as $keys => $values) {
                    if ($keys == 'masker') {
                        continue;
                    }
                    if (empty($values)) {
                        continue;
                    }

                    foreach ($values as $value) {
                        if (empty($value['parameters'])) {
                            continue;
                        }

                        $tambahan_parameters[$keys][] = array_keys($value['parameters']);

                        $parameters = array_map(function ($item) {
                            return array_diff_key($item, ['buffer' => '']);
                        }, $value['parameters']);

                        $detail[$value['no_sampel']][$keys] = $parameters;
                    }
                }

                if (isset($tambahan_parameters['air'])) {
                    $tambahan_parameters['air'] = flattenUnique($tambahan_parameters['air']);
                }
                if (isset($tambahan_parameters['udara'])) {
                    $tambahan_parameters['udara'] = flattenUnique($tambahan_parameters['udara']);
                }

                $requestSave = new Request([
                    "no_order" => $data['no_order'],
                    "no_quotation" => $data['nomor_quotation'],
                    "tanggal_sampling" => $data['jadwal'],
                    "nama_perusahaan" => $data['nama_perusahaan'],
                    "periode" => $data['periode'] ?: null,
                    "detail" => !empty($detail) ? $detail : null,
                    "tambahan" => !empty($tambahan_parameters) ? $tambahan_parameters : null,
                    "plastik_benthos" => [
                        "disiapkan" => "",
                        "tambahan" => ""
                    ],
                    "media_petri_dish" => [
                        "disiapkan" => "",
                        "tambahan" => ""
                    ],
                    "media_tabung" => [
                        "disiapkan" => "",
                        "tambahan" => ""
                    ],
                    "masker" => [
                        "disiapkan" => "2",
                        "tambahan" => ""
                    ],
                    "sarung_tangan_karet" => [
                        "disiapkan" => "2",
                        "tambahan" => ""
                    ],
                    "sarung_tangan_bintik" => [
                        "disiapkan" => "2",
                        "tambahan" => ""
                    ],
                    "analis_berangkat" => "",
                    "sampler_berangkat" => "",
                    "analis_pulang" => "",
                    "sampler_pulang" => ""
                ]);

                // dd($requestSave->all());
                $saveResp = $psController->save($requestSave);
                if ($saveResp->getStatusCode() == 200) {
                    $processedCount++;
                } else {
                    $errorCount++;
                }
                // } catch (Throwable $th) {
                //     $errorCount++;
                //     continue;
                // }
            }

            return response()->json([
                'message' => 'Process completed',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => count($finalResult)
            ], 200);
        } catch (Throwable $th) {
            dd($th);
            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $th->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function changeParameter(Request $request)
    {
        try {
            $kontrak = QuotationKontrakH::with('orderDetail', 'quotationKontrakD')
                ->whereHas('orderDetail', function ($q) {
                    $q->where('kategori_2', '1-Air')
                        ->where('tanggal_sampling', '>=', '2025-07-09')
                        ->where('is_active', true);
                })
                ->whereIn('no_document', $request->no_document)
                ->where('is_active', true)
                ->get();

            $nonKontrak = QuotationNonKontrak::with('orderDetail')
                ->whereHas('orderDetail', function ($q) {
                    $q->where('kategori_2', '1-Air')
                        ->where('tanggal_sampling', '>=', '2025-07-09')
                        ->where('is_active', true);
                })
                ->whereIn('no_document', $request->no_document)
                ->where('is_active', true)
                ->get();

            $dataList = $kontrak->merge($nonKontrak);
            // $dataList = $nonKontrak;

            // dd($dataList);
            $masterBakumutu = MasterBakumutu::where('is_active', true);

            $processedCount = 0;
            $errorCount = 0;
            $errorDetails = [];

            foreach ($dataList as $data) {
                DB::beginTransaction();
                try {
                    $cache = [];
                    $updated = false;

                    if (str_contains($data->no_document, '/QTC/')) {
                        // 1. PROSES HEADER
                        $dpsHeader = json_decode($data->data_pendukung_sampling);
                        if ($dpsHeader) {
                            foreach ($dpsHeader as $header) {
                                if ($header->kategori_1 === '1-Air') {
                                    $regulasiId = explode('-', reset($header->regulasi))[0];

                                    if ($masterBakumutu->where('id_regulasi', $regulasiId)->doesntExist()) {
                                        continue; // Skip jika regulasi tidak ditemukan
                                    }

                                    $bakumutu = $masterBakumutu->where('id_regulasi', $regulasiId)->get()->toArray();

                                    $parameter_new = array_map(function ($item) {
                                        return $item['id_parameter'] . ';' . $item['parameter'];
                                    }, $bakumutu);

                                    $parameter_old = $header->parameter ?? [];
                                    // dd($parameter_new, $parameter_old);

                                    if (array_diff($parameter_old, $parameter_new) || array_diff($parameter_new, $parameter_old)) {
                                        $still = array_values(array_intersect($parameter_new, $parameter_old));
                                        $replace = array_values(array_diff($parameter_old, $still));
                                        $newParams = array_values(array_diff($parameter_new, $parameter_old));

                                        $replacement = $this->getReplacement($newParams, $replace);
                                        // dd($still, $replace, $newParams, $replacement);

                                        // $temp = $this->getSimilarParameter($newParams, $replace);
                                        // $mirip = $temp['mirip'];
                                        // $tidakAda = $temp['tidakAda'];

                                        // $replacement = array_merge(array_column($mirip, 'baru'), $tidakAda);

                                        $header->parameter = array_merge($still, $replacement);
                                        $updated = true;
                                    }

                                    // Cache untuk periode
                                    if (isset($header->periode)) {
                                        foreach ($header->periode as $periode) {
                                            $cache[$header->kategori_2][reset($header->regulasi)][$periode] = $header->parameter;
                                        }
                                    }
                                }
                            }

                            if ($updated) {
                                // Hanya save jika ada perubahan, struktur data tetap sama
                                $data->data_pendukung_sampling = json_encode($dpsHeader, JSON_UNESCAPED_UNICODE);
                                $data->save();
                            }
                        }

                        // 2. PROSES DETAIL
                        $dataDetail = $data->quotationKontrakD;
                        foreach ($dataDetail as $detail) {
                            $dsDetail = json_decode($detail->data_pendukung_sampling);
                            if (!$dsDetail || empty($dsDetail))
                                continue;

                            $detailUpdated = false;
                            $firstDetail = reset($dsDetail);
                            $dsPeriode = $firstDetail->periode_kontrak ?? null;
                            $dsData = $firstDetail->data_sampling ?? [];

                            foreach ($dsData as $ds) {
                                if ($ds->kategori_1 === '1-Air') {
                                    $regulasiKey = reset($ds->regulasi);

                                    // Cek cache terlebih dahulu
                                    if (isset($cache[$ds->kategori_2][$regulasiKey][$dsPeriode])) {
                                        $ds->parameter = $cache[$ds->kategori_2][$regulasiKey][$dsPeriode];
                                        $detailUpdated = true;
                                    } else {
                                        $regulasiId = explode('-', $regulasiKey)[0];

                                        if ($masterBakumutu->where('id_regulasi', $regulasiId)->exists()) {
                                            $bakumutu = $masterBakumutu->where('id_regulasi', $regulasiId)->get()->toArray();

                                            $parameter_new = array_map(function ($item) {
                                                return $item['id_parameter'] . ';' . $item['parameter'];
                                            }, $bakumutu);

                                            $parameter_old = $ds->parameter ?? [];

                                            // Cek apakah ada perubahan
                                            if (array_diff($parameter_old, $parameter_new) || array_diff($parameter_new, $parameter_old)) {
                                                $still = array_values(array_intersect($parameter_new, $parameter_old));
                                                $replace = array_values(array_diff($parameter_old, $still));
                                                $newParams = array_values(array_diff($parameter_new, $parameter_old));

                                                $replacement = $this->getReplacement($newParams, $replace);

                                                // $temp = $this->getSimilarParameter($newParams, $replace);
                                                // $mirip = $temp['mirip'];
                                                // $tidakAda = $temp['tidakAda'];

                                                // $replacement = array_merge(array_column($mirip, 'baru'), $tidakAda);
                                                $ds->parameter = array_merge($still, $replacement);

                                                // Update cache
                                                $cache[$ds->kategori_2][$regulasiKey][$dsPeriode] = $ds->parameter;
                                                $detailUpdated = true;
                                            }
                                        }
                                    }
                                }
                            }

                            if ($detailUpdated) {
                                // Hanya save jika ada perubahan, struktur data tetap sama
                                $detail->data_pendukung_sampling = json_encode($dsDetail, JSON_UNESCAPED_UNICODE);
                                $detail->save();
                            }
                        }

                        // 3. PROSES ORDER
                        $dataOrder = $data->orderDetail->where('is_active', true)->where('kategori_2', '1-Air');
                        foreach ($dataOrder as $order) {
                            $regulasiDecoded = json_decode($order->regulasi, true);
                            if (!$regulasiDecoded)
                                continue;

                            $regulasiKey = reset($regulasiDecoded);
                            $kategori2 = $order->kategori_3;
                            $periode = $order->periode;

                            // Cek apakah ada parameter di cache
                            if (isset($cache[$kategori2][$regulasiKey][$periode])) {
                                $newParameter = $cache[$kategori2][$regulasiKey][$periode];

                                // Cek apakah parameter berbeda
                                $currentParameter = json_decode($order->parameter, true) ?? [];
                                if ($currentParameter !== $newParameter) {
                                    $order->parameter = json_encode($newParameter, JSON_UNESCAPED_UNICODE);
                                    $order->save();
                                }
                            }
                        }
                    } else {
                        $dpsHeader = json_decode($data->data_pendukung_sampling);
                        if ($dpsHeader) {
                            foreach ($dpsHeader as $header) {
                                if ($header->kategori_1 === '1-Air') {
                                    $regulasiId = explode('-', reset($header->regulasi))[0];

                                    if ($masterBakumutu->where('id_regulasi', $regulasiId)->doesntExist()) {
                                        continue;
                                    }

                                    $bakumutu = $masterBakumutu->where('id_regulasi', $regulasiId)->get()->toArray();

                                    $parameter_new = array_map(function ($item) {
                                        return $item['id_parameter'] . ';' . $item['parameter'];
                                    }, $bakumutu);

                                    $parameter_old = $header->parameter ?? [];
                                    // dd($parameter_new, $parameter_old);

                                    if (array_diff($parameter_old, $parameter_new) || array_diff($parameter_new, $parameter_old)) {
                                        $still = array_values(array_intersect($parameter_new, $parameter_old));
                                        $replace = array_values(array_diff($parameter_old, $still));
                                        $newParams = array_values(array_diff($parameter_new, $parameter_old));
                                        $replacement = $this->getReplacement($newParams, $replace);
                                        // dd($still, $replace, $newParams, $replacement);

                                        $header->parameter = array_merge($still, $replacement);
                                        $cache[$header->kategori_2][reset($header->regulasi)] = $header->parameter;
                                        $updated = true;
                                    }
                                }
                            }

                            if ($updated) {
                                // Hanya save jika ada perubahan, struktur data tetap sama
                                $data->data_pendukung_sampling = json_encode($dpsHeader, JSON_UNESCAPED_UNICODE);
                                $data->save();
                            }
                        }
                        // dd($cache);

                        $dataOrder = $data->orderDetail->where('is_active', true)->where('kategori_2', '1-Air');
                        foreach ($dataOrder as $order) {
                            $regulasiDecoded = json_decode($order->regulasi, true);
                            if (!$regulasiDecoded)
                                continue;

                            $regulasiKey = reset($regulasiDecoded);
                            $kategori2 = $order->kategori_3;
                            // $periode = $order->periode;

                            // Cek apakah ada parameter di cache
                            if (isset($cache[$kategori2][$regulasiKey])) {
                                $newParameter = $cache[$kategori2][$regulasiKey];

                                // Cek apakah parameter berbeda
                                $currentParameter = json_decode($order->parameter, true) ?? [];
                                // dd(json_decode($order->parameter, true), $newParameter);
                                if ($currentParameter !== $newParameter) {
                                    $order->parameter = json_encode($newParameter, JSON_UNESCAPED_UNICODE);
                                    $order->save();
                                }
                            }
                        }
                    }

                    // dd('stop');
                    DB::commit();
                    $processedCount++;
                } catch (Throwable $th) {
                    DB::rollback();
                    $errorCount++;
                    $errorDetails[] = [
                        'document' => $data->no_document,
                        'error' => $th->getMessage()
                    ];
                    \Log::error('Error processing document: ' . $data->no_document, [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString()
                    ]);
                }
            }

            return response()->json([
                'message' => 'Process completed',
                'processed' => $processedCount,
                'errors' => $errorCount,
                'total' => count($dataList),
                'error_details' => $errorDetails
            ], 200);
        } catch (Throwable $th) {
            DB::rollback();
            \Log::error('System error in changeParameter', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $th->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    private function parseParam($arr)
    {
        return array_map(function ($item) {
            $parts = explode(';', $item, 2);
            return count($parts) === 2 ? $parts : [$item, ''];
        }, $arr);
    }

    private function getReplacement($paramNew, $paramOld)
    {
        $baru = $this->parseParam($paramNew);
        $lama = $this->parseParam($paramOld);

        $idBaru = array_column($baru, 0);
        $idLama = array_column($lama, 0);

        $oldParam = Parameter::whereIn('id', $idLama)->get();
        $newParam = Parameter::whereIn('id', $idBaru)->get();

        $temp = array_intersect(
            $oldParam->pluck('nama_regulasi')->toArray(),
            $newParam->pluck('nama_regulasi')->toArray()
        );

        $diff = array_diff($oldParam->pluck('nama_regulasi')->toArray(), $newParam->pluck('nama_regulasi')->toArray());

        $new = $newParam->filter(function ($item) use ($temp) {
            return in_array($item->nama_regulasi, $temp);
        });

        $old = $oldParam->filter(function ($item) use ($diff) {
            return in_array($item->nama_regulasi, $diff);
        });

        $new = $new->map(function ($item) {
            return $item->id . ';' . $item->nama_lab;
        })->values()->toArray();

        $old = $old->map(function ($item) {
            return $item->id . ';' . $item->nama_lab;
        })->values()->toArray();

        $replacement = array_merge($old, $new);

        return $replacement;
    }

    private function cleanName($name)
    {
        return trim(preg_replace('/\s*\(.*?\)/', '', $name));
    }

    private function isMatch($old, $new)
    {
        $old = $this->cleanName($old);
        $new = $this->cleanName($new);

        $oldLower = strtolower($old);
        $newLower = strtolower($new);

        // Mapping manual (1 arah)
        $manualMap = [
            'h2s' => 's²⁻',
            'th' => 'kesadahan total',
            'nh3' => 'nh3-n',
            'f' => 'fluorida',
            'total coliform' => 'total coliform'
        ];
        if (isset($manualMap[$oldLower]) && $manualMap[$oldLower] === $newLower) {
            return 'manual';
        }

        // Exact match
        if ($oldLower === $newLower) {
            return 'exact';
        }

        // Contains match
        if (str_contains($newLower, $oldLower) || str_contains($oldLower, $newLower)) {
            return 'fuzzy';
        }

        // Initials match
        $words = explode(' ', $newLower);
        if (count($words) > 1) {
            $initials = implode('', array_map(fn($w) => $w[0] ?? '', $words));
            if ($oldLower === $initials) {
                return 'fuzzy';
            }
        }

        // Similarity match
        similar_text($oldLower, $newLower, $percent);
        return $percent >= 50 ? 'fuzzy' : 'none';
    }

    public function getSimilarParameter($paramNew, $paramOld)
    {
        $baru = $this->parseParam($paramNew);
        $lama = $this->parseParam($paramOld);

        $mirip = [];
        $tidakAda = [];
        $usedNew = [];

        foreach ($lama as $old) {
            [$oldId, $oldName] = $old;

            $bestMatch = null;
            $bestScore = 0;
            $bestIndex = -1;
            $bestType = 'none';

            foreach ($baru as $index => $new) {
                if (in_array($index, $usedNew))
                    continue;

                [$newId, $newName] = $new;

                $matchType = $this->isMatch($oldName, $newName);

                if ($matchType !== 'none') {
                    // Hitung score hanya untuk fuzzy/exact
                    $score = 0;
                    if ($matchType !== 'manual') {
                        similar_text(
                            strtolower($this->cleanName($oldName)),
                            strtolower($this->cleanName($newName)),
                            $score
                        );
                    } else {
                        $score = 100; // Manual selalu prioritas tertinggi
                    }

                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestMatch = "$newId;$newName";
                        $bestIndex = $index;
                        $bestType = $matchType;
                    }
                }
            }

            // Kalau manual → langsung masuk mirip
            // Kalau fuzzy/exact → cek score minimal 50
            if ($bestMatch && ($bestType === 'manual' || $bestScore >= 50)) {
                $mirip[] = [
                    'lama' => "$oldId;$oldName",
                    'baru' => $bestMatch,
                    'score' => round($bestScore, 2),
                    'type' => $bestType
                ];
                $usedNew[] = $bestIndex;
            } else {
                $tidakAda[] = "$oldId;$oldName";
            }
        }

        return [
            'mirip' => $mirip,
            'tidakAda' => $tidakAda
        ];
    }

    public function fixDetailStructure(Request $request)
    {

        try {
            // Ambil data yang mungkin struktur detailnya berubah
            $dataList = QuotationKontrakH::with('quotationKontrakD')
                ->whereIn('no_document', $request->no_document)
                ->where('is_active', true)
                ->get();

            $fixedCount = 0;
            $errorCount = 0;
            $errorDetails = [];

            foreach ($dataList as $data) {
                DB::beginTransaction();
                try {
                    foreach ($data->quotationKontrakD as  $detailIndex => $detail) {
                        $dsDetail = json_decode($detail->data_pendukung_sampling, true);

                        // ⚙️ 1. Cek apakah sudah sesuai struktur (sudah punya "periode_kontrak")
                        $isValidStructure = false;

                        if (is_array($dsDetail)) {
                            foreach ($dsDetail as $key => $item) {

                                // 🔹 Case 1: Struktur lama tapi sudah dikonversi (pakai key angka dan ada periode_kontrak)
                                if (is_array($item) && array_key_exists('periode_kontrak', $item)) {
                                    $isValidStructure = true;
                                    break;
                                }

                                // 🔹 Case 2: Format array langsung [{ "periode_kontrak": ..., "data_sampling": ... }]
                                if (array_key_exists('periode_kontrak', $dsDetail)) {
                                    $isValidStructure = true;
                                    break;
                                }
                            }
                        }

                        if ($isValidStructure) {
                            // ✅ Sudah sesuai struktur → skip
                            $skippedCount++;
                            continue;
                        }

                        // ⚙️ 2. Kalau belum sesuai, bentuk ulang struktur
                        $originalStructure = [
                            $detailIndex + 1 => [
                                "periode_kontrak" => $detail->periode_kontrak,
                                "data_sampling" => $dsDetail
                            ]
                        ];
                        // dd($dsDetail, $originalStructure);

                        $detail->data_pendukung_sampling = json_encode($originalStructure);
                        $detail->save();
                        $index++;
                        $fixedCount++;
                    }

                    // dd($dataDetail);

                    DB::commit();
                } catch (Throwable $th) {
                    DB::rollback();
                    $errorCount++;
                    $errorDetails[] = [
                        'document' => $data->no_document,
                        'error' => $th->getMessage()
                    ];
                    Log::error('Error fixing detail structure: ' . $data->no_document, [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString()
                    ]);
                }
            }

            return response()->json([
                'message' => 'Fix detail structure completed',
                'fixed' => $fixedCount,
                'errors' => $errorCount,
                'total_documents' => count($dataList),
                'error_details' => $errorDetails
            ], 200);
        } catch (Throwable $th) {
            DB::rollback();
            Log::error('System error in fixDetailStructure', [
                'error' => $th->getMessage(),
                'trace' => $th->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Terjadi kesalahan sistem',
                'error' => app()->environment('local') ? $th->getMessage() : 'Internal Server Error'
            ], 500);
        }
    }

    public function generatePersiapan(Request $request)
    {

        DB::beginTransaction();
        try {
            // dd($request->all());
            $data = OrderDetail::where('is_active', 1)
                ->where('no_order', $request->no_order)
                ->where(function ($query) {
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
            // dd($data);
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
                        dump($value);
                        $koding = $value->koding_sampling . strtoupper(Str::random(5));

                        // Hitung jumlah botol yang dibutuhkan
                        $jumlah_botol = ceil($volume / $ketentuan_botol[$type]);

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

            return response()->json(['message' => 'Success'], 200);
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

    public function updateQTKelengkapan(Request $request)
    {
        try {
            $dataList = KelengkapanKonfirmasiQs::select('id', 'no_quotation', 'id_quotation')
                ->orderByDesc('id')
                ->get();

            $quotationKontrakLookup = QuotationKontrakH::where('is_active', true)
                ->select('id', 'no_document')
                ->get()
                ->keyBy('no_document');

            $quotationNonKontrakLookup = QuotationNonKontrak::where('is_active', true)
                ->select('id', 'no_document')
                ->get()
                ->keyBy('no_document');

            $processedCount = 0;
            $errorCount = 0;

            foreach ($dataList as $kelengkapan) {
                DB::beginTransaction();
                try {
                    $no_document = preg_replace('/R\d+$/', '', $kelengkapan->no_quotation);

                    $matchedData = null;

                    if (str_contains($no_document, 'QTC')) {
                        foreach ($quotationKontrakLookup as $docNo => $item) {
                            if (str_contains($docNo, $no_document)) {
                                $matchedData = $item;
                                break;
                            }
                        }
                    } else {
                        foreach ($quotationNonKontrakLookup as $docNo => $item) {
                            if (str_contains($docNo, $no_document)) {
                                $matchedData = $item;
                                break;
                            }
                        }
                    }

                    if (!$matchedData || $kelengkapan->no_quotation === $matchedData->no_document) {
                        DB::rollBack();
                        continue;
                    }

                    $kelengkapan->id_quotation = $matchedData->id;
                    $kelengkapan->no_quotation = $matchedData->no_document;
                    $kelengkapan->save();

                    $processedCount++;
                    DB::commit();
                } catch (Throwable $th) {
                    $errorCount++;
                    DB::rollBack();
                    Log::error('Error processing kelengkapan ID: ' . $kelengkapan->id, [
                        'error' => $th->getMessage(),
                        'trace' => $th->getTraceAsString()
                    ]);
                    continue;
                }
            }

            return response()->json([
                'message' => 'success',
                'total' => count($dataList),
                'processed' => $processedCount,
                'errors' => $errorCount
            ], 200);
        } catch (Throwable $e) {
            Log::error('Error in updateQTKelengkapan', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'error',
                'error' => $e->getMessage()
            ], 500);
        }
    }


    public function numberingLhpOrder(Request $request)
    {
        DB::beginTransaction();
        try {
            $order_detail = OrderDetail::where('no_order', $request->no_order)->get();

            if ($order_detail->isEmpty()) {
                return response()->json([
                    'message' => 'No Order Tidak Ditemukan'
                ], 404);
            }

            $num = "001";

            // variabel penyimpan kondisi sebelumnya
            $lastPeriode   = null;
            $lastRegulasi  = [];
            $lastKategori3 = null;

            $changes = []; // ✅ simpan perubahan detail

            foreach ($order_detail as $od) {
                $needIncrement = false;

                if ($od->kategori_2 == '1-Air' || in_array($od->kategori_3, ['11-Udara Ambient', '27-Udara Lingkungan Kerja', '34-Emisi Sumber Tidak Bergerak'])) {
                    // ✅ Aturan 1: Air -> selalu increment
                    $needIncrement = true;
                } else {
                    $od_regulasi = json_decode($od->regulasi, true) ?: [];

                    // if($od->no_sampel == 'KPJD022504/002'){
                    //     dd([$od_regulasi, $lastRegulasi, count(array_diff($od_regulasi, $lastRegulasi)), $od->periode, $lastPeriode, $od->kategori_3, $lastKategori3]);
                    // }
                    if ($od->periode) {
                        // ✅ Aturan 2: Non-Air + ada periode
                        if (
                            $od->periode !== $lastPeriode ||
                            $od->kategori_3 !== $lastKategori3 ||
                            count(array_diff($od_regulasi, $lastRegulasi)) > 0
                        ) {
                            $needIncrement = true;
                        }
                    } else {
                        // ✅ Aturan 3: Non-Air + tanpa periode
                        if (
                            $od->kategori_3 !== $lastKategori3 ||
                            count(array_diff($od_regulasi, $lastRegulasi)) > 0
                        ) {
                            $needIncrement = true;
                        }
                    }

                    // update kondisi terakhir
                    $lastPeriode   = $od->periode;
                    $lastKategori3 = $od->kategori_3;
                    $lastRegulasi  = $od_regulasi;
                }

                if (!$needIncrement) {
                    $oldCfr = $od->cfr;
                    $newCfr = $request->no_order . "/" . str_pad(((int)$num - 1), 3, "0", STR_PAD_LEFT);

                    $od->cfr = $newCfr;
                    $od->save();

                    if ($od->status > 1) {
                        $lhpsH = LhpsAirHeader::where('no_sampel', $od->no_sampel)->first();
                        if ($lhpsH) {
                            $lhpsH->no_lhp = $newCfr;
                            $lhpsH->save();
                        }
                    }

                    $changes[] = [
                        'no_sampel' => $od->no_sampel,
                        'old_cfr'   => $oldCfr,
                        'new_cfr'   => $newCfr,
                    ];
                } else {
                    $oldCfr = $od->cfr;
                    $newCfr = $request->no_order . "/" . $num;

                    $od->cfr = $newCfr;
                    $od->save();

                    if ($od->status > 1) {
                        $lhpsH = LhpsAirHeader::where('no_sampel', $od->no_sampel)->first();
                        if ($lhpsH) {
                            $lhpsH->no_lhp = $newCfr;
                            $lhpsH->save();
                        }
                    }

                    $changes[] = [
                        'no_sampel' => $od->no_sampel,
                        'old_cfr'   => $oldCfr,
                        'new_cfr'   => $newCfr,
                    ];

                    $num = str_pad((int)$num + 1, 3, "0", STR_PAD_LEFT);
                }
            }

            FacadesLog::info('Re-numbering LHP Order Berhasil', [
                'no_order' => $request->no_order,
                'changes'  => $changes, // ✅ detail perubahan per sampel
            ]);

            DB::commit();
            return response()->json([
                'message' => 'Re-numbering LHP Order Berhasil',
                'changes' => $changes, // ✅ juga bisa dikembalikan ke response
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            FacadesLog::error('Re-numbering LHP Order Gagal', [
                'error' => $e->getMessage(),
                'line'  => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'file'  => $e->getFile()
            ]);
            return response()->json([
                'message' => $e->getMessage(),
                'line'    => $e->getLine(),
                'trace'   => $e->getTraceAsString(),
                'file'    => $e->getFile()
            ], 500);
        }
    }

    // public function decodeImageToBase64($filename)
    // {
    //     // Path penyimpanan
    //     $path = public_path('dokumen/bas/signatures');

    //     // Path file lengkap
    //     $filePath = $path . '/' . $filename;

    //     // Periksa apakah file ada
    //     if (!file_exists($filePath)) {
    //         return (object) [
    //             'status' => 'error',
    //             'message' => 'File tidak ditemukan'
    //         ];
    //     }

    //     // Baca konten file
    //     $imageContent = file_get_contents($filePath);

    //     // Konversi ke base64
    //     $base64Image = base64_encode($imageContent);

    //     // Deteksi tipe file
    //     $fileType = $this->detectFileType($imageContent);

    //     // Tambahkan data URI header sesuai tipe file
    //     $base64WithHeader = 'data:image/' . $fileType . ';base64,' . $base64Image;

    //     // Kembalikan respons
    //     return (object) [
    //         'status' => 'success',
    //         'base64' => $base64WithHeader,
    //         'file_type' => $fileType
    //     ];
    // }

    private function detectFileType($fileContent)
    {
        // Signature file untuk berbagai format
        $signatures = [
            'png' => "\x89PNG\x0D\x0A\x1A\x0A",
            'jpg' => "\xFF\xD8\xFF",
            'gif' => "GIF87a",
            'webp' => "RIFF",
            'svg' => '<?xml'
        ];

        foreach ($signatures as $type => $signature) {
            if (strpos($fileContent, $signature) === 0) {
                return $type;
            }
        }

        return 'bin';
    }

    /* decode */
    private  function encryptSlice(string $data): string
    {
        if (empty($data)) {
            return $data;
        }

        return array_reduce(str_split($data), function ($carry, $char) {
            $value = ($char === ' ') ? 's_PPX1' : $char;
            return $carry . self::getEncryptionMap($value);
        }, '');
    }

    private  function decryptSlice(string $data)
    {
        if (empty($data)) {
            return $data;
        }

        return array_reduce(explode('8', $data), function ($carry, $value) {
            if ($value === '') {
                return $carry;
            }
            $decrypted = $this->getEncryptionMap($value . '8', true);
            return $carry . ($decrypted === 's_PPX1' ? ' ' : $decrypted);
        }, '');
    }

    private  function getEncryptionMap(string $char, bool $decode = false): string
    {
        $map = [
            'a' => 'ssp8',
            'b' => 's21s48',
            'c' => 'xopA8',
            'd' => 'poxik8',
            'e' => 'Tak8',
            'f' => 'MkNixy8',
            'g' => 'IdPN8',
            'h' => 'OtuYx8',
            'i' => 'OtiX8',
            'j' => 'Z23x8',
            'k' => 'Zaee8',
            'l' => 'Rx38',
            'm' => 'R418',
            'n' => 'CapR8',
            'o' => 'Mui8',
            'p' => 'DtBy8',
            'q' => 'YxBi8',
            'r' => 'BiBG8',
            's' => 'muxYb8',
            't' => 'MZx8',
            'u' => 'mnz8',
            'v' => 'mzn8',
            'w' => 'MnCC8',
            'x' => 'BnM8',
            'y' => 'BVc8',
            'z' => 'BBc8',
            'A' => 'AAxY8',
            'B' => 'IojX8',
            'C' => 'XFhG8',
            'D' => 'XH8',
            'E' => 'xG8',
            'F' => 'GGJj8',
            'G' => 'Dx8',
            'H' => 'PR8',
            'I' => 'ER8',
            'J' => 'losp8',
            'K' => 'Hgk8',
            'L' => 'Jh8',
            'M' => 'Oxlao8',
            'N' => 'OOyx8',
            'O' => 'o00xY8',
            'P' => '0xP18',
            'Q' => '0xP8',
            'R' => 'sd208',
            'S' => 'JS08',
            'T' => 'KC8',
            'U' => 'qYkW8',
            'V' => 'qqQw8',
            'W' => 'Yuxq8',
            'X' => 'UUixYY8',
            'Y' => 'WWppxY8',
            'Z' => 'pxWW8',
            '0' => 'iiiY8',
            '1' => 'dxUYY8',
            '2' => 'SxTy8',
            '3' => 'G98',
            '4' => 'YuuI8',
            '5' => 'xITY8',
            '6' => 'DSYC8',
            '7' => 'CS28',
            '8' => 'PCSR8',
            '9' => 'OOS8',
            's_PPX1' => 'S8',
            ',' => 'sadP8',
            '.' => 'xpsd198',
            '[' => 'DTxDTp8',
            ']' => 'OPOP18',
            '(' => 'PlIcq8',
            ')' => 'FPOSx8',
            '\'' => '4PxxX8',
            '"' => 'DSTe8',
            '\\' => 'KaMP8',
            '?' => 'XPOS8',
            ':' => 'DPs8',
            ';' => 'TE38',
            '{' => 'xYaD8',
            '}' => 'xXDD918',
            // ... (rest of your encryption map)
        ];

        if ($decode) {
            $result = array_search($char, $map);
            return $result !== false ? $result : $char;
        }

        return $map[$char] ?? $char;
    }

    private function makeDecrypt(string $data)
    {
        return $this->decryptSlice($data);
    }

    public function checkFormulas(Request $request)
    {
        $invalid = [];

        try {
            foreach ($request->data as $value) {
                $is_exist = AnalystFormula::where('parameter', trim($value))
                    ->whereHas('param', function ($q) {
                        $q->where('id_kategori', 4);
                    })
                    ->with('param:id,id_kategori')
                    ->first();

                // kalau gak ada, atau id_kategori-nya bukan 4
                if (!$is_exist) {
                    $invalid[] = $value;
                }
            }

            return response()->json([
                'status' => 'success',
                'invalid_data' => $invalid,
            ]);
        } catch (\Exception $th) {
            dd($th);
        }
    }

    public function rollbackLhp()
    {
        try {
            DB::beginTransaction();
            DB::statement("SET SESSION sql_mode = ''");
            $data = OrderDetail::with([
                'dataPsikologi',
                'lhp_psikologi',
            ])
                ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
                ->where('is_active', true)
                ->whereJsonContains('parameter', [
                    "318;Psikologi"
                ])
                ->selectRaw('order_detail.*, GROUP_CONCAT(no_sampel SEPARATOR ", ") as no_sampel')
                ->whereNotNull('tanggal_terima')
                ->where('kategori_2', '4-Udara')
                ->whereIn('kategori_3', ["118-Psikologi", "27-Udara Lingkungan Kerja"])
                ->groupBy('cfr')
                ->where('status', 3)
                ->get();

            foreach ($data as $item) {
                $lhp = LhpUdaraPsikologiHeader::where('no_cfr', $item->cfr)->where('is_active', true)->first();

                if ($lhp) {
                    $lhp->is_approve = 0;
                    $lhp->save();
                }
                $no_sampel = explode(',', $item->no_sampel);
                foreach ($no_sampel as $no) {
                    $orderDetail = OrderDetail::where('no_sampel', $no)->first();
                    if ($orderDetail) {
                        $orderDetail->status = 2;
                        $orderDetail->is_approve = 0;
                        $orderDetail->save();
                    }
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Data berhasil di rollback'
            ]);
        } catch (\Exception $th) {
            DB::rollBack();

            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'detail' => $th->getLine()
            ], 500);
        }
    }

    public function moveInvoiceFromGenerate(){
        $invoices = [
            "ISL/INV/2500136",
            "ISL/INV/2500538",
            "ISL/INV/2500771",
            "ISL/INV/2500772",
            "ISL/INV/2500926",
            "ISL/INV/2500948",
            "ISL/INV/2500958",
            "ISL/INV/2500959",
            "ISL/INV/2501017",
            "ISL/INV/2501018",
            "ISL/INV/2501025",
            "ISL/INV/2501068",
            "ISL/INV/2501081",
            "ISL/INV/2501188",
            "ISL/INV/2501191",
            "ISL/INV/2501220",
            "ISL/INV/2501236",
            "ISL/INV/2501238",
            "ISL/INV/2501291",
            "ISL/INV/2501300",
            "ISL/INV/2501307",
            "ISL/INV/2501309",
            "ISL/INV/2501314",
            "ISL/INV/2501341",
            "ISL/INV/2501355",
            "ISL/INV/2501365",
            "ISL/INV/2501366",
            "ISL/INV/2501395",
            "ISL/INV/2501430",
            "ISL/INV/2501466",
            "ISL/INV/2501474",
            "ISL/INV/2501499",
            "ISL/INV/2501501",
            "ISL/INV/2501530",
            "ISL/INV/2501581",
            "ISL/INV/2501661",
            "ISL/INV/2501723",
            "ISL/INV/2501745",
            "ISL/INV/2501768",
            "ISL/INV/2501777",
            "ISL/INV/2501781",
            "ISL/INV/2501786",
            "ISL/INV/2501787",
            "ISL/INV/2501788",
            "ISL/INV/2501806",
            "ISL/INV/2501814",
            "ISL/INV/2501839",
            "ISL/INV/2501843",
            "ISL/INV/2501849",
            "ISL/INV/2501855",
            "ISL/INV/2501862",
            "ISL/INV/2501865",
            "ISL/INV/2501870",
            "ISL/INV/2501878",
            "ISL/INV/2501894",
            "ISL/INV/2501911",
            "ISL/INV/2501929",
            "ISL/INV/2501935",
            "ISL/INV/2501957",
            "ISL/INV/2501969",
            "ISL/INV/2501974",
            "ISL/INV/2501984",
            "ISL/INV/2501986",
            "ISL/INV/2502003",
            "ISL/INV/2502012",
            "ISL/INV/2502043",
            "ISL/INV/2502055",
            "ISL/INV/2502072",
            "ISL/INV/2502073",
            "ISL/INV/2502079",
            "ISL/INV/2502081",
            "ISL/INV/2502084",
            "ISL/INV/2502093",
            "ISL/INV/2502107",
            "ISL/INV/2502113",
            "ISL/INV/2502173",
            "ISL/INV/2502182",
            "ISL/INV/2502214",
            "ISL/INV/2502278",
            "ISL/INV/2502290",
            "ISL/INV/2502322",
            "ISL/INV/2502330",
            "ISL/INV/2502368",
            "ISL/INV/2502425",
            "ISL/INV/2502433",
            "ISL/INV/2502450",
            "ISL/INV/2502453",
            "ISL/INV/2502475",
            "ISL/INV/2502477",
            "ISL/INV/2502482",
            "ISL/INV/2502521",
            "ISL/INV/2502523",
            "ISL/INV/2502527",
            "ISL/INV/2502535",
            "ISL/INV/2502550",
            "ISL/INV/2502552",
            "ISL/INV/2502555",
            "ISL/INV/2502581",
            "ISL/INV/2502605",
            "ISL/INV/2502606",
            "ISL/INV/2502615",
            "ISL/INV/2502620",
            "ISL/INV/2502621",
            "ISL/INV/2502637",
            "ISL/INV/2502641",
            "ISL/INV/2502645",
            "ISL/INV/2502646",
            "ISL/INV/2502649",
            "ISL/INV/2502661",
            "ISL/INV/2502669",
            "ISL/INV/2502670",
            "ISL/INV/2502678",
            "ISL/INV/2502688",
            "ISL/INV/2502692",
            "ISL/INV/2502696",
            "ISL/INV/2502697",
            "ISL/INV/2502700",
            "ISL/INV/2502705",
            "ISL/INV/2502707",
            "ISL/INV/2502715",
            "ISL/INV/2502725",
            "ISL/INV/2502728",
            "ISL/INV/2502736",
            "ISL/INV/2502737",
            "ISL/INV/2502738",
            "ISL/INV/2502761",
            "ISL/INV/2502763",
            "ISL/INV/2502781",
            "ISL/INV/2502787",
            "ISL/INV/2502790",
            "ISL/INV/2502795",
            "ISL/INV/2502799",
            "ISL/INV/2502812",
            "ISL/INV/2502813",
            "ISL/INV/2502814",
            "ISL/INV/2502818",
            "ISL/INV/2502841",
            "ISL/INV/2502858",
            "ISL/INV/2502867",
            "ISL/INV/2502883",
            "ISL/INV/2502888",
            "ISL/INV/2502924",
            "ISL/INV/2502985",
            "ISL/INV/2502988",
            "ISL/INV/2502990",
            "ISL/INV/2503038",
            "ISL/INV/2503088",
            "ISL/INV/2503091",
            "ISL/INV/2503118",
            "ISL/INV/2503142",
            "ISL/INV/2503155",
            "ISL/INV/2503167",
            "ISL/INV/2503173",
            "ISL/INV/2503189",
            "ISL/INV/2503297",
            "ISL/INV/2503304",
            "ISL/INV/2503314",
            "ISL/INV/2503315",
            "ISL/INV/2503322",
            "ISL/INV/2503347",
            "ISL/INV/2503362",
            "ISL/INV/2503369",
            "ISL/INV/2503378",
            "ISL/INV/2503385",
            "ISL/INV/2503393",
            "ISL/INV/2503406",
            "ISL/INV/2503407",
            "ISL/INV/2503408",
            "ISL/INV/2503410",
            "ISL/INV/2503413",
            "ISL/INV/2503416",
            "ISL/INV/2503417",
            "ISL/INV/2503418",
            "ISL/INV/2503421",
            "ISL/INV/2503426",
            "ISL/INV/2503453",
            "ISL/INV/2503456",
            "ISL/INV/2503457",
            "ISL/INV/2503467",
            "ISL/INV/2503484",
            "ISL/INV/2503487",
            "ISL/INV/2503492",
            "ISL/INV/2503495",
            "ISL/INV/2503497",
            "ISL/INV/2503512",
            "ISL/INV/2503514",
            "ISL/INV/2503517",
            "ISL/INV/2503519",
            "ISL/INV/2503528",
            "ISL/INV/2503534",
            "ISL/INV/2503548",
            "ISL/INV/2503561",
            "ISL/INV/2503568",
            "ISL/INV/2503586",
            "ISL/INV/2503590",
            "ISL/INV/2503591",
            "ISL/INV/2503594",
            "ISL/INV/2503599",
            "ISL/INV/2503607",
            "ISL/INV/2503612",
            "ISL/INV/2503616",
            "ISL/INV/2503637",
            "ISL/INV/2503656",
            "ISL/INV/2503661",
            "ISL/INV/2503665",
            "ISL/INV/2503708",
            "ISL/INV/2503713",
            "ISL/INV/2503733",
            "ISL/INV/2503769",
            "ISL/INV/2503780",
            "ISL/INV/2503785",
            "ISL/INV/2503801",
            "ISL/INV/2503806",
            "ISL/INV/2503816",
            "ISL/INV/2503821",
            "ISL/INV/2503822",
            "ISL/INV/2503824",
            "ISL/INV/2503843",
            "ISL/INV/2503844",
            "ISL/INV/2503845",
            "ISL/INV/2503923",
            "ISL/INV/2503978",
            "ISL/INV/2503980",
            "ISL/INV/2503981",
            "ISL/INV/2503985",
            "ISL/INV/2503986",
            "ISL/INV/2503990",
            "ISL/INV/2504019",
            "ISL/INV/2504041",
            "ISL/INV/2504047",
            "ISL/INV/2504079",
            "ISL/INV/2504080",
            "ISL/INV/2504124",
            "ISL/INV/2504132",
            "ISL/INV/2504136",
            "ISL/INV/2504139",
            "ISL/INV/2504142",
            "ISL/INV/2504165",
            "ISL/INV/2504191",
            "ISL/INV/2504197",
            "ISL/INV/2504219",
            "ISL/INV/2504238",
            "ISL/INV/2504254",
            "ISL/INV/2504255",
            "ISL/INV/2504257",
            "ISL/INV/2504287",
            "ISL/INV/2504293",
            "ISL/INV/2504298",
            "ISL/INV/2504316",
            "ISL/INV/2504320",
            "ISL/INV/2504336",
            "ISL/INV/2504338",
            "ISL/INV/2504358",
            "ISL/INV/2504369",
            "ISL/INV/2504374",
            "ISL/INV/2504383",
            "ISL/INV/2504388",
            "ISL/INV/2504392",
            "ISL/INV/2504397",
            "ISL/INV/2504400",
            "ISL/INV/2504401",
            "ISL/INV/2504402",
            "ISL/INV/2504403",
            "ISL/INV/2504405",
            "ISL/INV/2504411",
            "ISL/INV/2504414",
            "ISL/INV/2504416",
            "ISL/INV/2504418",
            "ISL/INV/2504424",
            "ISL/INV/2504425",
            "ISL/INV/2504426",
            "ISL/INV/2504427",
            "ISL/INV/2504428",
            "ISL/INV/2504430",
            "ISL/INV/2504431",
            "ISL/INV/2504432",
            "ISL/INV/2504441",
            "ISL/INV/2504473",
            "ISL/INV/2504483",
            "ISL/INV/2504486",
            "ISL/INV/2504492",
            "ISL/INV/2504500",
            "ISL/INV/2504502",
            "ISL/INV/2504522",
            "ISL/INV/2504523",
            "ISL/INV/2504526",
            "ISL/INV/2504534",
            "ISL/INV/2504535",
            "ISL/INV/2504544",
            "ISL/INV/2504546",
            "ISL/INV/2504565",
            "ISL/INV/2504566",
            "ISL/INV/2504569",
            "ISL/INV/2504571",
            "ISL/INV/2504593",
            "ISL/INV/2504609",
            "ISL/INV/2504618",
            "ISL/INV/2504651",
            "ISL/INV/2504658",
            "ISL/INV/2504670",
            "ISL/INV/2504718",
            "ISL/INV/2504732",
            "ISL/INV/2504735",
            "ISL/INV/2504757",
            "ISL/INV/2504766",
            "ISL/INV/2504772",
            "ISL/INV/2504809",
            "ISL/INV/2504810",
            "ISL/INV/2504811",
            "ISL/INV/2504892",
            "ISL/INV/2504934",
            "ISL/INV/2504937",
            "ISL/INV/2504944",
            "ISL/INV/2504988",
            "ISL/INV/2505038",
            "ISL/INV/2505049",
            "ISL/INV/2505062",
            "ISL/INV/2505094",
            "ISL/INV/2505104",
            "ISL/INV/2505135",
            "ISL/INV/2505199",
            "ISL/INV/2505204",
            "ISL/INV/2505214",
            "ISL/INV/2505262",
            "ISL/INV/2505271",
            "ISL/INV/2505285",
            "ISL/INV/2505287",
            "ISL/INV/2505294",
            "ISL/INV/2505312",
            "ISL/INV/2505313",
            "ISL/INV/2505323",
            "ISL/INV/2505333",
            "ISL/INV/2505337",
            "ISL/INV/2505345",
            "ISL/INV/2505347",
            "ISL/INV/2505348",
            "ISL/INV/2505349",
            "ISL/INV/2505352",
            "ISL/INV/2505353",
            "ISL/INV/2505354",
            "ISL/INV/2505355",
            "ISL/INV/2505356",
            "ISL/INV/2505357",
            "ISL/INV/2505358",
            "ISL/INV/2505363",
            "ISL/INV/2505367",
            "ISL/INV/2505373",
            "ISL/INV/2505377",
            "ISL/INV/2505380",
            "ISL/INV/2505387",
            "ISL/INV/2505393",
            "ISL/INV/2505396",
            "ISL/INV/2505400",
            "ISL/INV/2505418",
            "ISL/INV/2505426",
            "ISL/INV/2505430",
            "ISL/INV/2505431",
            "ISL/INV/2505440",
            "ISL/INV/2505441",
            "ISL/INV/2505449",
            "ISL/INV/2505450",
            "ISL/INV/2505460",
            "ISL/INV/2505473",
            "ISL/INV/2505477",
            "ISL/INV/2505488",
            "ISL/INV/2505493",
            "ISL/INV/2505496",
            "ISL/INV/2505511",
            "ISL/INV/2505540",
            "ISL/INV/2505557",
            "ISL/INV/2505569",
            "ISL/INV/2505577",
            "ISL/INV/2505592",
            "ISL/INV/2505611",
            "ISL/INV/2505616",
            "ISL/INV/2505618",
            "ISL/INV/2505620",
            "ISL/INV/2505641",
            "ISL/INV/2505647",
            "ISL/INV/2505662",
            "ISL/INV/2505678",
            "ISL/INV/2505685",
            "ISL/INV/2505689",
            "ISL/INV/2505718",
            "ISL/INV/2505719",
            "ISL/INV/2505742",
            "ISL/INV/2505777",
            "ISL/INV/2505778",
            "ISL/INV/2505795",
            "ISL/INV/2505798",
            "ISL/INV/2505801",
            "ISL/INV/2505879",
            "ISL/INV/2505894",
            "ISL/INV/2505905",
            "ISL/INV/2506023",
            "ISL/INV/2506027",
            "ISL/INV/2506060",
            "ISL/INV/2506061",
            "ISL/INV/2506062",
            "ISL/INV/2506066",
            "ISL/INV/2506079",
            "ISL/INV/2506089",
            "ISL/INV/2506108",
            "ISL/INV/2506109",
            "ISL/INV/2506116",
            "ISL/INV/2506125",
            "ISL/INV/2506134",
            "ISL/INV/2506135",
            "ISL/INV/2506136",
            "ISL/INV/2506138",
            "ISL/INV/2506159",
            "ISL/INV/2506164",
            "ISL/INV/2506165",
            "ISL/INV/2506168",
            "ISL/INV/2506171",
            "ISL/INV/2506217",
            "ISL/INV/2506223",
            "ISL/INV/2506224",
            "ISL/INV/2506236",
            "ISL/INV/2506237",
            "ISL/INV/2506238",
            "ISL/INV/2506246",
            "ISL/INV/2506252",
            "ISL/INV/2506257",
            "ISL/INV/2506271",
            "ISL/INV/2506274",
            "ISL/INV/2506309",
            "ISL/INV/2506315",
            "ISL/INV/2506324",
            "ISL/INV/2506330",
            "ISL/INV/2506333",
            "ISL/INV/2506341",
            "ISL/INV/2506358",
            "ISL/INV/2506389",
            "ISL/INV/2506390",
            "ISL/INV/2506396",
            "ISL/INV/2506397",
            "ISL/INV/2506398",
            "ISL/INV/2506399",
            "ISL/INV/2506400",
            "ISL/INV/2506410",
            "ISL/INV/2506412",
            "ISL/INV/2506415",
            "ISL/INV/2506416",
            "ISL/INV/2506423",
            "ISL/INV/2506424",
            "ISL/INV/2506431",
            "ISL/INV/2506432",
            "ISL/INV/2506433",
            "ISL/INV/2506438",
            "ISL/INV/2506439",
            "ISL/INV/2506445",
            "ISL/INV/2506446",
            "ISL/INV/2506453",
            "ISL/INV/2506459",
            "ISL/INV/2506463",
            "ISL/INV/2506471",
            "ISL/INV/2506474",
            "ISL/INV/2506479",
            "ISL/INV/2506483",
            "ISL/INV/2506488",
            "ISL/INV/2506489",
            "ISL/INV/2506490",
            "ISL/INV/2506497",
            "ISL/INV/2506498",
            "ISL/INV/2506505",
            "ISL/INV/2506521",
            "ISL/INV/2506523",
            "ISL/INV/2506531",
            "ISL/INV/2506541",
            "ISL/INV/2506548",
            "ISL/INV/2506551",
            "ISL/INV/2506562",
            "ISL/INV/2506564",
            "ISL/INV/2506570",
            "ISL/INV/2506579",
            "ISL/INV/2506584",
            "ISL/INV/2506585",
            "ISL/INV/2506590",
            "ISL/INV/2506592",
            "ISL/INV/2506595",
            "ISL/INV/2506611",
            "ISL/INV/2506617",
            "ISL/INV/2506683",
            "ISL/INV/2506737",
            "ISL/INV/2506760",
            "ISL/INV/2506765",
            "ISL/INV/2506778",
            "ISL/INV/2506797",
            "ISL/INV/2506827",
            "ISL/INV/2506878",
            "ISL/INV/2506909",
            "ISL/INV/2506967",
            "ISL/INV/2506972",
            "ISL/INV/2506974",
            "ISL/INV/2506979",
            "ISL/INV/2506996",
            "ISL/INV/2507025",
            "ISL/INV/2507033",
            "ISL/INV/2507034",
            "ISL/INV/2507040",
            "ISL/INV/2507055",
            "ISL/INV/2507059",
            "ISL/INV/2507062",
            "ISL/INV/2507063",
            "ISL/INV/2507065",
            "ISL/INV/2507068",
            "ISL/INV/2507081",
            "ISL/INV/2507091",
            "ISL/INV/2507092",
            "ISL/INV/2507120",
            "ISL/INV/2507122",
            "ISL/INV/2507128",
            "ISL/INV/2507146",
            "ISL/INV/2507157",
            "ISL/INV/2507160",
            "ISL/INV/2507163",
            "ISL/INV/2507164",
            "ISL/INV/2507167",
            "ISL/INV/2507172",
            "ISL/INV/2507174",
            "ISL/INV/2507181",
            "ISL/INV/2507196",
            "ISL/INV/2507203",
            "ISL/INV/2507204",
            "ISL/INV/2507205",
            "ISL/INV/2507206",
            "ISL/INV/2507207",
            "ISL/INV/2507208",
            "ISL/INV/2507209",
            "ISL/INV/2507210",
            "ISL/INV/2507211",
            "ISL/INV/2507212",
            "ISL/INV/2507213",
            "ISL/INV/2507215",
            "ISL/INV/2507217",
            "ISL/INV/2507219",
            "ISL/INV/2507222",
            "ISL/INV/2507226",
            "ISL/INV/2507231",
            "ISL/INV/2507233",
            "ISL/INV/2507238",
            "ISL/INV/2507245",
            "ISL/INV/2507246",
            "ISL/INV/2507247",
            "ISL/INV/2507255",
            "ISL/INV/2507257",
            "ISL/INV/2507262",
            "ISL/INV/2507266",
            "ISL/INV/2507267",
            "ISL/INV/2507271",
            "ISL/INV/2507274",
            "ISL/INV/2507276",
            "ISL/INV/2507277",
            "ISL/INV/2507286",
            "ISL/INV/2507304",
            "ISL/INV/2507306",
            "ISL/INV/2507309",
            "ISL/INV/2507311",
            "ISL/INV/2507312",
            "ISL/INV/2507313",
            "ISL/INV/2507315",
            "ISL/INV/2507316",
            "ISL/INV/2507317",
            "ISL/INV/2507324",
            "ISL/INV/2507325",
            "ISL/INV/2507330",
            "ISL/INV/2507348",
            "ISL/INV/2507350",
            "ISL/INV/2507351",
            "ISL/INV/2507352",
            "ISL/INV/2507361",
            "ISL/INV/2507364",
            "ISL/INV/2507366",
            "ISL/INV/2507448",
            "ISL/INV/2507460",
            "ISL/INV/2507463",
            "ISL/INV/2507475",
            "ISL/INV/2507489",
            "ISL/INV/2507491",
            "ISL/INV/2507498",
            "ISL/INV/2507502",
            "ISL/INV/2507512",
            "ISL/INV/2507521",
            "ISL/INV/2507528",
            "ISL/INV/2507529",
            "ISL/INV/2507546",
            "ISL/INV/2507549",
            "ISL/INV/2507577",
            "ISL/INV/2507583",
            "ISL/INV/2507590",
            "ISL/INV/2507646",
            "ISL/INV/2507732",
            "ISL/INV/2507758",
            "ISL/INV/2507795",
            "ISL/INV/2507805",
            "ISL/INV/2507812",
            "ISL/INV/2507813",
            "ISL/INV/2507840",
            "ISL/INV/2507855",
            "ISL/INV/2507895",
            "ISL/INV/2507938",
            "ISL/INV/2507939",
            "ISL/INV/2507940",
            "ISL/INV/2507963",
            "ISL/INV/2507979",
            "ISL/INV/2508002",
            "ISL/INV/2508027",
            "ISL/INV/2508045",
            "ISL/INV/2508074",
            "ISL/INV/2508075",
            "ISL/IV/2500144",
            "ISL/IV/2500181",
            "ISL/IV/2500233",
            "ISL/IV/2500346",
            "ISL/INV/2508076",
            "ISL/INV/2508092",
            "ISL/INV/2508094",
            "ISL/INV/2508095",
            "ISL/INV/2508096",
            "ISL/INV/2508109",
            "ISL/INV/2508110",
            "ISL/INV/2508112",
            "ISL/INV/2508124",
            "ISL/INV/2508125",
            "ISL/INV/2508126",
            "ISL/INV/2508134",
            "ISL/INV/2508135",
            "ISL/INV/2508139",
            "ISL/INV/2508140",
            "ISL/INV/2508143",
            "ISL/INV/2508144",
            "ISL/INV/2508148",
            "ISL/INV/2508156",
            "ISL/INV/2508157",
            "ISL/INV/2508169",
            "ISL/INV/2508174",
            "ISL/INV/2508179",
            "ISL/INV/2508182",
            "ISL/INV/2508184",
            "ISL/INV/2508185",
            "ISL/INV/2508194",
            "ISL/INV/2508198",
            "ISL/INV/2508200",
            "ISL/INV/2508201",
            "ISL/INV/2508202",
            "ISL/INV/2508204",
            "ISL/INV/2508213",
            "ISL/INV/2508218",
            "ISL/INV/2508222",
            "ISL/INV/2508242",
            "ISL/INV/2508244",
            "ISL/INV/2508249",
            "ISL/INV/2508254",
            "ISL/INV/2508266",
            "ISL/INV/2508269",
            "ISL/INV/2508270",
            "ISL/INV/2508283",
            "ISL/INV/2508286",
            "ISL/INV/2508290",
            "ISL/INV/2508291",
            "ISL/INV/2508294",
            "ISL/INV/2508317",
            "ISL/INV/2508320",
            "ISL/INV/2508322",
            "ISL/INV/2508329",
            "ISL/INV/2508347",
            "ISL/INV/2508357",
            "ISL/INV/2508375",
            "ISL/INV/2508381",
            "ISL/INV/2508402",
            "ISL/INV/2508424",
            "ISL/INV/2508426",
            "ISL/INV/2508487",
            "ISL/INV/2508493",
            "ISL/INV/2508589",
            "ISL/INV/2508639",
            "ISL/INV/2508784",
            "ISL/INV/2508909"
        ];

        Invoice::whereIn('no_invoice', $invoices)->update(['is_generate' => 1, 'is_emailed' => 1]);

        return response()->json(['message' => 'Success'], 200);
    }
}
