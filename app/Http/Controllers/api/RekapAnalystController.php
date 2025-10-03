<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use App\Models\MasterKategori;
use App\Models\WsValueAir;
use App\Models\MasterSubKategori;
use App\Models\MasterRegulasi;
use App\Models\WsValueUdara;

use App\Models\IklimHeader;
use App\Models\GetaranHeader;
use App\Models\KebisinganHeader;
use App\Models\PencahayaanHeader;
use App\Models\LingkunganHeader;
use App\Models\DirectLainHeader;
use App\Models\ErgonomiHeader;
use App\Models\SinarUvHeader;
use App\Models\MedanLmHeader;
use App\Models\PsikologiHeader;
use App\Models\DebuPersonalHeader;
use App\Models\EmisiCerobongHeader;
use App\Models\IsokinetikHeader;
use App\Models\MicrobioHeader;
use App\Models\Subkontrak;

use Carbon\Carbon;
use Yajra\DataTables\DataTables;
use Exception;

class RekapAnalystController extends Controller
{
    private $categoryLingkunganHidup = [11];
	private $categoryLingkunganKerja = [27];
	private $categoryMicrobio = [12, 46];
	private $categoryKebisingan = [23, 24, 25, 26];
	private $categoryGetaran = [13, 14, 15, 16, 17, 18, 19, 20];
	private $categoryPencahayaan = [28];
	private $categoryIklim = [21];

    public function index(Request $request)
    {
        // dd(\explode('-', $request->date)[1]);
        list($year, $month) = explode('-', $request->date);
        $month = str_pad($month, 2, '0', STR_PAD_LEFT);
        if($request->kategori == '') {
            $kategori = "1-Air";
        }else {
            $kategori = $request->kategori;
        }

        $data = OrderDetail::with(['TrackingSatu', 'wsValueAir'])->where('is_active', true)
            // ->whereHas('TrackingSatu')
            ->where('kategori_2', $kategori)
            ->whereMonth('tanggal_terima', $month)
            ->whereYear('tanggal_terima', $year)
            ->orderBy('id', 'desc');
        // dd($data);
        return DataTables::of($data)
            ->filterColumn('tanggal_sampling', function ($query, $keyword) {
                $query->where('tanggal_sampling', 'like', "%$keyword%");
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%$keyword%");
            })
            ->filterColumn('no_order', function ($query, $keyword) {
                $query->where('no_order', 'like', "%$keyword%");
            })
            ->filterColumn('tracking_satu', function ($query, $keyword) {
                $query->whereHas('TrackingSatu', function ($q) use ($keyword) {
                    $q->where('ftc_laboratory', 'like', "%$keyword%");
                });
            })
            ->filterColumn('tanggal_terima', function ($query, $keyword) {
                $query->where('tanggal_terima', 'like', "%$keyword%");
            })
            ->filterColumn('kategori_2', function ($query, $keyword) {
                $query->where('kategori_2', 'like', "%$keyword%");
            })
            ->make(true);
    }

    public function detail(Request $request)
    {
        try{
            $checkOrder = OrderDetail::where('no_sampel', $request->no_sampel)->first();
            if (is_null($checkOrder)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data not found',
                ], 404);
            }

