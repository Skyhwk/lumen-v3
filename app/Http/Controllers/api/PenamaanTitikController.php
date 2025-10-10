<?php

namespace App\Http\Controllers\api;

use Datatables;
use App\Models\Parameter;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\MasterBakumutu;
use App\Models\MasterSubKategori;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;

class PenamaanTitikController extends Controller
{
    public function index(Request $request)
    {
        $order = OrderDetail::with([
            'orderHeader:id,no_document,no_order,nama_perusahaan,flag_status,no_tlp_perusahaan,nama_pic_order,no_pic_order,konsultan,tanggal_order,created_at,created_by,updated_at,updated_by',
            'codingSampling',
            'orderHeader.user.karyawan', // Created By
            'orderHeader.user2.karyawan' // Updated By
        ])
            ->select('no_order AS orderd', 'id_order_header', 'is_active', 'periode')
            ->where(['kontrak' => $request->mode == 'kontrak' ? 'C' : 'N', 'is_active' => true])
            ->distinct();

        if ($request->id_cabang) $order->whereHas('orderHeader', fn($q) => $q->where('id_cabang', $request->id_cabang));
        if ($request->periode) $order->whereHas('orderHeader', fn($q) => $q->whereYear('tanggal_order', $request->periode));

        return Datatables::of($order)->make(true);
    }

    // public function index(Request $request)
    // {
    //     $data = OrderDetail::select(
    //             'order_detail.no_order AS orderd',
    //             'order_detail.id_order_header',
    //             'order_detail.is_active',
    //             'order_detail.periode',
    //         )
    //         ->distinct()
    //         ->leftJoin('order_header', 'order_detail.id_order_header', '=', 'order_header.id')
    //         ->leftJoin('coding_sampling', 'order_detail.no_sampel', '=', 'coding_sampling.no_sampel')
    //         ->where('order_header.id_cabang', $request->id_cabang)
    //         ->where('order_detail.is_active', true)
    //         ->whereYear('order_header.tgl_penawaran', $request->periode ?? date('Y'))
    //         ->orderBy('id', 'DESC');

    //     if ($request->mode === 'kontrak') {
    //         $data->where('order_detail.kontrak', 'C');
    //     }

    //     if ($request->mode === 'non_kontrak' || $request->mode === 'null') {
    //         $data->where('order_detail.kontrak', 'N');
    //     }

    //     if ($request->action === 'getUpdate') {
    //         $data->whereNull('coding_sampling.no_sampel');
    //     }

    //     if ($request->action === 'getEdit') {
    //         $data->whereNotNull('coding_sampling.no_sampel');
    //     }

    //     // Filtering menggunakan Datatables
    //     return DataTables::of($data)
    //         ->filter(function ($query) use ($request) {
    //             if ($request->has('search') && !empty($request->search['value'])) {
    //                 $keyword = strtolower($request->search['value']);
    //                 $query->where(function ($q) use ($keyword) {
    //                     $q->orWhere(DB::raw('LOWER(order_header.id)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.no_document)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_detail.no_order)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.nama_perusahaan)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.flag_status)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.no_tlp_perusahaan)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.konsultan)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.nama_pic_order)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.no_pic_order)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_detail.periode)'), 'LIKE', "%{$keyword}%")
    //                         ->orWhere(DB::raw('LOWER(order_header.tgl_penawaran)'), 'LIKE', "%{$keyword}%");
    //                 });
    //             }
    //         })
    //         ->make(true);
    // }

    public function show(Request $request)
    {
        $orderDetail = OrderDetail::where(['id_order_header' => $request->id, 'is_active' => true]);

        return Datatables::of($orderDetail)->make(true);
    }

    public function getCabang()
    {
        return response()->json(MasterCabang::whereIn('id', $this->privilageCabang)->where('is_active', true)->get());
    }

    public function getKategori()
    {
        return response()->json(MasterKategori::where('is_active', true)->get());
    }

    public function getSubkategori(Request $request)
    {
        return response()->json(MasterSubKategori::where(['is_active' => true, 'id_kategori' => $request->id_kategori])->get());
    }

    public function getParameter(Request $request)
    {
        $param = [];
        $bakumutu = MasterBakumutu::where(['id_regulasi' => explode('-', $request->regulasi)[0], 'is_active' => true])->get();
        foreach ($bakumutu as $a) array_push($param, $a->id_parameter . ';' . $a->parameter);

        $data = Parameter::where(['id_kategori' => $request->id_kategori, 'is_active' => true])->get();

        return response()->json(['data' => $data, 'value' => $param, 'status' => '200'], 200);
    }

    public function saveOrderDetail(Request $request)
    {
        DB::beginTransaction();
        try {
            $orderDetail = OrderDetail::where(['id' => $request->id, 'is_active' => true])->first();
            $orderDetail->tgl_sampling = $request->tgl_tugas;
            $orderDetail->tgl_terima = $request->tgl_terima ?: null;
            $orderDetail->keterangan_1 = $request->keterangan_1;
            $orderDetail->keterangan_2 = $request->keterangan_2;
            $orderDetail->kategori_1 = $request->kategori_1;
            $orderDetail->kategori_2 = $request->kategori_2;
            $orderDetail->kategori_3 = $request->kategori_3;
            $orderDetail->updated_by = $this->user_id;
            $orderDetail->updated_at = date('Y-m-d H:i:s');

            $orderDetail->save();

            DB::commit();
            return response()->json(['message' => 'Saved Successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['message' => $e->getMessage()], 401);
        }
    }
}
