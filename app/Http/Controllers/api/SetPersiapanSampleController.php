<?php

namespace App\Http\Controllers\api;

use Illuminate\Http\Request;
use App\Models\MasterKaryawan;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\PersiapanSampelHeader;
use App\Models\PersiapanSampelDetail;
use App\Models\OrderHeader;
use App\Models\OrderDetail;
use Yajra\DataTables\Facades\DataTables;

class SetPersiapanSampleController extends Controller
{
    private function getDocumentNumber()
    {
        $latestPSH = PersiapanSampelHeader::orderBy('id', 'desc')->latest()->first();

        return 'ISL/PS/' . date('y') . '-' . $this->getRomanMonth(date('m')) . '/' . sprintf('%04d', $latestPSH ? $latestPSH->id + 1 : 1);
    }

    private function getRomanMonth($month)
    {
        return ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X', 'XI', 'XII'][$month - 1];
    }

    public function save(Request $request)
    {
        DB::beginTransaction();
        try {
            $existingPsh = PersiapanSampelHeader::where([ // cek dulu
                'no_quotation' => $request->no_quotation,
                'no_order' => $request->no_order,
                'tanggal_sampling' => $request->tanggal_sampling,
            ])->first();

            if ($existingPsh) { // klo ada update yg lama
                $psh = $existingPsh;
                $psh->updated_by = $this->karyawan;
                $psh->updated_at = time();

                PersiapanSampelDetail::where('id_persiapan_sampel_header', $psh->id)->delete(); // hapus semua psd lama
            } else {
                $psh = new PersiapanSampelHeader(); // klo gda buat baru

                $psh->no_document = $this->getDocumentNumber($request->no_order, $request->periode);
                $psh->created_by = $this->karyawan;
                $psh->created_at = time();
                $psh->updated_by = $this->karyawan;
                $psh->updated_at = time();
            }

            $psh->no_order = $request->no_order;
            $psh->no_quotation = $request->no_quotation;
            $psh->tanggal_sampling = $request->tanggal_sampling;
            $psh->nama_perusahaan = $request->nama_perusahaan;
            if ($request->periode) $psh->periode = $request->periode;
            if ($request->plastik_benthos) $psh->plastik_benthos = json_encode($request->plastik_benthos);
            if ($request->media_petri_dish) $psh->media_petri_dish = json_encode($request->media_petri_dish);
            if ($request->media_tabung) $psh->media_tabung = json_encode($request->media_tabung);
            if ($request->masker) $psh->masker = json_encode($request->masker);
            if ($request->sarung_tangan_karet) $psh->sarung_tangan_karet = json_encode($request->sarung_tangan_karet);
            if ($request->sarung_tangan_bintik) $psh->sarung_tangan_bintik = json_encode($request->sarung_tangan_bintik);
            $psh->analis_berangkat = $request->analis_berangkat;
            $psh->sampler_berangkat = $request->sampler_berangkat;
            $psh->analis_pulang = $request->analis_pulang;
            $psh->sampler_pulang = $request->sampler_pulang;

            $psh->save();

            // handle save psd
            foreach ($request->detail as $no_sampel => $parameters) {
                $psd = new PersiapanSampelDetail();

                $psd->no_sampel = $no_sampel;
                $psd->id_persiapan_sampel_header = $psh->id;
                $psd->parameters = json_encode($parameters);
                $psd->created_by = $this->karyawan;
                $psd->created_at = time();
                $psd->updated_by = $this->karyawan;
                $psd->updated_at = time();

                $psd->save();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }

        return response()->json(['success' => 'Saved Successfully.'], 200);
    }

    public function getAnalis()
    {
        return response()->json(MasterKaryawan::whereIn('id_jabatan', [60, 61, 62, 63])->orderBy('nama_lengkap')->get(), 200);
    }

    public function getUpdated(Request $request)
    {
        $psh = PersiapanSampelHeader::with('psDetail')->where([
            'no_quotation' => $request->no_quotation,
            'no_order' => $request->no_order,
        ])->first();

        if (!$psh) return response()->json(['message' => 'Updated PS Not Found'], 500);

        return response()->json($psh, 200);
    }

    public function indexV(Request $request)
    {
        try {
            $data = OrderDetail::with(['orderHeader.sampling.jadwal'])
                ->select([
                    'id_order_header',
                    'no_order',
                    'periode',
                    'is_active',
                    DB::raw('GROUP_CONCAT(DISTINCT no_sampel SEPARATOR ",") AS no_sampel'),
                    // DB::raw('GROUP_CONCAT(DISTINCT kategori_3 SEPARATOR ",") AS kategori_3'),
                    DB::raw('GROUP_CONCAT(DISTINCT tanggal_sampling SEPARATOR ",") AS tanggal_sampling'),
                    // DB::raw('GROUP_CONCAT(DISTINCT parameter SEPARATOR ",") AS parameter'),
                    // DB::raw('GROUP_CONCAT(DISTINCT persiapan SEPARATOR ",") AS persiapan'),
                ])
                ->where('is_active', true)
                ->whereYear('tanggal_sampling', date('Y', strtotime($request->periode_awal)))
                ->whereMonth('tanggal_sampling', date('m', strtotime($request->periode_awal)))
                ->orderBy('id_order_header', 'desc')
                ->groupBy(['id_order_header', 'no_order', 'periode', 'is_active']);

            return DataTables::of($data)
                ->filterColumn('order_header.no_document', function ($query, $keyword) {
                    $query->whereHas('orderHeader', function ($q) use ($keyword) {
                        $q->where('no_document', 'like', "%{$keyword}%");
                    });
                })->filterColumn('order_header.nama_perusahaan', function ($query, $keyword) {
                    $query->whereHas('orderHeader', function ($q) use ($keyword) {
                        $q->where('nama_perusahaan', 'like', "%{$keyword}%");
                    });
                })->make(true);
        } catch (\Exception $ex) {
            return response()->json([
                'message' => $ex->getMessage(),
                'line' => $ex->getLine(),
            ], 500);
        }
    }
}
