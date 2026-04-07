<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use Validator;
use App\Models\DataPerbantuan;
use App\Http\Controllers\Controller;
use App\Models\DFUS;
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
        $lastDfus = DFUS::select('id_pelanggan', DB::raw('MAX(tanggal) as last_dfus_tanggal'))
            ->groupBy('id_pelanggan');

        $data = MasterPelanggan::query()
            ->leftJoinSub($lastDfus, 'last_dfus', function ($join) {
                $join->on('last_dfus.id_pelanggan', '=', 'master_pelanggan.id_pelanggan');
            })
            ->where('master_pelanggan.sales_id', '!=', 127)
            ->whereNotNull('master_pelanggan.sales_id')
            ->where('master_pelanggan.is_active', true)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('data_perbantuan')
                    ->whereColumn('data_perbantuan.id_pelanggan', 'master_pelanggan.id_pelanggan');
            })
            ->select(
                'master_pelanggan.*',
                'last_dfus.last_dfus_tanggal'
            )
            ->orderByRaw('last_dfus.last_dfus_tanggal IS NULL DESC')
            ->orderBy('last_dfus.last_dfus_tanggal', 'asc');

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
        $notAnswer = false;
        if ($request->type_keterangan == "Not Answer") {
            $notAnswer = true;
        };

        $data = DataPerbantuan::where('id', $request->id)->first();

        if ($notAnswer) {
            DataPerbantuan::create([
                'nama_pelanggan' => $data->nama_pelanggan,
                'id_pelanggan'   => $data->id_pelanggan,
                'sales_id'       => $data->sales_id,
                'karyawan_id'    => $data->karyawan_id,

                'nama_pic' => $data->nama_pic,
                'no_pic' => $data->no_pic,
                'no_perusahaan' => $data->no_perusahaan,

                'created_at' => $data->created_at,
                'created_by' => $data->created_by,
            ]);
            $data->delete();
            return response()->json(['message' => 'Data perbantuan berhasil diupdate'], 200);
        } else {
            $data->type_keterangan = $request->type_keterangan;
            if ($request->type_keterangan == "Not Interest" || $request->type_keterangan == "Number Invalid") {
                $data->is_checked = true;
            }
            $data->save();
            return response()->json(['message' => 'Data perbantuan berhasil diupdate'], 200);
        }
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
