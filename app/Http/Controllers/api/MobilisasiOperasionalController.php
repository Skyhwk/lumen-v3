<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\JadwalMobil;
use App\Models\Jadwal;


use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
class MobilisasiOperasionalController extends Controller
{
    public function index(Request $request)
    {
        $data = Jadwal::with([
            'jadwalMobil' => function ($q) {
                $q->where('is_active', true);
            },

            'quotationKontrakH' => function ($q) {
                $q->select(
                    'id',
                    'no_document',
                    'nama_pic_order',
                    'nama_pic_sampling',
                    'no_tlp_pic_sampling',
                    'no_pic_order'
                )->where('is_active', true);
            },

            'quotationNonKontrak' => function ($q) {
                $q->select(
                    'id',
                    'no_document',
                    'nama_pic_order',
                    'nama_pic_sampling',
                    'no_tlp_pic_sampling',
                    'no_pic_order'
                )->where('is_active', true);
            }
        ])
        ->select(
            'parsial',
            'no_quotation',
            'nama_perusahaan',
            'periode',
            'jam_mulai',
            'jam_selesai',
            'durasi',
            'driver',
            'id_cabang',
            'wilayah',
            DB::raw('group_concat(sampler) as sampler'),
            DB::raw('MAX(kendaraan) as kendaraan')
        )
        ->groupBy(
            'parsial',
            'no_quotation',
            'periode',
            'nama_perusahaan',
            'durasi',
            'driver',
            'jam_mulai',
            'jam_selesai',
            'wilayah',
            'id_cabang'
        )
        ->whereNotNull('no_quotation')
        ->where('is_active', true)
        ->where('tanggal', $request->tanggal)
        ->get()
        ->map(function ($item) {

            $quotation = str_contains($item->no_quotation, 'QTC')
                ? $item->quotationKontrakH
                : $item->quotationNonKontrak;

            $item->pic = $quotation ? [
                'nama_pic_sampling' => $quotation->nama_pic_sampling,
                'no_tlp_pic_sampling' => $quotation->no_tlp_pic_sampling,
            ] : null;

            unset($item->quotationKontrakH, $item->quotationNonKontrak);

            return $item;
        });

        return response()->json($data);
    }
}