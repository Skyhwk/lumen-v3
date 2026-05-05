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
            $skppa = DokumenSkppa::where('id', $request->id)->first();
            $skppa->count_print = $skppa->count_print + 1;
            $skppa->is_printed = true;
            $skppa->printed_by = $this->karyawan;
            $skppa->printed_at = Carbon::now()->format('Y-m-d H:i:s');
            $skppa->save();

            DB::commit();
            return response()->json([
                'message' => 'Berhasil Print Dokumen SKPPA' . $skppa->no_document,
            ], 200);
        } catch (\Exception $th) {
            DB::rollBack();
            return response()->json([
                'message' => 'Error download file ' . $th->getMessage(),
            ], 401);
        }
    }
}
