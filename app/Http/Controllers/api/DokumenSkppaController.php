<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Carbon\Carbon;

use App\Models\DokumenSkppa;
use App\Models\Ftc;
use App\Models\KelengkapanKonfirmasiQs;
use App\Models\LinkLhp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use App\Services\GetAtasan;
use App\Services\RenderDokumenBap;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;

class DokumenSkppaController extends Controller
{
    public function index()
    {
        $dokumenBap = DokumenSkppa::with('order');

        return Datatables::of($dokumenBap)->make(true);
    }

    public function handlePrintSkppa(Request $request)
    {
        DB::beginTransaction();
        try {
            $bap = DokumenSkppa::where('id', $request->id)->first();
            $bap->count_print = $bap->count_print + 1;
            $bap->is_printed = true;
            $bap->printed_by = $this->karyawan;
            $bap->printed_at = Carbon::now()->format('Y-m-d H:i:s');
            $bap->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil Print BAP' . $bap->no_document,
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error download file ' . $th->getMessage(),
            ], 401);
        }
    }
}
