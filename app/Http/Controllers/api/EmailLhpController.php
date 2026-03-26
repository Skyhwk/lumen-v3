<?php

namespace App\Http\Controllers\api;

use App\Models\EmailLhp;
use App\Models\OrderDetail;
use App\Models\OrderHeader;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;

use App\Services\GetBawahan;

use Datatables;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class EmailLhpController extends Controller
{
    public function index(Request $request)
    {   
        $rekapOrder = OrderHeader::with(['orderDetail', 'emailLhp', 'sales'])
        ->whereYear('created_at', $request->periode)
        ->where('is_active', true);


        if ($request->id_cabang)
            $rekapOrder->where('id_cabang', $request->id_cabang);

        // if ($request->periode)
        //     $rekapOrder->whereYear('tanggal_penawaran', $request->periode);


        // dd($rekapOrder->get());
        
        $karyawan = $request->attributes->has('user') ? $request->attributes->get('user')->karyawan : null;
        
        if($karyawan->id_jabatan == 24) {
            $rekapOrder->where('sales_id', $this->user_id);
        } else if($karyawan->id_jabatan == 21) {
            $bawahan = GetBawahan::where('id', $this->user_id)->get()->pluck('id')->toArray();
            $rekapOrder->whereIn('sales_id', $bawahan);
        } else if($karyawan->id_jabatan == 148) {
            $rekapOrder->where('sales_id', $this->user_id);
        }
        
        return Datatables::of($rekapOrder)
            ->filterColumn('jenis_kontrak', function ($query, $keyword) {
                if($keyword == 'Kontrak') {
                    $query->where('no_document', 'like', '%QTC/%');
                } else if($keyword == 'Non Kontrak') {
                    $query->where('no_document', 'like', '%QT/%');
                }
            })
            ->filterColumn('sales_name', function ($query, $keyword) {
                $query->whereHas('sales', function ($query) use ($keyword) {
                    $query->where('nama_lengkap', 'like', '%' . $keyword . '%');
                });
            })
            ->addColumn('email_lhp', function ($row) {
                $data = $row->emailLhp->where('no_order', $row->no_order)->first();
                return $data ? $data->toArray() : null;
            })
            ->make(true);
    }

    public function store(Request $request)
    {
        $cek = EmailLhp::where('id_pelanggan', $request->id_pelanggan)->where('no_order', $request->no_order)->first();

        if($cek) {
            // update
            $data = $cek;
            $data->email_to = ($cek->email != $request->email_to) ? $request->email_to : $cek->email;
            $data->email_cc = ($request->email_cc != '' && $cek->email_cc != $request->email_cc) ? $request->email_cc : $cek->email_cc;
            $data->save();
        } else {
            //create
            $data = new EmailLhp();
            $data->id_pelanggan = $request->id_pelanggan;
            $data->no_order = $request->no_order;
            $data->email_to = $request->email_to;
            $data->email_cc = $request->email_cc != '' ? $request->email_cc : null;
            $data->save();
        }
        

        return response()->json([
            'message' => 'Email LHP berhasil disimpan',
            'status' => true,
        ], 200);
    }

    public function delete(Request $request)
    {
        $data = EmailLhp::where('id_pelanggan', $request->id_pelanggan)
        ->where('no_order', $request->no_order)
        ->first();
        $data->delete();
        return response()->json([
            'message' => 'Email LHP berhasil dihapus',
            'status' => true,
        ], 200);
    }

    public function getCabang()
    {
        return response()->json(MasterCabang::whereIn('id', $this->privilageCabang)->where('is_active', true)->get());
    }
}
