<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\AnalystWorksheetHeader;
use App\Models\AnalystWorksheetDetail;
use Carbon\Carbon;
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

class LembarKerjaController extends Controller
{
    /**
     * Get list of created Workspaces
     */
    public function getWorkspaces(Request $request)
    {
        try {
            $query = AnalystWorksheetHeader::with('details')
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->where('created_by', $this->karyawan);

            if ($request->has('tanggal') && !empty($request->input('tanggal'))) {
                $query->whereDate('created_at', $request->input('tanggal'));
            }

            $workspaces = $query->get();

            $udaraSamples = [];
            $airSamples = [];
            $emisiSamples = [];
            $swabSamples = [];

            foreach ($workspaces as $ws) {
                $samples = $ws->details->pluck('no_sampel')->toArray();
                if ($ws->id_kategori == 4) $udaraSamples = array_merge($udaraSamples, $samples);
                else if ($ws->id_kategori == 1) $airSamples = array_merge($airSamples, $samples);
                else if ($ws->id_kategori == 5) $emisiSamples = array_merge($emisiSamples, $samples);
                else if ($ws->id_kategori == 7) $swabSamples = array_merge($swabSamples, $samples);
            }

            // Udara Lookups
            $udaraCompleted = [];
            if (!empty($udaraSamples)) {
                $udaraCompleted = array_unique(array_merge(
                    PencahayaanHeader::whereIn('no_sampel', $udaraSamples)->pluck('no_sampel')->toArray(),
                    GetaranHeader::whereIn('no_sampel', $udaraSamples)->pluck('no_sampel')->toArray(),
                    Subkontrak::whereIn('no_sampel', $udaraSamples)->where('is_approve', true)->where('is_active', true)->pluck('no_sampel')->toArray(),
                    KebisinganHeader::whereIn('no_sampel', $udaraSamples)->pluck('no_sampel')->toArray(),
                    SwabTestHeader::whereIn('no_sampel', $udaraSamples)->where('is_active', true)->pluck('no_sampel')->toArray(),
                    MicrobioHeader::whereIn('no_sampel', $udaraSamples)->where('parameter', 'like', "%Swab%")->where('is_active', true)->pluck('no_sampel')->toArray(),
                    DataLapanganPartikulatMeter::whereIn('no_sampel', $udaraSamples)->pluck('no_sampel')->toArray(),
                    LingkunganHeader::whereIn('no_sampel', $udaraSamples)->where('is_active', true)->pluck('no_sampel')->toArray()
                ));
            }

            // Air Lookups
            $airCompleted = [];
            if (!empty($airSamples)) {
                $airCompleted = array_unique(array_merge(
                    Gravimetri::whereIn('no_sampel', $airSamples)->where('is_active', true)->where('is_approved', true)->pluck('no_sampel')->toArray(),
                    Titrimetri::whereIn('no_sampel', $airSamples)->where('is_active', true)->where('is_approved', true)->pluck('no_sampel')->toArray(),
                    Colorimetri::whereIn('no_sampel', $airSamples)->where('is_active', true)->where('is_approved', true)->pluck('no_sampel')->toArray(),
                    Subkontrak::whereIn('no_sampel', $airSamples)->where('is_approve', true)->where('is_active', true)->pluck('no_sampel')->toArray()
                ));
            }

            // Emisi Lookups
            $emisiCompleted = [];
            if (!empty($emisiSamples)) {
                $emisiCompleted = WsValueEmisiCerobong::whereIn('no_sampel', $emisiSamples)->where('is_active', true)
                    ->where(function($q) {
                        $q->has('emisi_cerobong_header')->orHas('emisi_isokinetik')->orHas('subkontrak');
                    })->pluck('no_sampel')->toArray();
            }

            // Swab Lookups
            $swabCompleted = [];
            if (!empty($swabSamples)) {
                $swabCompleted = SwabTestHeader::whereIn('no_sampel', $swabSamples)->where('is_active', true)->pluck('no_sampel')->toArray();
            }

            $workspaces = $workspaces->map(function($ws) use ($udaraCompleted, $airCompleted, $emisiCompleted, $swabCompleted) {
                $completed = 0;
                $total = count($ws->details);

                foreach ($ws->details as $detail) {
                    $isDone = false;
                    if ($ws->id_kategori == 4) { 
                        $isDone = in_array($detail->no_sampel, $udaraCompleted);
                    } else if ($ws->id_kategori == 1) { 
                        $isDone = in_array($detail->no_sampel, $airCompleted);
                    } else if ($ws->id_kategori == 5) { 
                        $isDone = in_array($detail->no_sampel, $emisiCompleted);
                    } else if ($ws->id_kategori == 7) { 
                        $isDone = in_array($detail->no_sampel, $swabCompleted);
                    }

                    if ($isDone) {
                        $completed++;
                    }
                }

                $ws->completed_samples = $completed;
                $ws->total_samples = $total;
                unset($ws->details); 

                if ($completed === $total && $total > 0 && !$ws->is_finished) {
                    DB::table('analyst_worksheet_headers')
                        ->where('id', $ws->id)
                        ->update(['is_finished' => true]);
                    $ws->is_finished = 1;
                }

                return $ws;
            });

            return response()->json([
                'status' => 'success',
                'data' => $workspaces
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Validate Sample against selected Parameter
     */
    public function validateSample(Request $request)
    {
        try {
            $no_sampel = $request->input('no_sampel');
            $parameter = $request->input('parameter'); 
            
            if (!$no_sampel || !$parameter) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'no sampel dan parameter harus diisi.'
                ], 422);
            }

            // parameter column format is ["485;E. coli", "567;TPC"]
            // Frontend may send full value "1934;BOD (B-23)" or just label "BOD (B-23)"
            // Use LIKE to match either format
            $orderDetail = OrderDetail::where('no_sampel', $no_sampel)
                ->where('is_active', true)
                ->where('parameter', 'LIKE', '%'.$parameter.'%')
                ->first();

            if ($orderDetail) {
                // Check if sample already exists in another active workspace
                $existingWorkspace = DB::table('analyst_worksheet_details as d')
                    ->join('analyst_worksheet_headers as h', 'h.id', '=', 'd.id_header')
                    ->where('d.no_sampel', $no_sampel)
                    ->where('h.parameter', $parameter)
                    ->where('d.is_active', true)
                    ->where('h.is_active', true)
                    ->select('h.nama_workspace')
                    ->first();

                if ($existingWorkspace) {
                    return response()->json([
                        'status' => 'error',
                        'message' => "No sampel sudah ada di workspace: {$existingWorkspace->nama_workspace}"
                    ]);
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Sample valid',
                    'data' => $orderDetail
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => "No sampel tidak mengandung parameter $parameter"
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save the Worksheet (Header and Details)
     */
    public function saveWorkspace(Request $request)
    {
        DB::beginTransaction();
        try {
            $user = $this->karyawan ?? 'System'; // Typically retrieved from auth
            $nama_workspace = $request->input('nama_workspace');
            $id_kategori = $request->input('id_kategori');
            $nama_parameter = $request->input('nama_parameter');
            $samples = $request->input('samples'); // array of no_sampel strings

            if (!$samples || !is_array($samples) || count($samples) == 0) {
                throw new \Exception("Daftar sampel kosong.");
            }

            // Auto-generate name using microtime to replace spaces and dots
            if (!$nama_workspace) {
                $nama_workspace = str_replace('.', '', (string) microtime(true));
            }

            // Create Header (parameter moved back to header)
            $headerId = DB::table('analyst_worksheet_headers')->insertGetId([
                'nama_workspace' => $nama_workspace,
                'id_kategori' => $id_kategori,
                'parameter' => $nama_parameter,
                'created_by' => $user,
                'created_at' => Carbon::now(),
                'is_active' => true,
                'is_finished' => false,
            ]);

            // Create Details (parameter removed from details)
            $details = [];
            foreach ($samples as $sampel) {
                $details[] = [
                    'id_header' => $headerId,
                    'no_sampel' => $sampel,
                    'created_by' => $user,
                    'created_at' => Carbon::now(),
                    'is_active' => true,
                ];
            }

            DB::table('analyst_worksheet_details')->insert($details);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Lembar Kerja berhasil disimpan.',
                'data' => [
                    'id_header' => $headerId,
                    'nama_workspace' => $nama_workspace
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan Lembar Kerja: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getWorkspaceDetail(Request $request)
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

    public function updateCatatan(Request $request)
    {
        try {
            $id_detail = $request->input('id_detail');
            $catatan = $request->input('catatan');

            DB::table('analyst_worksheet_details')
                ->where('id', $id_detail)
                ->update(['catatan' => $catatan]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
             return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
