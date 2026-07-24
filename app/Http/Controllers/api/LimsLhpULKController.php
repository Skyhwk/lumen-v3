<?php

namespace App\Http\Controllers\api;

use App\Models\LhpsLingHeader;
use App\Models\LhpsLingDetail;
use App\Models\LhpsLingCustom;
use App\Models\Lims\OrderDetail;
use App\Models\MetodeSampling;
use App\Models\MasterBakumutu;
use App\Models\Parameter;
use App\Models\ParameterFdl;
use App\Models\GenerateLink;
use App\Services\TemplateLhps;
use App\Services\GenerateQrDocumentLhp;
use App\Services\LhpTemplate;
use App\Services\PrintLhp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;

class LimsLhpULKController extends Controller
{
    // public function index(Request $request){
    //     $listParamTemplate = [
    //         'lingkungan_kerja',
    //         'senyawa_volatile',
    //         'debu_personal',
    //         'sensoric_pm',
    //         'direct_lain',
    //     ];

    //     // 1. Ambil semua parameter dari template yang dipilih
    //     $parameterFdl = ParameterFdl::whereIn('nama_fdl', $listParamTemplate)->get();

    //     $parameterAllowed = [];
    //     foreach ($parameterFdl as $row) {
    //         $decoded = json_decode($row->parameters, true) ?? [];
    //         $parameterAllowed = array_merge($parameterAllowed, $decoded);
    //     }

    //     // Tambahkan parameter manual
    //     $manualAdd = [
    //         'Sinar UV', 'Ergonomi', 'Gelombang Elektro', 'Medan Listrik', 
    //         'Medan Magnit Statis', 'Medan Magnet', 'Power Density'
    //     ];
    //     $parameterAllowed = array_merge($parameterAllowed, $manualAdd);

    //     // Bersihkan duplikasi dan karakter aneh
    //     $parameterAllowed = array_unique(array_filter($parameterAllowed));

    //     // 2. Buat Pattern Regex
    //     // Gunakan preg_quote agar karakter seperti ( ) atau . tidak merusak query
    //     $pattern = implode('|', array_map('preg_quote', $parameterAllowed));

    //     $data = OrderDetail::selectRaw('
    //             max(id) as id,
    //             max(id_order_header) as id_order_header,
    //             cfr,
    //             GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
    //             MAX(nama_perusahaan) as nama_perusahaan,
    //             MAX(konsultan) as konsultan,
    //             MAX(no_quotation) as no_quotation,
    //             MAX(no_order) as no_order,
    //             MAX(parameter) as parameter,
    //             MAX(regulasi) as regulasi,
    //             GROUP_CONCAT(DISTINCT kategori_1 SEPARATOR ",") as kategori_1,
    //             MAX(kategori_2) as kategori_2,
    //             MAX(kategori_3) as kategori_3,
    //             GROUP_CONCAT(DISTINCT keterangan_1 SEPARATOR ",") as keterangan_1,
    //             GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_tugas,
    //             GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ",") as tanggal_terima
    //         ')
    //         ->with(['lhps_ling','orderHeader'])
    //         ->where('is_active', true)
    //         ->where('kategori_3', '27-Udara Lingkungan Kerja')
    //         ->where('status', 3)
    //         // ->where(function ($query) use ($parameterAllowed) {
    //         //     foreach ($parameterAllowed as $param) {
    //         //         $query->where('parameter', 'NOT LIKE', "%;$param%");
    //         //     }
    //         // })
    //         // --- LOGIKA VALIDASI TANPA OR WHERE ---
    //         ->where(function ($query) use ($parameterAllowed) {
    //             // Syntax SQL menghitung jumlah parameter (berdasarkan separator ;)
    //             $countSql = "(LENGTH(parameter) - LENGTH(REPLACE(parameter, ';', '')) + 1)";

    //             foreach ($parameterAllowed as $param) {
    //                 // Kita gunakan CASE WHEN di dalam whereRaw
    //                 // Logika: 
    //                 // 1. Apakah jumlah parameter <= 2?
    //                 //    YA -> Cek apakah parameter TIDAK mengandung kata terlarang (NOT LIKE).
    //                 //    TIDAK -> Return 1 (True/Lolos) karena validasi blacklist tidak berlaku.
                    
