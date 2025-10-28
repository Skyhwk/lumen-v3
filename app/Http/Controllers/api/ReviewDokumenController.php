<?php

namespace App\Http\Controllers\api;

use App\Models\{
    MasterKategori,
    MasterSubKategori,
    MasterRegulasi,
    OrderDetail,
    Parameter,
    MasterBakumutu,
    TcOrderDetail,
    QuotationKontrakH,
    QuotationKontrakD,
    QuotationNonKontrak
};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Yajra\Datatables\Datatables;
use Illuminate\Database\Eloquent\Collection;

class ReviewDokumenController extends Controller
{

    // public function index()
    // {
    //     $today = Carbon::now()->format('Y-m-d');

    //     // Ambil semua parameter expired dan aktif, sekaligus
    //     $expiredParameters = Parameter::select('id', 'nama_lab', 'id_kategori')
    //         ->where('is_expired', 1)
    //         ->where('is_active', 1)
    //         ->get()
    //         ->groupBy('id_kategori');

    //     // Ambil semua kategori aktif
    //     $kategori = MasterKategori::where('is_active', 1)->get();

    //     // Siapkan array untuk id kategori_2
    //     $kategoriLabels = [];
    //     foreach ($kategori as $kat) {
    //         $kategoriLabels[$kat->id] = $kat->id . '-' . $kat->nama_kategori;
    //     }

    //     $allData = collect();

    //     foreach ($expiredParameters as $kategoriId => $parameters) {
    //         // Ambil list nama_lab
    //         $paramNames = $parameters->map(function ($param) {
    //             return $param->id . ';' . $param->nama_lab;
    //         })->values()->toArray();

    //         // Pastikan ada kategori label
    //         // if (!isset($kategoriLabels[$kategoriId])) continue;

    //         // $kategoriLabel = $kategoriLabels[$kategoriId];

    //         // Query order detail hanya untuk kategori dan parameter yang sesuai
    //         $orderDetails = OrderDetail::with('orderHeader')->where('is_active', true)
    //             ->whereDate('tanggal_sampling', '>', $today)
    //             // ->where('kategori_2', $kategoriLabel)
    //             ->where('kategori_2', '1-Air')
    //             ->where(function ($query) use ($paramNames) {
    //                 foreach ($paramNames as $name) {
    //                     $query->orWhereJsonContains('parameter', $name);
    //                 }
    //             })
    //             ->orderBy('tanggal_sampling', 'asc')
    //             ->get();

    //         $allData = $allData->merge($orderDetails);
    //     }

    //     return DataTables::of($allData)
    //         ->editColumn('tanggal_sampling', function ($row) {
    //             return Carbon::parse($row->tanggal_sampling)->format('Y-m-d');
    //         })
    //         ->make(true);
    // }


    public function index()
    {
        $today = Carbon::now()->format('Y-m-d');

        $expiredParameters = Parameter::select('id', 'nama_lab', 'id_kategori')
            ->where('is_expired', 1)
            ->where('is_active', 1)
            ->get();

        if ($expiredParameters->isEmpty()) {
            return DataTables::of(collect())->make(true);
        }

        $paramNames = $expiredParameters->map(function ($param) {
            return $param->id . ';' . $param->nama_lab;
        })->values()->toArray();



        $orderDetails = OrderDetail::with([
            'orderHeader'
        ])
            ->where('is_active', true)
            ->whereDate('tanggal_sampling', '>', $today)
            ->where('kategori_2', '1-Air')
            ->where(function ($query) use ($paramNames) {
                // Gunakan whereRaw untuk optimasi query JSON
                $conditions = collect($paramNames)->map(function ($name) {
                    return "JSON_CONTAINS(parameter, '\"" . addslashes($name) . "\"')";
                })->implode(' OR ');

                $query->whereRaw("({$conditions})");
            })
            ->orderBy('tanggal_sampling', 'asc')
            ->get();


        return DataTables::of($orderDetails)
            ->editColumn('tanggal_sampling', function ($row) {
                return Carbon::parse($row->tanggal_sampling)->format('Y-m-d');
            })
            ->make(true);
    }


    public function parameterData(Request $request)
    {
        $id_regulasi = explode('-', $request->regulasi)[0];
        $data = MasterBakumutu::where('id_regulasi', $id_regulasi)
            ->where('is_active', 1)
            ->get();

        return response()->json([
            'data' => $data
        ], 200);
        //  return DataTables::of($data)->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json($data);
    }

    public function getSubKategori(Request $request)
    {
        $data = MasterSubKategori::where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getRegulasi(Request $request)
    {
        $data = MasterRegulasi::with(['bakumutu'])->where('id_kategori', $request->id_kategori)->get();
        return response()->json($data);
    }

    public function getParameter(Request $request)
    {
        $data = Parameter::where('id_kategori', $request->id_kategori)
            ->where('is_active', 1)
            ->get();
        return response()->json($data);
    }
    public function getParameterNonExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('is_expired', 0)
            ->get();

        return response()->json($data);
    }
    public function getParameterExpired(Request $request)
    {
        $param = [];
        foreach ($request->params as $value) {
            $param[] = explode(';', $value)[1];
        }
        $data = Parameter::whereIn('nama_lab', $param)
            ->where('is_active', 1)
            ->where('id_kategori', (int) $request->kategori)
            ->where('is_expired', 1)
            ->get();

        return response()->json($data);
    }

