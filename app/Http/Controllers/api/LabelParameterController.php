<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\TemplateStp;
use App\Models\TemplateAnalyst;
use App\Models\MasterCabang;
use App\Models\MasterKategori;
use App\Models\CategorySample;
use App\Models\Parameter;
use App\Models\User;
use App\Models\Usertoken;
use App\Models\Requestlog;
use App\Models\OrderDetail;
use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\Gravimetri;
use App\Models\LingkunganHeader;
use App\Models\DebuPersonalHeader;
use App\Models\WsValueLingkungan;
use App\Models\EmisiCerobongHeader;
use App\Models\DustFallHeader;
use App\Models\MicrobioHeader;
use App\Models\IsokinetikHeader;
use App\Models\SwabTestHeader;
use App\Models\Subkontrak;
// use App\Models\WsValueEmisiCerobong;
use App\Models\WsValueAir;
use App\Models\WsValueMicrobio;
use App\Models\WsValueSwab;
use App\Models\WsValueUdara;
use App\Models\WsValueEmisiCerobong;
use App\Models\DataLapanganDebuPersonal;
use App\Models\DataLapanganLingkunganHidup;
use App\Models\DataLapanganLingkunganKerja;
use App\Models\DataLapanganSenyawaVolatile;
use App\Models\DataLapanganEmisiCerobong;
use App\Models\DataLapanganIsokinetikHasil;
use App\Models\DataLapanganSwab;
use App\Models\DetailLingkunganHidup;
use App\Models\DetailLingkunganKerja;
use App\Models\DetailSenyawaVolatile;
use App\Models\DetailMicrobiologi;
use App\Models\AnalisParameter;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\Hash;
use App\Services\FunctionValue;
use App\Services\AnalystRender;
use App\Services\AnalystFormula;
use App\Services\AutomatedFormula;
use App\Models\AnalystFormula as Formula;
use App\Models\KuotaAnalisaParameter;
use Illuminate\Support\Facades\Exception;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Mpdf;
use Repository;

class LabelParameterController extends Controller
{
    public function getCategories()
    {
        return response()->json([
            'data' => MasterKategori::where('is_active', true)->get(['id', 'nama_kategori']),
            'message' => 'Categories retrieved successfully'
        ], 200);
    }

    public function getTemplates(Request $request)
    {
        return response()->json([
            'data' => TemplateStp::where(['category_id' => $request->selectedCategory, 'is_active' => true])->get(['id', 'name', 'param']),
            'message' => 'Templates retrieved successfully'
        ], 200);
    }

    public function getParameterDetail(Request $request)
    {
        $parameterDetail = OrderDetail::with('TrackingSatu')
            ->whereHas('TrackingSatu', fn($q) => $q->whereDate('ftc_laboratory', $request->selectedDate))
            ->where([
                'kategori_2' => $request->selectedCategory . "-" . MasterKategori::find($request->selectedCategory)->nama_kategori,
                'is_active' => true
            ])
            ->whereJsonContains('parameter', Parameter::where(['nama_lab' => $request->selectedParameter, 'id_kategori' => $request->selectedCategory, 'is_active' => true])->first()->id . ";" . $request->selectedParameter)
            ->get();

        return response()->json([
            'data' => $parameterDetail,
            'message' => 'Parameters retrieved successfully'
        ], 200);
    }

    public function generatePdf(Request $request)
    {
        $parameterDetail = OrderDetail::with('TrackingSatu')
            ->whereHas('TrackingSatu', fn($q) => $q->whereDate('ftc_laboratory', $request->selectedDate))
            ->where([
                'kategori_2' => $request->selectedCategory . "-" . MasterKategori::find($request->selectedCategory)->nama_kategori,
                'is_active' => true
            ])
            ->whereJsonContains('parameter', Parameter::where(['nama_lab' => $request->selectedParameter, 'id_kategori' => $request->selectedCategory, 'is_active' => true])->first()->id . ";" . $request->selectedParameter)
            ->get();

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => [50, 15],
            'margin_left' => 1,
            'margin_right' => 1,
            'margin_top' => 0.5,
            'margin_header' => 0,
            'margin_bottom' => 0,
            'margin_footer' => 0,
        ]);

        $mpdf->WriteHTML(view('pdf.label_parameter', ['data' => $parameterDetail, 'selectedDate' => $request->selectedDate, 'selectedParameter' => $request->selectedParameter])->render());

        $filename = 'Label_Parameter_' . urlencode($request->selectedParameter) . '_' . $request->selectedDate . '.pdf';
        $path = public_path('label_parameter');

        if (!file_exists($path)) mkdir($path, 0777, true);

        $mpdf->Output($path . '/' . $filename, \Mpdf\Output\Destination::FILE);

        return response()->json(['data' => $filename, 'message' => 'PDF generated successfully'], 200);
    }
}
