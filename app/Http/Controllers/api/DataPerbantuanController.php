<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Validator;
use App\Models\DataPerbantuan;
use App\Http\Controllers\Controller;
use App\Models\MasterKaryawan;
use App\Models\MasterPelanggan;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Exception;
use Illuminate\Support\Carbon;

class DataPerbantuanController extends Controller
{
    public function index(Request $request)
    {
        $data = DataPerbantuan::with(['sales'])->where('karyawan_id', $this->user_id)->where('type_keterangan', null);
        return Datatables::of($data)->make(true);
    }

    public function indexAddData(Request $request)
    {
        $existingCustomerIds = DataPerbantuan::whereNotNull('id_pelanggan')
            ->pluck('id_pelanggan');
        $data = MasterPelanggan::where('sales_id', '!=', 127)
            ->whereNotNull('sales_id')
            ->where('is_active', true)
            ->whereNotIn('id_pelanggan', $existingCustomerIds);

        return Datatables::of($data)->make(true);
    }

    public function getKaryawan(Request $request)
    {
        $search = $request->search;

        $karyawan = MasterKaryawan::where('is_active', true)
            ->when($search, function ($q) use ($search) {
                $q->where('nama_lengkap', 'like', '%' . $search . '%');
            })
            ->select('id', 'nama_lengkap')
            ->limit(50)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $karyawan,
            'message' => 'Available karyawan data retrieved successfully',
        ], 200);
    }

    public function updateTypeKeterangan(Request $request)
    {
        $data = DataPerbantuan::where('id', $request->id)->first();
        $data->type_keterangan = $request->type_keterangan;
        if ($request->type_keterangan == "Not Interest" || $request->type_keterangan == "Number Invalid") {
            $data->is_checked = true;
        }
        $data->save();
        return response()->json(['data' => $data], 200);
    }

    public function storeAddData(Request $request)
    {
        try {
            $customer = MasterPelanggan::with(['pic_pelanggan', 'kontak_pelanggan'])->whereIn('id', $request->selected_ids)->get();
            $karyawan = MasterKaryawan::where('id', $request->karyawan_id)->first();

            $inputData = [];

            foreach ($customer as $item) {
                $inputData[] = [
                    'nama_pelanggan' => $item->nama_pelanggan,
                    'id_pelanggan'   => $item->id_pelanggan,
                    'sales_id'       => $item->sales_id,
                    'karyawan_id'    => $karyawan->id,

                    'nama_pic' => optional($item->pic_pelanggan->last())->nama_pic,
                    'no_pic' => optional($item->pic_pelanggan->last())->no_tlp_pic,
                    'no_perusahaan' => optional($item->kontak_pelanggan->last())->no_tlp_perusahaan,

                    'created_at' => Carbon::now(),
                    'created_by' => $this->karyawan,
                ];
            }

            DataPerbantuan::insert($inputData);

            return response()->json(['message' => 'Data perbantuan berhasil ditambahkan'], 200);
        } catch (\Exception $th) {
            return response()->json(['message' => 'Gagal menambahkan data perbantuan', 'error' => $th->getMessage()], 500);
        }
    }
}