    public function updateData(Request $request)
    {
        DB::beginTransaction();
        try {
            //---------------Proses Quotation--------------------------------

            $noQt = $request->no_document;
            if (\explode('/', $noQt)[1] == 'QTC') {
                // kontrak
                $dataQuotation = QuotationKontrakH::where('no_document', $noQt)->first();
                if (!$dataQuotation) {
                    return response()->json(['message' => 'Quotation Tidak Ditemukan'], 404);
                }

                $dataQuotationDetail = QuotationKontrakD::where('id_request_quotation_kontrak_h', $dataQuotation->id)->get();
                foreach ($dataQuotationDetail as $item) {
                    $dataPendukungSampling = array_values(json_decode($item->data_pendukung_sampling, true))[0]['data_sampling'] ?? json_decode($item->data_pendukung_sampling, true);
                    foreach ($dataPendukungSampling as $keys => $value) {
                        if ($value['regulasi'] == $request->regulasi) {
                            if ($request->existing !== null && $request->pengganti !== null) {
                                foreach ($value['parameter'] as $index => $param) {
                                    $key = array_search($param, $request->existing);
                                    if ($key !== false && isset($request->pengganti[$key])) {
                                        $value['parameter'][$index] = $request->pengganti[$key];
                                    }
                                }

                                $dataPendukungSampling[$keys]['parameter'] = $value['parameter'];
                            }
                        }
                    }
                    if ($request->existing !== null && $request->pengganti !== null) {
                        $item->data_pendukung_sampling = json_encode($dataPendukungSampling);
                        $item->save();
                    }
                }

                $dataPendukungHeader = json_decode($dataQuotation->data_pendukung_sampling, true);
                foreach ($dataPendukungHeader as $keys => $value) {
                    if ($value['regulasi'] == $request->regulasi) {
                        if ($request->existing !== null && $request->pengganti !== null) {
                            foreach ($value['parameter'] as $index => $param) {
                                $key = array_search($param, $request->existing);
                                if ($key !== false && isset($request->pengganti[$key])) {
                                    $value['parameter'][$index] = $request->pengganti[$key];
                                }
                            }
                            $dataPendukungHeader[$keys]['parameter'] = $value['parameter'];
                        }
                    }
                }
                if ($request->existing !== null && $request->pengganti !== null) {
                    $dataQuotation->data_pendukung_sampling = json_encode($dataPendukungHeader);
                    $dataQuotation->save();
                }
            } else {
                // nnon kontrak
                $dataQuotation = QuotationNonKontrak::where('no_document', $noQt)->first();
                $dataPendukungSampling = json_decode($dataQuotation->data_pendukung_sampling, true);

                foreach ($dataPendukungSampling as $keys => $value) {
                    if ($value['regulasi'] == $request->regulasi) {
                        if ($request->existing !== null && $request->pengganti !== null) {
                            foreach ($value['parameter'] as $index => $param) {
                                $key = array_search($param, $request->existing);
                                if ($key !== false && isset($request->pengganti[$key])) {
                                    $value['parameter'][$index] = $request->pengganti[$key];
                                }
                            }
                            $dataPendukungSampling[$keys]['parameter'] = $value['parameter'];
                        }
                    }
                }
                if ($request->existing !== null && $request->pengganti !== null) {
                    $dataQuotation->data_pendukung_sampling = json_encode($dataPendukungSampling);
                    $dataQuotation->save();
                }
            }
            //---------------End Proses Quotation--------------------------------

            //---------------Proses Order Detail--------------------------------
            $dataOrderDetail = OrderDetail::where('no_order', $request->no_order)->where('is_active', true)->get();

            foreach ($dataOrderDetail as $item) {
                $regulasiExist = json_decode($item->regulasi, true) ?? [];
                if ($regulasiExist == $request->regulasi) {
                    $parameterExist = json_decode($item->parameter, true) ?? [];
                    if ($request->existing !== null && $request->pengganti !== null) {
                        foreach ($parameterExist as $index => $param) {
                            $key = array_search($param, $request->existing);
                            if ($key !== false && isset($request->pengganti[$key])) {
                                $parameterExist[$index] = $request->pengganti[$key];
                            }
                        }

                        $item->parameter = json_encode($parameterExist) ?? [];
                        $item->save();

                        $record = new TcOrderDetail();
                        $record->id_order_detail = $item->id;
                        $record->no_sampel = $item->no_sampel;
                        $record->updated_tc_by = $this->karyawan;
                        $record->updated_tc_at = Carbon::now()->format('Y-m-d H:i:s');
                        $record->save();
                    }
                }
            }

            DB::commit();
            return response()->json([
                'status' => 200,
                'success' => true,
                'message' => 'Data berhasil diupdate',
                // 'data' => $data
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => 'Terjadi kesalahan: ' . $th->getMessage(),
                'line' => $th->getLine()
            ], 500);
        }
    }
}
