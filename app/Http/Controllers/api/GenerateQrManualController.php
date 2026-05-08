<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Model
use App\Models\OrderDetail;
use App\Models\PengesahanLhp;
use App\Models\QrDocument;

// Library
use Yajra\DataTables\DataTables;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Carbon\Carbon;

class GenerateQrManualController extends Controller
{

    public function index(Request $request){
        $qrDocuments = QrDocument::where('created_by', 'System Manual')->get();
        
        return DataTables::of($qrDocuments)->make(true);
    }

    public function generateQrDocument(Request $request){
        DB::beginTransaction();
        try {
            $order_detail = OrderDetail::where('cfr', $request->no_lhp)->where('is_active', 1)->first();
            if(!$order_detail){
                return response()->json([
                    'message' => "Data order dengan CFR $request->no_lhp tidak ditemukan"
                ], 404);
            }
            $pengesahan = PengesahanLhp::where('berlaku_mulai', '<=', $request->tanggal_lhp)
                ->orderByDesc('berlaku_mulai')
                ->first();
            $filename = 'LHP_' . \str_replace("/", "_", $order_detail->cfr);
            $path = public_path() . "/qr_documents/" . $filename . '.svg';
            if(!file_exists($path)){
                $link = 'https://www.intilab.com/validation/';
                $unique = 'isldc' . (int) floor(microtime(true) * 1000);
        
                QrCode::size(200)->generate($link . $unique, $path);
                $dataQr = [
                    'type_document' => $request->tipe_dokumen,
                    'kode_qr' => $unique,
                    'file' => $filename,
                    'data' => json_encode([
                        'Nomor_LHP' => $order_detail->cfr,
                        'Nama_Pelanggan' => $order_detail->nama_perusahaan,
                        'Pelanggan_ID' => substr($order_detail->no_order, 0, 6),
                        'Tanggal_Pengesahan' => Carbon::parse($request->tanggal_lhp)->locale('id')->isoFormat('DD MMMM YYYY'),
                        'Disahkan_Oleh' => $pengesahan->nama_karyawan ?? 'Abidah Walfathiyyah',
                        'Jabatan' => $pengesahan->jabatan_karyawan ?? 'Technical Control Supervisor'
                    ]),
                    'created_at' => Carbon::now(),
                    'created_by' => 'System Manual',
                ];
                QrDocument::create($dataQr);

                DB::commit();
                return response()->json([
                    'message' => "QR Document $filename berhasil digenerate"
                ], 200);
            } else {
                DB::rollback();
                return response()->json([
                    'message' => "QR Document $filename sudah ada pada server"
                ], 401);
            }
        } catch (Exception $e){
            DB::rollback();
            return response()->json([
                'message' => $e->getMessage()
            ], 401);
        }
    }
}