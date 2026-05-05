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
            'jadwalMobil' => function ($q) use ($request) {
                $q->where('is_active', true)
                  ->where('tanggal_berangkat', $request->tanggal);
            },
            'quotationKontrakH' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                  ->where('is_active', true);
            },
            'quotationNonKontrak' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling')
                  ->where('is_active', true);
            }
        ])
        ->select(
            'parsial',
            'no_quotation',
            'nama_perusahaan',
            'periode',
            'jam_mulai',
            'jam_selesai',
            'driver',
            'durasi',
            'id_cabang',
            'wilayah',
            DB::raw('group_concat(sampler) as sampler'),
            DB::raw('MAX(kendaraan) as kendaraan')
        )
        ->groupBy(
            'parsial', 'no_quotation', 'periode', 'nama_perusahaan',
            'durasi','driver','jam_mulai', 'jam_selesai', 'wilayah', 'id_cabang'
        )
        ->whereNotNull('no_quotation')
        ->where('is_active', true)
        ->where('tanggal', $request->tanggal)
        ->orderByRaw('MAX(kendaraan) ASC')
        ->orderBy('jam_mulai')
        ->get()
        ->map(function ($item) {
            $quotation = strpos($item->no_quotation, 'QTC') !== false
                ? $item->quotationKontrakH
                : $item->quotationNonKontrak;

            $item->pic = $quotation ? [
                'nama_pic_sampling'   => $quotation->nama_pic_sampling,
                'no_tlp_pic_sampling' => $quotation->no_tlp_pic_sampling,
            ] : null;

            unset($item->quotationKontrakH, $item->quotationNonKontrak);

            $jadwalMobil = $item->jadwalMobil;
            $item->jadwal_mobil = $jadwalMobil ? [
                'jam_berangkat'     => $jadwalMobil->jam_berangkat,
                'tanggal_berangkat' => $jadwalMobil->tanggal_berangkat,
                'keterangan'        => $jadwalMobil->keterangan,
            ] : null;

            unset($item->jadwalMobil);

            return $item;
        })
        ->groupBy('kendaraan')
        ->map(function ($group) {
            $first = $group->first();
             
            return [
                'kendaraan'    => $first->kendaraan,
                'jadwal_mobil' => $first->jadwal_mobil,
                'list_pt'      => $group->map(function ($item) {
                    $arraySamplers = collect(explode(',', $item->sampler))->map(function ($sampler) use ($item){
                        return ($sampler == $item->driver) ? $sampler . ' (Driver)' : $sampler;
                    });
                    
                    return [
                        'no_quotation'    => $item->no_quotation,
                        'nama_perusahaan' => $item->nama_perusahaan,
                        'wilayah'         => $item->wilayah,
                        'sampler'         => $arraySamplers->implode(', '),
                        'jam_mulai'       => $item->jam_mulai,
                        'jam_selesai'     => $item->jam_selesai,
                        'durasi'          => $item->durasi,
                        'periode'         => $item->periode,
                        'pic'             => $item->pic
                    ];
                })->values(),
            ];
        })
        ->values();

        return Datatables::of($data)->make(true);
    }


    // public function index(Request $request)
    // {
    //     $data = Jadwal::with([
    //         'jadwalMobil' => function ($q) use ($request) {
    //             $q->where('is_active', true)
    //             ->where('tanggal_berangkat', $request->tanggal);
    //         },

    //         'quotationKontrakH' => function ($q) {
    //             $q->select(
    //                 'id',
    //                 'no_document',
    //                 'nama_pic_sampling',
    //                 'no_tlp_pic_sampling',
    //             )->where('is_active', true);
    //         },

    //         'quotationNonKontrak' => function ($q) {
    //             $q->select(
    //                 'id',
    //                 'no_document',
    //                 'nama_pic_sampling',
    //                 'no_tlp_pic_sampling',
    //             )->where('is_active', true);
    //         }
    //     ])
    //     ->select(
    //         'parsial',
    //         'no_quotation',
    //         'nama_perusahaan',
    //         'periode',
    //         'jam_mulai',
    //         'jam_selesai',
    //         'durasi',
    //         'driver',
    //         'id_cabang',
    //         'wilayah',
    //         DB::raw('group_concat(sampler) as sampler'),
    //         DB::raw('MAX(kendaraan) as kendaraan')
    //     )
    //     ->groupBy(
    //         'parsial',
    //         'no_quotation',
    //         'periode',
    //         'nama_perusahaan',
    //         'durasi',
    //         'driver',
    //         'jam_mulai',
    //         'jam_selesai',
    //         'wilayah',
    //         'id_cabang'
    //     )
    //     ->whereNotNull('no_quotation')
    //     ->where('is_active', true)
    //     ->where('tanggal', $request->tanggal)
    //     ->get()
    //     ->map(function ($item) {

    //         $quotation = str_contains($item->no_quotation, 'QTC')
    //             ? $item->quotationKontrakH
    //             : $item->quotationNonKontrak;

    //         $item->pic = $quotation ? [
    //             'nama_pic_sampling' => $quotation->nama_pic_sampling,
    //             'no_tlp_pic_sampling' => $quotation->no_tlp_pic_sampling,
    //         ] : null;

    //         unset($item->quotationKontrakH, $item->quotationNonKontrak);

    //         return $item;
    //     })->map(function ($item) {
    //         $jadwalMobil = $item->jadwalMobil;
    //         $item->jadwal_mobil = $jadwalMobil ? [
    //             'jam_berangkat' => $jadwalMobil->jam_berangkat,
    //             'tanggal_berangkat' => $jadwalMobil->tanggal_berangkat,
    //             'keterangan' => $jadwalMobil->keterangan,
    //         ] : null;

    //         unset($item->jadwalMobil);

    //         return $item;
    //     });

    //     return Datatables::of($data)->make(true);
    // }

}