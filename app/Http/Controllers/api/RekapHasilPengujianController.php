<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Illuminate\Http\Request;

use Carbon\Carbon;

Carbon::setLocale('id');

use App\Models\{LinkLhp, MasterKaryawan, QuotationKontrakH, QuotationNonKontrak};
use App\Services\{GetAtasan, SendEmail};

class RekapHasilPengujianController extends Controller
{
    public function index()
    {
        $linkLhp = LinkLhp::with('token')->where('is_emailed', true)->latest()->get();

        return Datatables::of($linkLhp)->make(true);
    }

    public function reject(Request $request) 
    {
        $linkLhp = LinkLhp::find($request->id);
        $linkLhp->update(['is_emailed' => false]);

        return response()->json(['message' => 'Data berhasil direject'], 200);
    }
}