            if($checkOrder->kategori_2 == '1-Air') {
                $data = WsValueAir::with(['gravimetri', 'titrimetri', 'colorimetri','subkontrak'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('status', 0)
                    ->where('is_active', 1);

                // ->where(function ($query) {
                //     $query->whereHas('colorimetri', function ($q) {
                //         $q->where('is_approved', 1);
                //     })
                //     ->orWhereHas('gravimetri', function ($q) {
                //         $q->where('is_approved', 1);
                //     })
                //     ->orWhereHas('titrimetri', function ($q) {
                //         $q->where('is_approved', 1);
                //     });
                // });

                return Datatables::of($data)->make(true);
            }else if($checkOrder->kategori_2 == '4-Udara') {
                if (in_array($checkOrder->kategori_3, $this->categoryLingkunganKerja)) {
                    $parameters = json_decode(html_entity_decode($checkOrder->parameter), true);
                    $parameterArray = is_array($parameters) ? array_map('trim', explode(';', $parameters[0])) : [];
                    // ERGONOMI
                    if ($parameterArray[1] == 'Ergonomi') {
                        $data = ErgonomiHeader::with('datalapangan')
                            ->where('no_sampel', $request->no_sampel)
                            ->where('is_approve', true)
                            ->where('is_active', true)
                            ->select('*') // pastikan select ada
                            ->addSelect(DB::raw("'ergonomi' as data_type"));

                        return DataTables::of($data)->make(true);
                    } else if ($parameterArray[1] == 'Sinar UV') {
                        $data = SinarUvHeader::with('datalapangan', '')
                            ->where('no_sampel', $request->no_sampel)
                            ->where('is_approved', true)
                            ->where('is_active', true)
                            ->select('*')
                            ->addSelect(DB::raw("'sinar_uv' as data_type"));
                        foreach ($data as $item) {
                            $item->ws_udara->parsed_hasil = json_decode($item->ws_udara->hasil1);
                        }
                        return Datatables::of($data)->make(true);
                    } else if ($parameterArray[1] == 'Debu (P8J)') {
                        $data = DebuPersonalHeader::with('data_lapangan', 'ws_lingkungan')
                            ->where('no_sampel', $request->no_sampel)
                            ->where('is_approved', true)
                            ->where('is_active', true)
                            ->select('*')
                            ->addSelect(DB::raw("'debu_personal' as data_type"));


                        return Datatables::of($data)->make(true);
                    } else if ($parameterArray[1] == 'Medan Magnit Statis' || $parameterArray[1] == 'Medan Listrik' || $parameterArray[1] == 'Power Density' || $parameterArray[1] == 'Gelombang Elektro') {
                        $data = MedanLmHeader::with('datalapangan', 'ws_udara')
                            ->where('no_sampel', $request->no_sampel)
                            ->where('is_approve', true)
                            ->where('is_active', true)
                            ->select('*')
                            ->addSelect(DB::raw("'medan_lm' as data_type"))->get();
                        // dd($data->get());
                        foreach ($data as $item) {
                            $item->ws_udara->parsed_hasil = json_decode($item->ws_udara->hasil1);
                        }

                        return Datatables::of($data)->make(true);
                    } else if ($parameterArray[1] == 'Psikologi') {
                        $data = PsikologiHeader::with('data_lapangan')
                            ->where('no_sampel', $request->no_sampel)
                            ->where('is_approve', true)
                            ->where('is_active', true)
                            ->select('*')
                            ->addSelect(DB::raw("'psikologi' as data_type"))
                            ->first();
                        $data->data_lapangan->hasil = json_decode($data->data_lapangan->hasil);
                        return response()->json($data, 200);
                    }
                    $directData = DirectLainHeader::with(['ws_udara'])
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('status', 0)
                        ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                        ->addSelect(DB::raw("'direct' as data_type"))
                        ->get();

                    $lingkunganData = LingkunganHeader::with('ws_value_linkungan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('status', 0)
                        ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                        ->addSelect(DB::raw("'lingkungan' as data_type"))
                        ->get();
                    $subkontrak = Subkontrak::with(['ws_value_linkungan'])
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                        ->addSelect(DB::raw("'subKontrak' as data_type"))
                        ->get();



                    $combinedData = collect()
                        ->merge($lingkunganData)
                        ->merge($subkontrak)
                        ->merge($directData);


                    $processedData = $combinedData->map(function ($item) {
                        switch ($item->data_type) {
                            case 'lingkungan':
                                $item->source = 'Lingkungan';
                                break;
                            case 'subKontrak':
                                $item->source = 'Subkontrak';
                                break;
                            case 'direct':
                                $item->source = 'Direct Lain';
                                break;
                        }
                        return $item;
                    });

                    $processedData = $combinedData->map(function ($item) {
                        if ($item->ws_udara) {
                            $item->ws_udara = collect($item->ws_udara)->merge([
                                'parsed_hasil' => json_decode($item->ws_udara->hasil1)
                            ]);
                        }
                        return $item;
                    });

                    return Datatables::of($processedData)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryLingkunganHidup)) {
                    $lingkunganData = LingkunganHeader::with('ws_value_linkungan')
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('status', 0)
                        ->select('id', 'no_sampel', 'id_parameter', 'parameter', 'lhps', 'is_approved', 'approved_by', 'approved_at', 'created_by', 'created_at', 'status', 'is_active')
                        ->addSelect(DB::raw("'lingkungan' as data_type"))
                        ->get();
                    $subkontrak = Subkontrak::with(['ws_value_linkungan'])
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->select('id', 'no_sampel', 'parameter', 'lhps', 'is_approve', 'approved_by', 'approved_at', 'created_by', 'created_at', 'lhps as status', 'is_active')
                        ->addSelect(DB::raw("'subKontrak' as data_type"))
                        ->get();
                    $combinedData = collect()
                        ->merge($lingkunganData)
                        ->merge($subkontrak);


                    $processedData = $combinedData->map(function ($item) {
                        switch ($item->data_type) {
                            case 'lingkungan':
                                $item->source = 'Lingkungan';
                                break;
                            case 'subKontrak':
                                $item->source = 'Subkontrak';
                                break;
                        }
                        return $item;
                    });
                    return Datatables::of($processedData)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryKebisingan)) {
                    $data = KebisinganHeader::with(['ws_udara'])->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('status', 0);

                    return Datatables::of($data)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryMicrobio)) {
                    $data = MicrobioHeader::with(['ws_value'])->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('status', 0);
                    return Datatables::of($data)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryPencahayaan)) {
                    $data = PencahayaanHeader::with(['data_lapangan', 'ws_udara'])
                        ->where('no_sampel', $request->no_sampel)
                        ->where('is_approved', 1)
                        ->where('status', 0);

                    return Datatables::of($data)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryIklim)) {
                    $data = IklimHeader::with(['iklim_panas', 'iklim_dingin', 'ws_udara'])->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('status', 0)->get();
                    foreach ($data as $item) {
                        $item->ws_udara->parsed_hasil = json_decode($item->ws_udara->hasil2);
                    }

                    return Datatables::of($data)->make(true);
                } else if (in_array($checkOrder->kategori_3, $this->categoryGetaran)) {

                    $data = GetaranHeader::with(['lapangan_getaran', 'lapangan_getaran_personal', 'ws_udara'])->where('no_sampel', $request->no_sampel)
                        ->where('is_approve', 1)
                        ->where('status', 0);

                    return Datatables::of($data)->make(true);
                } else {
                    return response()->json([
                        'message' => 'Kategori tidak sesuai',
                        'status' => 404,
                    ], 404);
                }
            }else if( $checkOrder->kategori_2 == '5-Emisi'){
                $data1 = IsokinetikHeader::with(['method1', 'method2', 'method3', 'method4', 'method5', 'method6'])
                    ->where('is_approve', 1)
                    ->where('is_active', 1)
                    ->where('parameter', '!=', 'Iso-ResTime')
                    // ->whereIn('parameter', $paramOrder)
                    ->where('no_sampel', $request->no_sampel)
                    ->get()->map(function ($item) {
                        $item['data_type'] = 'isokinetik_header';
                        return $item;
                    });

                $data2 = EmisiCerobongHeader::with(['ws_value_cerobong', 'data_lapangan'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approved', 1)
                    // ->whereIn('parameter', $paramOrder)
                    ->where('is_active', 1)
                    ->get()
                    ->map(function ($item) {
                        $item['data_type'] = 'emisi_cerobong_header';

                        if (
                            isset($item['data_lapangan']['arah_pengamat_opasitas']) &&
                            is_string($item['data_lapangan']['arah_pengamat_opasitas'])
                        ) {
                            $item['data_lapangan']['arah_pengamat_opasitas'] = json_decode($item['data_lapangan']['arah_pengamat_opasitas'], true);
                        }

                        if (
                            isset($item['data_lapangan']['jarak_pengamat']) &&
                            is_string($item['data_lapangan']['jarak_pengamat'])
                        ) {
                            $item['data_lapangan']['jarak_pengamat'] = json_decode($item['data_lapangan']['jarak_pengamat'], true);
                        }

                        if (
                            isset($item['data_lapangan']['warna_emisi']) &&
                            is_string($item['data_lapangan']['warna_emisi'])
                        ) {
                            $item['data_lapangan']['warna_emisi'] = json_decode($item['data_lapangan']['warna_emisi'], true);
                        }

                        if (
                            isset($item['data_lapangan']['warna_latar']) &&
                            is_string($item['data_lapangan']['warna_latar'])
                        ) {
                            $item['data_lapangan']['warna_latar'] = json_decode($item['data_lapangan']['warna_latar'], true);
                        }

                        return $item;
                    });

                $data3 = Subkontrak::with(['ws_value_cerobong'])
                    ->where('no_sampel', $request->no_sampel)
                    ->where('is_approve', 1)
                    // ->whereIn('parameter', $paramOrder)
                    ->where('is_active', 1)
                    ->get()
                    ->map(function ($item) {
                        $item['data_type'] = 'subkontrak';
                        return $item;
                    });

                $data1Arr = $data1->toArray();
                $data2Arr = $data2->toArray();
                $data3Arr = $data3->toArray();

                $data = array_merge($data1Arr, $data2Arr, $data3Arr);

                return Datatables::of($data)->make(true);
            }
        }catch(\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'status' => 404,
            ], 404);
        }
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Available data category retrieved successfully',
        ], 201);
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

}
