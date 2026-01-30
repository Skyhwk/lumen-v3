<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use DateTime;
use Mpdf;
use Carbon\Carbon;
use App\Models\OrderHeader;
use App\Models\Qsd;
use App\Models\MasterPelanggan;
use App\Models\SamplingPlan;
use App\Models\UploadQsd;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Str;
use App\Models\QuotationKontrakD;
use App\Models\QuotationNonKontrak;
use App\Models\AllQuote;
use App\Models\AllQTActive;
class QsdController extends Controller
{
    // percobaan 3
    public function index(Request $request)
    {
        // $data = OrderHeader::with([
        //     'getInvoice',
        //     'qsd',
        // ])
        // ->join('all_qt_active', 'order_header.no_document', '=', 'all_qt_active.no_document')
        // ->select(
        //     'order_header.id as id'
        //     , 'order_header.no_order'
        //     , 'order_header.no_document'
        //     , 'order_header.wilayah'
        //     , 'order_header.kategori_customer'
        //     , 'order_header.sub_kategori'
        //     , 'order_header.merk_customer'
        //     , 'order_header.bahan_customer'
        //     , 'order_header.grand_total as total_tagihan'
        //     , 'all_qt_active.tanggal_penawaran'
        //     , 'all_qt_active.nama_perusahaan'
        //     , 'all_qt_active.konsultan'
        //     , 'all_qt_active.periode_kontrak'
        //     , 'all_qt_active.biaya_akhir'
        //     , 'all_qt_active.total_discount'
        //     , 'all_qt_active.total_dpp'
        //     , 'all_qt_active.total_ppn'
        //     , 'all_qt_active.grand_total'
        // )
        // ->where('order_header.is_active', true)
        // ->where('all_qt_active.periode_kontrak', 'like', '%' . $request->periode . '%');


        // return Datatables::eloquent($data)
        //     ->filter(function ($query) use ($request) {
        //     if ($request->has('search') && !empty($request->search['value'])) {
        //         $keyword = strtolower($request->search['value']);
        //         $query->where(function ($q) use ($keyword) {
        //             $q->orWhere(DB::raw('LOWER(order_header.no_order)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.no_document)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.nama_perusahaan)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.konsultan)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.wilayah)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.kategori_customer)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.sub_kategori)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.bahan_customer)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(order_header.konsultan)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.biaya_akhir)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.total_discount)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.total_ppn)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.total_dpp)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.grand_total)'), 'LIKE', "%{$keyword}%")
        //                 ->orWhere(DB::raw('LOWER(all_qt_active.tanggal_penawaran)'), 'LIKE', "%{$keyword}%");
                    
        //             // Pencarian untuk periode_kontrak dengan format bulan tahun
        //             $bulanIndonesia = [
        //                 'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
        //                 'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
        //                 'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'
        //             ];
                    
        //             // Cek apakah keyword berisi nama bulan dalam bahasa Indonesia
        //             foreach ($bulanIndonesia as $namaBulan => $kodeBulan) {
        //                 if (strpos($keyword, $namaBulan) !== false) {
        //                     // Ekstrak tahun jika ada
        //                     preg_match('/\d{4}/', $keyword, $matches);
        //                     $tahun = !empty($matches) ? $matches[0] : date('Y');
                            
        //                     // Format pencarian untuk database (YYYY-MM)
        //                     $periodeSearch = $tahun . '-' . $kodeBulan;
        //                     $q->orWhere(DB::raw('SUBSTRING(all_quot.periode_kontrak, 1, 7)'), 'LIKE', "%{$periodeSearch}%");
        //                     break;
        //                 }
        //             }
        //         });
        //     }
        // })
        // ->make(true);

        $data = AllQTActive::with([
            'qsd',
            'getInvoice'
        ])->where('periode_kontrak', 'like', '%' . $request->periode . '%');

        // return Datatables::of($data)->make(true);
        return Datatables::eloquent($data)
            ->filter(function ($query) use ($request) {
            if ($request->has('search') && !empty($request->search['value'])) {
                $keyword = strtolower($request->search['value']);
                $query->where(function ($q) use ($keyword) {

                    $searchColumns = [
                        'no_order', 'no_document', 'nama_perusahaan', 'konsultan', 
                        'wilayah', 'kategori_customer', 'sub_kategori', 'bahan_customer',
                        'biaya_akhir', 'total_discount', 'total_ppn', 'total_dpp', 
                        'grand_total', 'tanggal_penawaran'
                    ];
                    
                    foreach ($searchColumns as $column) {
                        $q->orWhere(DB::raw("LOWER({$column})"), 'LIKE', "%{$keyword}%");
                    }

                    $bulanIndonesia = [
                        'januari' => '01', 'februari' => '02', 'maret' => '03', 'april' => '04',
                        'mei' => '05', 'juni' => '06', 'juli' => '07', 'agustus' => '08',
                        'september' => '09', 'oktober' => '10', 'november' => '11', 'desember' => '12'
                    ];
                    
                    // Cek apakah keyword berisi nama bulan dalam bahasa Indonesia
                    foreach ($bulanIndonesia as $namaBulan => $kodeBulan) {
                        if (strpos($keyword, $namaBulan) !== false) {
                            // Ekstrak tahun jika ada
                            preg_match('/\d{4}/', $keyword, $matches);
                            $tahun = !empty($matches) ? $matches[0] : date('Y');
                            
                            // Format pencarian untuk database (YYYY-MM)
                            $periodeSearch = $tahun . '-' . $kodeBulan;
                            $q->orWhere(DB::raw('SUBSTRING(all_quot.periode_kontrak, 1, 7)'), 'LIKE', "%{$periodeSearch}%");
                            break;
                        }
                    }
                });
            }
        })
        ->make(true);
    }

