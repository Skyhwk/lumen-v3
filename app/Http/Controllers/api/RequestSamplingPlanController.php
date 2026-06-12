<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Models\PraNoSample;
use App\Models\MasterKaryawan;
use App\Jobs\RenderSamplingPlan;
use App\Services\JadwalServices;
use App\Models\MasterDriver;
use App\Models\MasterCabang;
use App\Models\QuotationNonKontrak;

use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\PerbantuanSampler;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;
use Illuminate\Support\Facades\Log;

class RequestSamplingPlanController extends Controller
{
    public function index(Request $request)
    {
        $data = SamplingPlan::withTypeModelSub()
            ->with([
                'jadwalSP' => function ($query) {
                    $query->select(
                        'id_sampling',
                        'no_quotation',
                        DB::raw('JSON_ARRAYAGG(JSON_OBJECT("tanggal", tanggal)) AS tanggal_sp'),
                        'kategori',
                        'updated_by',
                        'updated_at',
                        'created_by',
                        'created_at'
                    )
                        ->groupBy('id_sampling', 'no_quotation', 'kategori', 'updated_by', 'updated_at', 'created_by', 'created_at')
                        ->orderByRaw('COALESCE(updated_at, created_at) DESC');
                }
            ])
            ->where('is_active', true)
            ->where('status', 0)
            ->where('is_approved', 0)
            ->whereRaw("no_document NOT REGEXP 'R[0-9]+$'")
            ->orderBy('id', 'DESC');

        return Datatables::of($data)
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_document', function ($query, $keyword) {
                $query->where('no_document', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('no_quotation', function ($query, $keyword) {
                $query->where('no_quotation', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('nama_perusahaan', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('quotation', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
                        $sub->where('nama_perusahaan', 'like', "%{$keyword}%");
                    });
                });
            })
            ->filterColumn('wilayah', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->whereHas('quotation', function ($sub) use ($keyword) {
                        $sub->where('wilayah', 'like', "%{$keyword}%");
                    })->orWhereHas('quotationKontrak', function ($sub) use ($keyword) {
                        $sub->where('wilayah', 'like', "%{$keyword}%");
                    });
                });
            })
            ->filterColumn('opsi_1', function ($query, $keyword) {
                $query->where('opsi_1', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('opsi_2', function ($query, $keyword) {
                $query->where('opsi_2', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('periode_kontrak', function ($query, $keyword) {
                $query->where('periode_kontrak', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('is_sabtu', function ($query, $keyword) {
                $query->where('is_sabtu', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('is_minggu', function ($query, $keyword) {
                $query->where('is_minggu', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('is_malam', function ($query, $keyword) {
                $query->where('is_malam', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('created_by', function ($query, $keyword) {
                $query->where('created_by', 'like', '%' . $keyword . '%');
            })
            ->make(true);
    }

    public function getPraNomorSample(Request $request)
    {
        $isContract = $request->periode_kontrak !== null;
        $categories = [];
        if (!$isContract) {
            $qt = QuotationNonKontrak::where('no_document', $request->no_quotation)->first();
            $dataPendukungSampling = json_decode($qt->data_pendukung_sampling);
            foreach ($dataPendukungSampling as $dps) {
                $kategori = explode("-", $dps->kategori_2)[1];
                foreach ($dps->penamaan_titik as $penamaanTitik) {
                    $props = get_object_vars($penamaanTitik);
                    $noSampel = key($props);

                    array_push($categories, "$kategori - $noSampel");
                }
            };
        } else {
            $qtH = QuotationKontrakH::where('no_document', $request->no_quotation)->first();
            $qtD = QuotationKontrakD::where('id_request_quotation_kontrak_h', $qtH->id)->where('periode_kontrak', $request->periode_kontrak)->first();

            $dataPendukungSampling = json_decode($qtD->data_pendukung_sampling);
            foreach ($dataPendukungSampling as $dps) {
                foreach ($dps->data_sampling as $ds) {
                    $kategori = explode("-", $ds->kategori_2)[1];
                    foreach ($ds->penamaan_titik as $penamaanTitik) {
                        $props = get_object_vars($penamaanTitik);
                        $noSampel = key($props);

                        array_push($categories, "$kategori - $noSampel");
                    }
                }
            };
        }

        $categories = str_replace('\\', '', json_encode($categories));

        $sp['pra_no_sample'] = ['kategori' => $categories];

        return response()->json($sp, 200);
    }

    public function getSampler()
    {
        
        $samplers = MasterKaryawan::with('jabatan')
            ->whereIn('id_jabatan', [94]) // 'Sampler', 'K3 Staff'
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
        $privateSampler =  PerbantuanSampler::with('users.jabatan')
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
        
        $privateSampler->transform(function ($item) {
            $digitCount = strlen((string) $item->user_id);

            // 2. Tentukan suffix (akhiran nama)
            if ($digitCount > 4) {
                $item->nama_display = $item->nama_lengkap . ' (freelance)';
            } else {
                $item->nama_display = $item->nama_lengkap . ' (perbantuan)';
            }

            $item->id = $item->user_id;
            
            unset($item->jabatan);
            if ($item->users && $item->users->jabatan) {
                $jabatanObj = $item->users->getRelation('jabatan');
                $item->setRelation('jabatan', $jabatanObj);
            } else {
                // Fallback jika data kosong (opsional, biar frontend gak error undefined)
                $jabatanObj = (object) [
                    "nama_jabatan" => "Freelance Sampler",
                ];
                $item->jabatan = $jabatanObj;
            }
            unset($item->users);
            return $item;
        });
        $samplers->transform(function ($item) {
            $item->nama_display = $item->nama_lengkap;
            return $item;
        });
        $allSamplers = $samplers->concat($privateSampler);
        $allSamplers = $allSamplers->unique('id');
        $allSamplers = $allSamplers->sortBy('nama_display')->values();
        return response()->json($allSamplers, 200);
    }

    public function kantorCabang(Request $request)
    {
        $branch = MasterCabang::select('id', 'kode_cabang', 'nama_cabang')->get();
        return response()->json($branch, 200);
    }

    public function rejectJadwal(Request $request)
    {
        $ObjectData = (object) [
            "no_quotation" => $request->no_quotation,
            "id_sampling" => $request->id_sampling,
            "rejection_reason" => $request->rejection_reason,
            "karyawan" => $this->karyawan,
        ];
        try {
            //code...
            $tipe = explode('/', $request->no_quotation)[1];
            if ($tipe == 'QTC') {
                $jadwal = JadwalServices::on('rejectJadwalKontrak', $ObjectData)->rejectJadwalSPKontrak();
            } else if ($tipe == 'QT') {
                $jadwal = JadwalServices::on('rejectJadwalNon', $ObjectData)->rejectJadwalSP();
            }
    
            if ($jadwal) {
                return response()->json([
                    "message" => "Berhasil menolak jadwal",
                    "status" => "success"
                ], 200);
            }
        } catch (\Throwable $e) {
            $logData = [
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
                'status'  => $ex->getCode(),
                'status'  => '401',
            ];
            Log::channel('sampling')->error("=== rejectJadwal ===", $logData);
            return response()->json([
                "message" => $e->getMessage(),
                "status" => "error"
            ], $e->getCode() ?: 500);
        }
    }

    public function addJadwal(Request $request)
    {

        $ObjectData = (object) [
            "no_quotation" => $request->no_quotation,
            "no_document" => $request->no_document,
            "quotation_id" => $request->quotation_id,
            "id_sampling" => $request->id_sampling,
            "nama_perusahaan" => $request->nama_perusahaan,
            "alamat" => $request->alamat,
            "tanggal" => $request->tanggal,
            "id_cabang" => $request->id_cabang,
            "kategori" => $request->kategori,
            "jam_mulai" => $request->jam_mulai,
            "jam_selesai" => $request->jam_selesai,
            "note" => $request->note,
            "warna" => $request->warna,
            "sampler" => $request->sampler,
            "durasi" => $request->durasi,
            "status" => $request->status,
            "kendaraan" => $request->kendaraan,
            "periode" => $request->periode_kontrak != "" ? $request->periode_kontrak : null,
            "karyawan" => $this->karyawan,
            'driver' => $request->driver ?? null,
            "isokinetic" => (int)$request->isokinetic,
            "pendampingan_k3" => (int)$request->pendampingan_k3,
            "durasi_personal" => $request->durasi_personal,
        ];
        
        $addJadwal = JadwalServices::on('addJadwal', $ObjectData)->addJadwalSP();

        if ($addJadwal) {
            $type = explode("/", $request->no_quotation)[1];
            if ($type == 'QTC') {
                $job = new RenderSamplingPlan($request->quotation_id, 'kontrak');
            } else if ($type == 'QT') {
                $job = new RenderSamplingPlan($request->quotation_id, 'non_kontrak');
            }

            $this->dispatch($job);

            return response()->json(['message' => 'Berhasil menambah jadwal sampling.', 'status' => 'success'], 200);
        }
    }

    public function renderPDF(Request $request)
    {
        if ($request->data['status_quotation'] == 'kontrak') {
            $filename = RenderSamplingPlanService::onKontrak($request->data['quotation_id'])->onPeriode($request->data['periode_kontrak'])->renderPartialKontrak();
        } else {
            $filename = RenderSamplingPlanService::onNonKontrak($request->data['quotation_id'])->save();
        }

        // $sp = SamplingPlan::where('id', $request->data['id'])->first();
        return response()->json([
            'filename' => $filename,
            'status' => 'success'
        ], 200);
    }
    public function showDriver()
    {
        $data = MasterDriver::where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function getStatusSampling(Request $request)
    {
        
        try {
            $getLabelStatusSampling =QuotationKontrakD::where('id_request_quotation_kontrak_h',$request->id_request_quotation_kontrak_h)
            ->where('periode_kontrak',$request->periode_kontrak)->first(['status_sampling']);
            
            return response()->json(['data'=>$getLabelStatusSampling],200);
        } catch (\Throwable $th) {
            //throw $th;
            $logData = [
                'message' => $th->getMessage(),
                'line' => $th->getLine(),
                'status'  => $th->getCode(),
                'status'  => '401',
            ];
            Log::channel('sampling')->error("=== getStatusSampling ===", $logData);
            return response()->json(["message"=>$th->getMessage(),"line"=>$getLine(),"file" =>$th->getFile()],400);
        }
    }

    public function kendaraanAvalible(Request $request){
    
        $tanggal = $request->tanggal;
        // $jamMulai = $request->jam_mulai;
        // $jamSelesai = $request->jam_selesai;
        $idSamplingEdit = $request->id_sampling; 

        // --- 1. Ambil Semua Daftar Kendaraan dari API Eksternal ---
        $masterKendaraan = [];
        $url = 'https://apps.intilab.com/api/devices';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET"); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer RzBFAiAsSMLm1ORiB2hH9KQvaGjNSN-1jHrV7nK_WIf1cF4CnwIhAMjtQNnyRNrpy4NogP8qHJWdv_5KVyiWcTLVt7JKSsN2eyJ1IjozLCJlIjoiMjA2MC0wNy0wN1QxNzowMDowMC4wMDArMDA6MDAifQ', 
            'Accept: application/json',
            'Content-Type: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode == 200 && $response) {
            $resBody = json_decode($response, true);
            $masterKendaraan = array_column($resBody, 'name');
        } else {
            return response()->json([
                'message' => 'Gagal mengambil data kendaraan dari API.',
                'error' => $curlError
            ], 500);
        }
        
        // --- 2. Cari Semua Jadwal yang Bentrok ---
        $queryBentrok = DB::table('jadwal')
            ->select('kendaraan', 'sampler', 'jam_mulai', 'created_at', 'updated_at')
            ->where('tanggal', $tanggal)
            ->whereNotNull('kendaraan')
            ->where('is_active', 1);
            // ->where(function($query) use ($jamMulai, $jamSelesai) {
            //     // Rumus Overlap
            //     $query->where('jam_mulai', '<', $jamSelesai)
            //         ->where('jam_selesai', '>', $jamMulai);
            // });

        if ($idSamplingEdit) {
            $queryBentrok->where('id_sampling', '!=', $idSamplingEdit);
        }

        $dataBentrok = $queryBentrok->get();

        // --- 3. Filter Konflik Sesuai Aturan (Paling awal jamnya, lalu created/updated_at) ---
        $detailKonflik = [];

        foreach ($dataBentrok as $row) {
            $veh = $row->kendaraan;

            // Jika kendaraan ini belum masuk daftar konflik, masukkan.
            if (!isset($detailKonflik[$veh])) {
                $detailKonflik[$veh] = $row;
            } else {
                // Jika sudah ada, bandingkan jam_mulai-nya
                $existing = $detailKonflik[$veh];

                if ($row->jam_mulai < $existing->jam_mulai) {
                    // Jam lebih awal menang
                    $detailKonflik[$veh] = $row;
                } elseif ($row->jam_mulai == $existing->jam_mulai) {
                    // Jika jamnya sama, adu created_at atau updated_at
                    $waktuRow = $row->created_at ? strtotime($row->created_at) : strtotime($row->updated_at);
                    $waktuExisting = $existing->created_at ? strtotime($existing->created_at) : strtotime($existing->updated_at);

                    if ($waktuRow && $waktuExisting && $waktuRow < $waktuExisting) {
                        $detailKonflik[$veh] = $row;
                    }
                }
            }
        }

        // --- 4. Return Format JSON ---
        return response()->json([
            'status'    => true,
            // Karena kita mau nampilin semua mobil, 'data' isinya $masterKendaraan murni
            'data'      => array_map(function($name) { return ['id' => $name, 'text' => $name]; }, $masterKendaraan), 
            // Kirim detail konflik sebagai referensi Frontend
            'conflicts' => $detailKonflik 
        ]);
    }
}