    //                 $query->whereRaw("
    //                     CASE 
    //                         WHEN $countSql <= 2 THEN parameter NOT LIKE ? 
    //                         ELSE 1 
    //                     END
    //                 ", ["%;$param%"]);
    //             }
    //         })
    //         ->groupBy('cfr');

    //     return Datatables::of($data)
    //         ->order(function ($query) {
    //             $query->orderByRaw("MAX(tanggal_terima) DESC");
    //         })
    //         ->make(true);

    // }

    public function index(Request $request)
{
    $listParamTemplate = [
        'lingkungan_kerja', 'senyawa_volatile', 'debu_personal', 
        'sensoric_pm', 'direct_lain'
    ];

    // 1. Ambil semua parameter yang diperbolehkan
    $parameterFdl = ParameterFdl::whereIn('nama_fdl', $listParamTemplate)->get();

    $parameterAllowed = [];
    foreach ($parameterFdl as $row) {
        $decoded = json_decode($row->parameters, true) ?? [];
        $parameterAllowed = array_merge($parameterAllowed, $decoded);
    }
    
    $parameterAllowed = array_unique(array_filter($parameterAllowed));

    // 2. Buat Pattern Regex untuk Whitelist
    // Kita gunakan [[:<:]] atau word boundaries agar "Magnet" tidak tertukar dengan "Magnetic"
    // Namun untuk fleksibilitas tinggi, kita gunakan pipe (|)
    $regexPattern = implode('|', array_map(function($val) {
        return preg_quote($val, '/');
    }, $parameterAllowed));

    $data = OrderDetail::selectRaw('
            max(id) as id,
            max(id_order_header) as id_order_header,
            cfr,
            GROUP_CONCAT(no_sampel SEPARATOR ",") as no_sampel,
            MAX(nama_perusahaan) as nama_perusahaan,
            MAX(konsultan) as konsultan,
            MAX(no_quotation) as no_quotation,
            MAX(no_order) as no_order,
            MAX(parameter) as parameter,
            MAX(regulasi) as regulasi,
            GROUP_CONCAT(DISTINCT kategori_1 SEPARATOR ",") as kategori_1,
            MAX(kategori_2) as kategori_2,
            MAX(kategori_3) as kategori_3,
            GROUP_CONCAT(DISTINCT keterangan_1 SEPARATOR ",") as keterangan_1,
            GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") as tanggal_tugas,
            GROUP_CONCAT(DISTINCT tanggal_terima SEPARATOR ",") as tanggal_terima
        ')
        ->with(['lhps_ling', 'orderHeader'])
        ->where('is_active', true)
        ->where('kategori_3', '27-Udara Lingkungan Kerja')
        ->where('status', 3)
        // --- LOGIKA WHITELIST MENGGUNAKAN REGEX ---
        ->where(function ($query) use ($regexPattern) {
            if (!empty($regexPattern)) {
                // Mencari apakah kolom 'parameter' mengandung salah satu dari list allowed
                $query->whereRaw("parameter REGEXP ?", [$regexPattern]);
            }
        });

    if ($request->has('month_year') && !empty($request->month_year)) {
        $parts = explode('-', $request->month_year);
        if (count($parts) === 2) {
            $year = $parts[0];
            $month = $parts[1];
            $matchingIds = \App\Models\LhpsLingHeader::whereYear('tanggal_lhp', $year)
                    ->whereMonth('tanggal_lhp', $month)
                    ->where('is_active', true)
                    ->pluck('no_lhp');
                $data->whereIn('cfr', $matchingIds);
        }
    }

    $data = $data->groupBy('cfr');

    return Datatables::of($data)
        ->order(function ($query) {
            $query->orderByRaw("MAX(tanggal_terima) DESC");
        })
        ->make(true);
}

    public function handleReject(Request $request) {
        DB::beginTransaction();
        try {
            $header = LhpsLingHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
            $detail = LhpsLingDetail::where('id_header', $header->id)->get();
            $custom = LhpsLingCustom::where('id_header', $header->id)->get();
            if($header != null) {

                $header->is_approved = 0;
                $header->rejected_at = Carbon::now()->format('Y-m-d H:i:s');
                $header->rejected_by = $this->karyawan;
                
                // $header->file_qr = null;
                $header->save();

                $data_order = OrderDetail::where('cfr', $request->no_lhp)->where('is_active', true)->update([
                    'status' => 2,
                    'is_approve' => 0,
                    'rejected_at' => Carbon::now()->format('Y-m-d H:i:s'),
                    'rejected_by' => $this->karyawan
                ]);
            }

            DB::commit();
            return response()->json([
                'message' => 'Reject no LHP '.$request->no_lhp.' berhasil!'
            ]);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Terjadi kesalahan '.$e->getMessage(),
            ], 401);
        }
    }

    public function handleDownload(Request $request) {
        try {
            $noLhp = $request->no_lhp ?? $request->cfr;
            if ($noLhp) {
                $header = LhpsLingHeader::where('no_lhp', $noLhp)->where('is_active', true)->first();
            } else {
                $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            }

            if ($header && $header->file_lhp) {
                $filePath = public_path('dokumen/LHP_DOWNLOAD/' . $header->file_lhp);
                if (file_exists($filePath)) {
                    $pdfContent = file_get_contents($filePath);
                    return response()->json([
                        'data' => base64_encode($pdfContent),
                        'is_base64' => true,
                        'file_name' => $header->file_lhp,
                        'message' => 'Download file berhasil!'
                    ], 200);
                }
            }

            return $this->previewLhp($request);
        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Error download file '.$th->getMessage(),
            ], 401);
        }
    }

    public function rePrint(Request $request) 
    {
        DB::beginTransaction();
        $header = LhpsLingHeader::where('no_lhp', $request->no_lhp)->where('is_active', true)->first();
        $header->count_print = $header->count_print + 1; 

        $detail = LhpsLingDetail::where('id_header', $header->id)->get();
        $custom = LhpsLingCustom::where('id_header', $header->id)->get();

        if ($header != null) {
            if ($header->file_qr == null) {
                $file_qr = new GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_LINGKUNGAN_KERJA', $header, $this->karyawan);
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
                ->whereView('DraftUdaraLingkunganKerja')
                ->render('downloadLHP');

            $header->file_lhp = $fileName;
            $header->save();
        }

        $servicePrint = new PrintLhp();
        $servicePrint->printByFilename($header->file_lhp, $detail);
        
        if (!$servicePrint) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal Melakukan Reprint Data', 'status' => '401'], 401);
        }
        
        DB::commit();

        return response()->json([
            'message' => 'Berhasil Melakukan Reprint Data ' . $request->no_sampel . ' berhasil!'
        ], 200);
    }

      public function previewLhp(Request $request)
    {
        try {
            $noLhp = $request->no_lhp ?? $request->cfr;
            if ($noLhp) {
                $header = LhpsLingHeader::where('no_lhp', $noLhp)->where('is_active', true)->first();
            } else {
                $header = LhpsLingHeader::where('no_sampel', $request->no_sampel)->where('is_active', true)->first();
            }

            if (!$header) {
                return response()->json(['message' => 'Header LHP tidak ditemukan'], 404);
            }

            if ($header->file_qr == null) {
                $file_qr = new \App\Services\GenerateQrDocumentLhp();
                $file_qr_path = $file_qr->insert('LHP_ULK', $header, $this->karyawan ?? 'System');
                if ($file_qr_path) {
                    $header->file_qr = $file_qr_path;
                    $header->save();
                }
            }

            $detail = LhpsLingDetail::where('id_header', $header->id)->get();
            $detail = collect($detail)->sortBy([
                ['tanggal_sampling', 'asc'],
                ['no_sampel', 'asc']
            ])->values()->toArray();

            $groupedByPage = collect(LhpsLingCustom::where('id_header', $header->id)->get())
                ->groupBy('page')
                ->toArray();

            foreach ($groupedByPage as $idx => $cstm) {
                $groupedByPage[$idx] = collect($cstm)->sortBy([
                    ['tanggal_sampling', 'asc'],
                    ['no_sampel', 'asc']
                ])->values()->toArray();
            }

            $pdfContent = LhpTemplate::setDataDetail($detail)
                ->setDataHeader($header)
                ->useLampiran(true)
                ->setDataCustom($groupedByPage)
                ->whereView('DraftUdaraAmbient')
                ->render('downloadLHPFinal', 'S');

            return response()->json([
                'data' => base64_encode($pdfContent),
                'is_base64' => true,
                'file_name' => $header->file_lhp ?? (str_replace("/", "_", $noLhp ?? $request->no_sampel) . '.pdf'),
                'message' => 'LHP berhasil dirender'
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'message' => 'Gagal merender LHP: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}