<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\SamplingPlan;
use App\Jobs\RenderSamplingPlan;
use App\Models\MasterKaryawan;
use App\Models\MasterDriver;
use App\Models\PraNoSample;
use App\Models\MasterCabang;
use App\Services\JadwalServices;
use App\Http\Controllers\Controller;
use App\Models\QuotationKontrakD;
use App\Models\QuotationKontrakH;
use App\Models\QuotationNonKontrak;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use Yajra\Datatables\Datatables;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\RenderSamplingPlan as RenderSamplingPlanService;


class RequestSamplingPlanRevisiController extends Controller
{
    public function index(Request $request)
    {
        $data = SamplingPlan::withTypeModelSub()
            ->with([
                'jadwal' => function ($query) {
                    $query->select('id_sampling', DB::raw('JSON_ARRAYAGG(JSON_OBJECT("tanggal", tanggal)) AS tanggal_sp'), 'kategori')
                        ->groupBy('id_sampling', 'kategori');
                }
            ])
            ->where('is_active', true)
            ->where('status', 0)
            ->where('is_approved', 0)
            ->where('no_document', 'REGEXP', 'R[0-9]+$')
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
            ->filterColumn('opsi_1', function ($query, $keyword) {
                $query->where('opsi_1', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('opsi_2', function ($query, $keyword) {
                $query->where('opsi_2', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('opsi_3', function ($query, $keyword) {
                $query->where('opsi_3', 'like', '%' . $keyword . '%');
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

    // public function getPraNomorSample(Request $request)
    // {
    //     $sp = SamplingPlan::where(['no_quotation' => $request->no_quotation, 'periode_kontrak' => $request->periode_kontrak ?? null])->first();
    //     $sp->pra_no_sample = PraNoSample::where(['no_quotation' => $request->no_quotation, 'periode' => $request->periode_kontrak ?? null])->first();

    //     return response()->json($sp, 200);
    // }

    public function getSampler()
    {
        $privateUserIds =[21, 35, 39, 56, 95, 112, 171, 377, 311, 377, 531, 779,346,96];
        $samplers = MasterKaryawan::with('jabatan')
            ->whereIn('id_jabatan', [94]) // 'Sampler', 'K3 Staff'
            ->whereNotIn('user_id', $privateUserIds)
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
        $privateSampler =  MasterKaryawan::with('jabatan')
            ->whereIn('user_id', $privateUserIds)
            ->where('is_active', true)
            ->orderBy('nama_lengkap')
            ->get();
        $privateSampler->transform(function ($item) {
            $item->nama_display = $item->nama_lengkap . ' (perbantuan)';
            return $item;
        });
        $samplers->transform(function ($item) {
            $item->nama_display = $item->nama_lengkap;
            return $item;
        });
        $allSamplers = $samplers->merge($privateSampler);
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
    }

    public function addJadwal(Request $request)
    {
        // dd($request->all());
        try {

            //code...
            $ObjectData = (object) [
                "no_quotation" => $request->no_quotation,
                "no_document" => $request->no_document,
                "quotation_id" => $request->quotation_id,
                "id_sampling" => $request->id,
                "id_cabang" => $request->id_cabang,
                "nama_perusahaan" => $request->nama_perusahaan,
                "alamat" => $request->alamat,
                "tanggal" => $request->tanggal,
                "kategori" => $request->kategori,
                "jam_mulai" => $request->jam_mulai,
                "jam_selesai" => $request->jam_selesai,
                "note" => $request->note,
                "warna" => $request->warna,
                "sampler" => $request->sampler,
                "durasi" => $request->durasi,
                "status" => $request->status,
                "kendaraan" => $request->kendaraan,
                "periode" => $request->periode ?? null,
                "karyawan" => $this->karyawan,
                'driver' => $request->driver ?? null,
                "isokinetic" => $request->isokinetic,
                "pendampingan_k3" => $request->pendampingan_k3
            ];
            $addJadwal = JadwalServices::on('addJadwal', $ObjectData)->addJadwalSP();

            $this->updateOrderDetail($ObjectData, $request->tanggal);
            
            if ($addJadwal) {
                $type = explode("/", $request->no_quotation)[1];
                if ($type == 'QTC') {
                    $job = new RenderSamplingPlan($request->quotation_id, 'kontrak');
                } else if ($type == 'QT') {
                    $job = new RenderSamplingPlan($request->quotation_id, 'non_kontrak');
                }
                $this->dispatch($job);
                return response()->json(['message' => 'Berhasil menambah jadwal sampling.!', 'status' => 'success'], 200);
            }
        } catch (\Throwable $th) {
            return response()->json($th);
        }
    }

    public function renderPDF(Request $request)
    {
        if ($request->data['status_quotation'] == 'kontrak') {
            $filename = RenderSamplingPlanService::onKontrak($request->data['quotation_id'])->onPeriode($request->data['periode_kontrak'])->renderPartialKontrak();
        } else {
            $filename = RenderSamplingPlanService::onNonKontrak($request->data['quotation_id'])->save();
        }

        return response()->json($filename, 200);
    }
    public function showDriver()
    {
        $data = MasterDriver::where('is_active', true);
        return Datatables::of($data)->make(true);
    }

    public function chekOrder(Request $request)
    {
        $chekNoQty = OrderHeader::where('no_document', $request->no_quotation)->where('is_revisi',0)->first();

        return response()->json([
            "data" => $chekNoQty ? true : false
        ], 200);
    }

    public function getStatusSampling(Request $request)
    {
        try {
            $getLabelStatusSampling =QuotationKontrakD::where('id_request_quotation_kontrak_h',$request->id_request_quotation_kontrak_h)
            ->where('periode_kontrak',$request->periode_kontrak)->first(['status_sampling']);
            
            return response()->json(['data'=>$getLabelStatusSampling],200);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(["message"=>$th->getMessage(),"line"=>$getLine(),"file" =>$th->getFile()],400);
        }
    }

    private function updateOrderDetail($data, $tanggal)
    {
        $cekOrder = OrderHeader::where('no_document', $data->no_quotation)->where('is_active', true)->first();
        if ($cekOrder) {
            $array_no_samples = [];
            foreach ($data->kategori as $x => $y) {
                $pra_no_sample = explode(" - ", $y)[1];
                $no_samples = $cekOrder->no_order . '/' . $pra_no_sample;
                $array_no_samples[] = $no_samples;
            }

            $orderDetail = OrderDetail::where('id_order_header', $cekOrder->id)->whereIn('no_sampel', $array_no_samples)->get();
            foreach ($orderDetail as $od) {
                $od->tanggal_sampling = $tanggal;
                $od->save();

                Log::channel('perubahan_tanggal')->info('Order Detail updated: ' . $od->no_sampel . ' -> ' . $tanggal);
            }
        }
    }
}
