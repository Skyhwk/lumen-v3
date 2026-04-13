<?php

namespace App\Http\Controllers\api;

use App\Models\PengajuanFeeSampling;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class FeeSamplingController extends Controller
{
    public function index(Request $request)
    {
        $data = PengajuanFeeSampling::with(['detail_fee' => function ($q) {
            $q->where('is_approve', 1);
        }])
            ->where('is_approve_finance', 1)
            ->whereNotNull('transfer_date')
            ->where('is_upload_bukti_pembayaran', 0);

        return Datatables::of($data)->make(true);
    }
    
    public function uploadFile(Request $request)
    {
        DB::beginTransaction();
        try {
            $file = $request->file('file_input');

            // Validasi file
            // if (!$file || $file->getClientOriginalExtension() !== 'pdf') {
            //     return response()->json(['error' => 'File tidak valid. Harus .pdf'], 400);
            // }

            $claim = PengajuanFeeSampling::find($request->id);
            // Pastikan folder invoice ada
            $folder = public_path('bukti_pembayaran_fee_sampling');
            if (!file_exists($folder)) {
                mkdir($folder, 0777, true);
            }

            // Generate nama file unik
            $fileName = str_replace(".", "", microtime(true)) . '_' . Carbon::now()->format('YmdHis') . '.' . $file->getClientOriginalExtension();

            // Simpan file
            $file->move($folder, $fileName);
            $claim->filename = $fileName;
            $claim->is_upload_bukti_pembayaran = 1;
            $claim->save();

            DB::commit();
            return response()->json([
                'success'  => 'Sukses menyimpan file upload',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Terjadi kesalahan server',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}