<?php

namespace App\Http\Controllers\api;

use App\Models\PersiapanSampelDetail;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;


Carbon::setLocale('id');

use App\Models\{Ftc, ScanSampelAnalis, ScanSampelTc, OrderDetail};

class VerifikasiLabISLController extends Controller
{

    public function dashboard(Request $request)
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        $today = Carbon::today();
        $month = Carbon::now()->month;

        if ($grade == 'STAFF') {
            $data = Ftc::selectRaw("
                SUM(CASE WHEN DATE(ftc_laboratory) = ? THEN 1 ELSE 0 END) as total_hari_ini,
                SUM(CASE WHEN MONTH(ftc_laboratory) = ? THEN 1 ELSE 0 END) as total_bulan_ini
            ", [$today, $month])
                ->where('user_laboratory', $user->id)
                ->first();

            return response()->json([
                'today' => $data->total_hari_ini ?? 0,
                'thisMonth' => $data->total_bulan_ini ?? 0
            ], 200);
        } else {
            $data = Ftc::selectRaw("
                SUM(CASE WHEN DATE(ftc_laboratory) = ? THEN 1 ELSE 0 END) as total_hari_ini,
                SUM(CASE WHEN MONTH(ftc_laboratory) = ? THEN 1 ELSE 0 END) as total_bulan_ini
            ", [$today, $month])
                ->first();

            return response()->json([
                'today' => $data->total_hari_ini ?? 0,
                'thisMonth' => $data->total_bulan_ini ?? 0
            ], 200);
        }
    }

    public function index(Request $request)
    {
        $grade = null;
        $user = $request->attributes->get('user');
        if (isset($user->karyawan) && $user->karyawan != null) {
            $grade = $user->karyawan->grade;
        }

        if ($grade == 'STAFF') {
            $data = ScanSampelAnalis::select('created_by', 'created_at', 'status', 'no_sampel')
                ->where('is_active', true)
                ->where('created_by', $this->karyawan)
                ->orderBy('created_at', 'DESC')
                ->paginate(5);
            return response()->json($data, 200);
        } else {
            $data = ScanSampelAnalis::select('created_by', 'created_at', 'status', 'no_sampel')
                ->where('is_active', true)
                ->orderBy('created_at', 'DESC')
                ->paginate(5);
            return response()->json($data, 200);
        }
    }

    public function store(Request $request)
    {
        try {
            $data = Ftc::where('no_sample', $request->no_sampel)->first();

            if ($data->ftc_laboratory)
                return response()->json(['message' => 'Nomor sampel sudah pernah di scan'], 401);

            $data->ftc_laboratory = Carbon::now()->format('Y-m-d H:i:s');
            $data->user_laboratory = $this->user_id;

            $data->save();

            return response()->json(['message' => 'Data berhasil disimpan dengan no sample ' . $request->no_sampel, 'status' => '200'], 200);
        } catch (\Throwable $th) {
            return response()->json(['message' => 'No sample tidak ditemukan', 'status' => '400'], 400);
        }
    }

    public function checkBottle2(Request $request)
    {
        $datachek = explode('/', $request->no_sampel);

        $scan = null;
        $data_botol = [];
        $data_scanned = [];
        $data_scanned_analis = [];

        if (isset($datachek[1])) {
            $scan = ScanSampelTc::where('no_sampel', $request->no_sampel)->first();

            if (!$scan) {
                return response()->json([
                    'message' => 'Botol dengan no sampel tersebut belum di-scan',
                    'status' => '404'
                ], 404);
            }

            $data_scanned = array_map(function ($item) {
                if ($item->kategori == '4-Udara' || $item->kategori == '5-Emisi') {
                    return $item->disiapkan = '1';
                } else {
                    return $item->disiapkan = $item->jumlah;
                }
                return $item;
            }, json_decode($scan->data_detail, false) ?: []);

            $data_scan_analis = ScanSampelAnalis::where('no_sampel', $request->no_sampel)
                ->where('is_active', 1)
                ->first();

            $data_scanned_analis = $data_scan_analis ? json_decode($data_scan_analis->data, false) : [];

            // if (count($data_scanned) !== count($scanned_filter)) {
            //     return response()->json([
            //         'message' => 'Botol dengan no sampel tersebut belum lengkap',
            //         'status' => '500'
            //     ], 500);
            // }

            $data_botol = array_values($scanned_filter);
        } else {
            $scan = ScanSampelTc::whereRaw(
                "JSON_CONTAINS(data_detail, ?)",
                ['{"koding": "' . $request->no_sampel . '"}']
            )->first();

            if (!$scan) {
                return response()->json([
                    'message' => 'Botol dengan kode tersebut belum di-scan',
                    'status' => '404'
                ], 404);
            }

            $data_scanned = array_map(function ($item) {
                if ($item->kategori == '4-Udara' || $item->kategori == '5-Emisi') {
                    return $item->disiapkan = '1';
                } else {
                    return $item->disiapkan = $item->jumlah;
                }
                return $item;
            }, json_decode($scan->data_detail, false) ?: []);

            $data_scan_analis = ScanSampelAnalis::whereRaw(
                "JSON_CONTAINS(data, ?)",
                ['{"koding": "' . $request->no_sampel . '"}']
            )
                ->where('is_active', 1)
                ->first();

            $data_scanned_analis = $data_scan_analis ? json_decode($data_scan_analis->data, false) : [];

            // if (count($data_scanned) !== count($scanned_filter)) {
            //     return response()->json([
            //         'message' => 'Botol dengan no sampel tersebut belum lengkap',
            //         'status' => '500'
            //     ], 500);
            // }

            $data_botol = array_map(function ($item) use ($request) {
                if ($item->koding === $request->no_sampel) {
                    $item->add = 1;
                }
                return $item;
            }, $data_scanned);

            foreach ($data_scanned as $key => $item) {
                if ($scan->kategori == '4-Udara' || $scan->kategori == '5-Emisi') {
                    $item->disiapkan = '1';
                }
                $item->kategori = $scan->kategori;

                $parameterExcludeEmisi = ['SO2', 'NO2', 'Velocity', 'NOX'];

                if ($scan->kategori == '5-Emisi' && in_array($item->parameter, $parameterExcludeEmisi)) {
                    unset($data_scanned[$key]);
                }

                if ($item->koding === $request->no_sampel) {
                    $item->add = 1;
                }
            }

            $data_scanned = array_values($data_scanned);
        }

        return response()->json([
            'message' => 'Botol dengan no sampel tersebut berhasil di dapatkan',
            'status' => '200',
            'data_botol' => $data_botol,
            'no_sampel' => $scan->no_sampel ?? null,
            'data_scan' => $data_scanned_analis
        ], 200);
    }

    // public function checkBottle2(Request $request)
    // {
    //     DB::beginTransaction();
    //     $datachek = explode('/', $request->no_sampel);
    //     $data = null;
    //     $persiapan = null;
    //     $dataDisplay = null;
    //     $parameters = null;
    //     try {
    //         if (isset($datachek[1])) {

    //             $data = ScanSampelTc::where('no_sampel', $request->no_sampel)->first();
    //             $dataDisplay = json_decode($data->data_detail);
    //             $data_scan_analis = ScanSampelAnalis::where('no_sampel', $request->no_sampel)->where('is_active', 1)->first();
    //             $data_scanned_analis = isset($data_scan_analis) ? json_decode($data_scan_analis->data) : [];
    //         } else {
    //             $data = ScanSampelTc::whereNotNull('data_detail')
    //                 ->whereJsonContains('data_detail', ['koding' => $request->no_sampel])
    //                 ->first();

    //             if (!$data) {
    //                 return response()->json(["message" => "Botol Belum di SCAN di TC", "code" => 404], 404);
    //             }

    //             $dataDisplay = json_decode($data->data_detail);
    //             $data_scan_analis = ScanSampelAnalis::where('no_sampel', $data->no_sampel)->where('is_active', 1)->first();
    //             $data_scanned_analis = isset($data_scan_analis) ? json_decode($data_scan_analis->data) : [];
    //             foreach ($dataDisplay as $item) {
    //                 if ($data->kategori == '1-Air') {
    //                     $type = $item->jenis_botol;
    //                 } else {
    //                     $type = $item->parameter;

    //                 }

    //                 if ($item->koding == $request->no_sampel) {
    //                     $item->add = 1;
    //                 }


    //                 // if (isset($parameters->air->$type)) {
    //                 //     $item->disiapkan = $parameters->air->$type->disiapkan;
    //                 //     if ($item->koding == $request->no_sampel) {
    //                 //         $item->add = 1;
    //                 //     }
    //                 // } else if (isset($parameters->udara->$type)) {
    //                 //     $item->disiapkan = $parameters->udara->$type->disiapkan;
    //                 //     if ($item->koding == $request->no_sampel) {
    //                 //         $item->add = 1;
    //                 //     }
    //                 // } else {
    //                 //     $item->disiapkan = null;
    //                 // }
    //             }


    //         }
    //         foreach ($dataDisplay as $key => $item) {
    //             if ($data->kategori == '4-Udara' || $data->kategori == '5-Emisi') {
    //                 $item->disiapkan = '1';
    //             }
    //             $item->kategori = $data->kategori;


    //             $parameterExcludeEmisi = [
    //                 'SO2',
    //                 'NO2',
    //                 'Velocity',
    //                 'NOX'
    //             ];

    //             if ($data->kategori == '5-Emisi' && in_array($item->parameter, $parameterExcludeEmisi)) {
    //                 unset($dataDisplay[$key]);
    //             }

    //         }

    //         $dataDisplay = array_values($dataDisplay);

    //         DB::commit();
    //         return response()->json([
    //             'message' => 'Botol dengan no sampel tersebut berhasil di dapatkan',
    //             'status' => '200',
    //             'data_botol' => $dataDisplay,
    //             'no_sampel' => $data->no_sampel,
    //             'data_scan' => $data_scanned_analis ?? []
    //         ], 200);
    //     } catch (\Throwable $th) {
    //         DB::rollBack();
    //         return response()->json(['message' => 'No sample tidak ditemukan', 'status' => '400', 'log' => $th->getMessage(), 'getLine' => $th->getLine(), 'getFile' => $th->getFile()], 400);
    //     }
    // }




    public function storeBottle(Request $request)
    {
        DB::beginTransaction();
        try {
            $no_sampel = $request->no_sampel;
            $order_detail = OrderDetail::where('no_sampel', $no_sampel)->first();
            $data_scan = $request->data_botol;
            $data = ScanSampelAnalis::where('no_sampel', $no_sampel)->first();
            $lengkap = false;
            $dataScanAll = array_reduce($data_scan, function ($carry, $item) use (&$lengkap) {
                if ($item['jumlah'] == $item['disiapkan']) {
                    $carry[] = $item;
                }
                return $carry;
            });

            $data_scan = array_filter($data_scan, function ($item) use (&$lengkap) {
                if (isset($item['add']))
                    unset($item['add']);
                return $item;
            });

            if (count($dataScanAll) == count($data_scan)) {
                $lengkap = true;
            }
            if ($data) {
                $data->data = json_encode($data_scan);
                $data->updated_at = Carbon::now();
                $data->updated_by = $this->karyawan;
                $data->status = $lengkap ? 'lengkap' : 'belum_lengkap';
                $data->save();
            } else {
                $data = new ScanSampelAnalis();
                $data->no_sampel = $no_sampel;
                $data->data = json_encode($data_scan);
                $data->created_at = Carbon::now();
                $data->created_by = $this->karyawan;
                $data->status = $lengkap ? 'lengkap' : 'belum_lengkap';
                $data->save();
            }

            $ftc = Ftc::where('no_sample', $no_sampel)->first();
            if (is_null($ftc)) {
                $ftc = new Ftc();
                $ftc->no_sample = $no_sampel;

            }
            $ftc->ftc_laboratory = Carbon::now()->format('Y-m-d H:i:s');
            $ftc->user_laboratory = $this->user_id;
            $ftc->save();

            // $order_detail->tanggal_terima = Carbon::now()->format('Y-m-d');
            // $order_detail->save();

            DB::commit();
            return response()->json(['message' => 'Data berhasil disimpan dengan no sample ' . $request->no_sampel, 'status' => '201'], 201);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'file' => $th->getFile(),
                'status' => '500'
            ], 500);
        }
    }
}