    public function showTotal(Request $request)
    {
        $query = OrderHeader::where('is_active', true)
            ->select(DB::raw('SUM(biaya_akhir) as biaya_akhir'));

        if ($request->periode) {
            $query->whereYear('tanggal_penawaran', $request->periode);
        }

        $rekapOrder = $query->get();

        return response()->json(array('grand_total' => $rekapOrder[0]->biaya_akhir));
    }


    public function saveQSD(Request $request)
    {
        $orderheader = OrderHeader::findOrFail($request->id);
        $pelanggan = MasterPelanggan::where('id_pelanggan', $orderheader->id_pelanggan)->first();

        if ($request->has('no_po')) {
            $orderheader->no_po = $request->no_po;
        }

        if ($request->has('kategori_customer')) {
            $orderheader->kategori_customer = $request->kategori_customer;
        }

        if ($request->has('sub_kategori')) {
            $orderheader->sub_kategori = $request->sub_kategori;
            $pelanggan->sub_kategori = $request->sub_kategori;
        }

        if ($request->has('merk_customer')) {
            $orderheader->merk_customer = $request->merk_customer;
            $pelanggan->merk_pelanggan = $request->merk_customer;
        }

        if ($request->has('bahan_customer')) {
            $orderheader->bahan_customer = $request->bahan_customer;
            $pelanggan->bahan_pelanggan = $request->bahan_customer;
        }

        $orderheader->updated_by = $this->karyawan;
        $orderheader->updated_at = Carbon::now();
        $orderheader->save();
        $pelanggan->save();

        $qsd = Qsd::firstOrCreate(['order_header_id' => $request->id]);

        if ($request->has('no_po')) {
            $qsd->no_po = $request->no_po;
        }

        if ($request->has('tgl_po')) {
            $qsd->tgl_po = $request->tgl_po;
        }
        if ($request->has('nilai_qsd')) {
            $qsd->nilai = $request->nilai_qsd;
        }
        if ($request->has('keterangan_qsd_edit')) {
            $qsd->keterangan = $request->keterangan_qsd_edit;
        }

        $qsd->order_header_id = $request->id;
        $qsd->save();

        return response()->json(['message' => 'Saved successfully.'], 200);
    }

    public function handleUpload(Request $request)
    {
        DB::beginTransaction();
        try {
            $files = $request->file('file_input');
            $fileNames = [];

            $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png'];
            $path = 'upload_document/';
            $savePath = public_path($path);

            if (!file_exists($savePath)) {
                mkdir($savePath, 0755, true);
            }

            foreach ($files as $file) {
                $originalExtension = $file->getClientOriginalExtension();

                if (!in_array(strtolower($originalExtension), $allowedExtensions)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Format file tidak didukung. Hanya PDF, JPG, JPEG, dan PNG yang diizinkan.'
                    ], 422);
                }

                $extension = 'pdf';
                $fileName = $this->generateFileName($file, $extension);

                if (strtolower($originalExtension) === 'pdf') {
                    $file->move($savePath, $fileName);
                } else {
                    $this->imageToPdf($file, $path, $fileName);
                }

                $fileNames[] = $fileName;
            }

            $uploadDocument = UploadQsd::firstOrCreate(['id_qsd' => $request->id_qsd]);
            $uploadDocument->title = $request->title;
            $uploadDocument->id_qsd = $request->id_qsd;
            $uploadDocument->description = $request->description;
            $uploadDocument->filename = json_encode($fileNames);
            $uploadDocument->created_by = $this->karyawan;
            $uploadDocument->created_at = Carbon::now();
            $uploadDocument->save();

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Dokumen berhasil diunggah',
                'data' => $uploadDocument
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 401);
        }
    }


    public function generateFileName($file, $extension)
    {
        $dateMonth = str_pad(date('m'), 2, '0', STR_PAD_LEFT) . str_pad(date('d'), 2, '0', STR_PAD_LEFT);

        $fileName = "UPD_" . $dateMonth . "_" . str_replace('.', '', microtime(true)) . "." . $extension;
        $filename = str_replace(' ', '_', $fileName);

        return $filename;
    }

    public function imageToPdf($file, $path, $fileName)
    {
        $tempImagePath = sys_get_temp_dir() . '/' . time() . '.' . $file->getClientOriginalExtension();
        file_put_contents($tempImagePath, file_get_contents($file->getRealPath()));

        $outputPath = public_path($path . $fileName);

        $mpdf = new Mpdf([
            'margin_left' => 0,
            'margin_right' => 0,
            'margin_top' => 0,
            'margin_bottom' => 0,
        ]);

        list($width, $height) = getimagesize($tempImagePath);

        if ($width > $height) {
            $mpdf = new Mpdf(['orientation' => 'L']);
        } else {
            $mpdf = new Mpdf(['orientation' => 'P']);
        }

        $imageData = file_get_contents($tempImagePath);
        $base64Image = base64_encode($imageData);
        $imgType = pathinfo($tempImagePath, PATHINFO_EXTENSION);

        $mpdf->WriteHTML('<div style="text-align: center;">
            <img src="data:image/' . $imgType . ';base64,' . $base64Image . '" style="max-width: 100%; height: auto;">
        </div>');

        // Simpan PDF
        $mpdf->Output($outputPath, 'F');

        // Hapus file sementara
        if (file_exists($tempImagePath)) {
            unlink($tempImagePath);
        }

        return true;
    }
}
