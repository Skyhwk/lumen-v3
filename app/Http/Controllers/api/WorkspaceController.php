<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\AnalystWorksheetDetail;
use App\Models\AnalystWorksheetHeader;
use App\Models\PencahayaanHeader;
use App\Models\GetaranHeader;
use App\Models\Subkontrak;
use App\Models\KebisinganHeader;
use App\Models\SwabTestHeader;
use App\Models\MicrobioHeader;
use App\Models\DataLapanganPartikulatMeter;
use App\Models\LingkunganHeader;
use App\Models\Gravimetri;
use App\Models\Titrimetri;
use App\Models\Colorimetri;
use App\Models\WsValueEmisiCerobong;
use App\Models\MasterKategori;
use App\Models\Parameter;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class WorkspaceController extends Controller
{
    public function index(Request $request)
    {
        $data = AnalystWorksheetHeader::query();

        return Datatables::of($data)
            ->addIndexColumn()
            ->make(true);
    }

    public function detail(Request $request)
    {
        try {
            $id = $request->input('id');
            $header = AnalystWorksheetHeader::with('details')->find($id);

            if (!$header) {
                return response()->json(['status' => 'error', 'message' => 'Workspace tidak ditemukan'], 404);
            }

            $samples = $header->details->pluck('no_sampel')->toArray();
            $waktuInputMap = [];

            if (!empty($samples)) {
                if ($header->id_kategori == 4) { // Udara
                    $queries = [
                        PencahayaanHeader::whereIn('no_sampel', $samples)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        GetaranHeader::whereIn('no_sampel', $samples)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        Subkontrak::whereIn('no_sampel', $samples)->where('is_approve', true)->where('is_active', true)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        KebisinganHeader::whereIn('no_sampel', $samples)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        SwabTestHeader::whereIn('no_sampel', $samples)->where('is_active', true)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        MicrobioHeader::whereIn('no_sampel', $samples)->where('parameter', 'like', "%Swab%")->where('is_active', true)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        DataLapanganPartikulatMeter::whereIn('no_sampel', $samples)->select('no_sampel', 'created_at', 'updated_at')->get(),
                        LingkunganHeader::whereIn('no_sampel', $samples)->where('is_active', true)->select('no_sampel', 'created_at', 'updated_at')->get()
                    ];
                    foreach ($queries as $results) {
                        foreach ($results as $r) {
                            if (!isset($waktuInputMap[$r->no_sampel])) {
                                $waktuInputMap[$r->no_sampel] = $r->created_at ?? $r->updated_at;
                            }
                        }
                    }
                } else if ($header->id_kategori == 1) { // Air
                    $queries = [
                        Gravimetri::whereIn('no_sampel', $samples)->where('is_active', true)->where('is_approved', true)->select('no_sampel', 'created_at')->get(),
                        Titrimetri::whereIn('no_sampel', $samples)->where('is_active', true)->where('is_approved', true)->select('no_sampel', 'created_at')->get(),
                        Colorimetri::whereIn('no_sampel', $samples)->where('is_active', true)->where('is_approved', true)->select('no_sampel', 'created_at')->get(),
                        Subkontrak::whereIn('no_sampel', $samples)->where('is_approve', true)->where('is_active', true)->select('no_sampel', 'created_at')->get()
                    ];
                    foreach ($queries as $results) {
                        foreach ($results as $r) {
                            if (!isset($waktuInputMap[$r->no_sampel])) {
                                $waktuInputMap[$r->no_sampel] = $r->created_at;
                            }
                        }
                    }
                } else if ($header->id_kategori == 5) { // Emisi
                    $results = WsValueEmisiCerobong::with(['emisi_cerobong_header', 'emisi_isokinetik', 'subkontrak'])
                        ->whereIn('no_sampel', $samples)->where('is_active', true)->get();
                    foreach ($results as $emisi) {
                        if (!isset($waktuInputMap[$emisi->no_sampel])) {
                            if ($emisi->emisi_cerobong_header) $waktuInputMap[$emisi->no_sampel] = $emisi->emisi_cerobong_header->created_at;
                            else if ($emisi->emisi_isokinetik) $waktuInputMap[$emisi->no_sampel] = $emisi->emisi_isokinetik->created_at;
                            else if ($emisi->subkontrak && $emisi->subkontrak->count() > 0) $waktuInputMap[$emisi->no_sampel] = $emisi->subkontrak->first()->created_at;
                        }
                    }
                } else if ($header->id_kategori == 7) { // Swab Test
                    $results = SwabTestHeader::whereIn('no_sampel', $samples)->where('is_active', true)->select('no_sampel', 'created_at')->get();
                    foreach ($results as $r) {
                        if (!isset($waktuInputMap[$r->no_sampel])) {
                            $waktuInputMap[$r->no_sampel] = $r->created_at;
                        }
                    }
                }
            }

            foreach ($header->details as $detail) {
                $detail->waktu_prepare = $detail->created_at;
                $detail->waktu_input_hasil = $waktuInputMap[$detail->no_sampel] ?? null;
            }

            return response()->json([
                'status' => 'success',
                'data' => $header
            ]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}