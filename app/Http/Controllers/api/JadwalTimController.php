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
class JadwalTimController extends Controller
{
    public function index(Request $request)
    {
        $data = Jadwal::with([
            'jadwalMobil' => function ($q) use ($request) {
                $q->where('is_active', true)
                    ->where(
                        'tanggal_berangkat',
                        $request->tanggal
                    );
            },

            'quotationKontrakH' => function ($q) {
                $q->select(
                    'id',
                    'no_document',
                    'nama_pic_sampling',
                    'no_tlp_pic_sampling'
                )->where(
                    'is_active',
                    true
                );
            },

            'quotationNonKontrak' => function ($q) {
                $q->select(
                    'id',
                    'no_document',
                    'nama_pic_sampling',
                    'no_tlp_pic_sampling'
                )->where(
                    'is_active',
                    true
                );
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
                'note',
                'wilayah',
                DB::raw(
                    'GROUP_CONCAT(sampler) as sampler'
                )
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
                'id_cabang',
                'note'
            )
            ->whereNotNull(
                'no_quotation'
            )
            ->where(
                'is_active',
                true
            )
            ->where(
                'tanggal',
                $request->tanggal
            )
            ->orderBy('jam_mulai', 'asc')
            ->get()

            // mapping PIC
            ->map(function ($item) {

                $quotation =
                    strpos(
                        $item->no_quotation,
                        'QTC'
                    ) !== false
                        ? $item->quotationKontrakH
                        : $item->quotationNonKontrak;

                $item->pic = $quotation
                    ? [
                        'nama_pic_sampling'
                        => $quotation->nama_pic_sampling,

                        'no_tlp_pic_sampling'
                        => $quotation->no_tlp_pic_sampling,
                    ]
                    : null;

                unset(
                    $item->quotationKontrakH,
                    $item->quotationNonKontrak
                );

                // buat tim sampler yang konsisten
                $teamSamplers = collect(
                    explode(',', $item->sampler)
                )
                    ->map(fn($s) => trim($s))
                    ->filter();

                // tambahkan driver jika belum ada
                if (
                    !empty($item->driver)
                    && !$teamSamplers->contains(
                        $item->driver
                    )
                ) {
                    $teamSamplers->push(
                        $item->driver
                    );
                }

                // sort biar konsisten
                $normalizedTeam =$teamSamplers
                    ->sort()
                    ->values()
                    ->implode(', ');

                $displaySampler = $teamSamplers->map(function ($sampler)use ($item) {
                    return $sampler ==$item->driver ? $sampler .' (Driver)': $sampler;
                })->implode(', ');

                $item->team_sampler = $normalizedTeam;
                $item->display_sampler = $displaySampler;
                return $item;
            })

            // GROUP BY TIM
            ->groupBy(
                'team_sampler'
            )

            ->map(function ($group) {
                $first = $group->first();
                return [
                    'tim_sampler' => $first->display_sampler,
                    'list_pt' =>
                        $group->map( function ($item) {
                            return [
                                'no_quotation'=> $item->no_quotation,
                                'nama_perusahaan'=> $item->nama_perusahaan,
                                'wilayah'=> $item->wilayah,
                                'sampler'=> $item->display_sampler,
                                'jam_mulai'=> $item->jam_mulai,
                                'jam_selesai'=> $item->jam_selesai,
                                'durasi'=> $item->durasi,
                                'periode'=> $item->periode,
                                'pic'=> $item->pic,
                                'note' => $item->note,
                            ];
                        }
                    )->values(),
                ];
            })

            ->values();

        return Datatables::of($data)
            ->make(true);
    }

}