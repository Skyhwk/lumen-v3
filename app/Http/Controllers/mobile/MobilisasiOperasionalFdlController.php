<?php

namespace App\Http\Controllers\mobile;

use App\Http\Controllers\Controller;
use App\Models\Jadwal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MobilisasiOperasionalFdlController extends Controller
{
    public function index(Request $request)
    {
        $samplerLogin = $this->karyawan;
        $tanggal = $request->tanggal ?: date('Y-m-d');

        $data = Jadwal::with([
            'jadwalMobil' => function ($q) use ($tanggal) {
                $q->where('is_active', true)
                    ->where('tanggal_berangkat', $tanggal);
            },
            'quotationKontrakH' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling', 'alamat_sampling')
                    ->where('is_active', true);
            },
            'quotationNonKontrak' => function ($q) {
                $q->select('id', 'no_document', 'nama_pic_sampling', 'no_tlp_pic_sampling', 'alamat_sampling')
                    ->where('is_active', true);
            },
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
                'note',
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
                'id_cabang',
                'note'
            )
            ->whereNotNull('no_quotation')
            ->where('is_active', true)
            ->where('tanggal', $tanggal)
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

                $item->alamat_sampling = $quotation ? $quotation->alamat_sampling : null;

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
            ->filter(function ($item) use ($samplerLogin) {
                $login = strtolower(trim((string) $samplerLogin));
                if ($login === '') {
                    return false;
                }

                $samplers = collect(explode(',', (string) ($item->sampler ?? '')))
                    ->map(fn ($sampler) => strtolower(trim($sampler)));

                if ($samplers->contains($login)) {
                    return true;
                }

                return strtolower(trim((string) ($item->driver ?? ''))) === $login;
            })
            ->groupBy('kendaraan')
            ->map(function ($group) {
                $first = $group->first();

                $timSampler = $group
                    ->flatMap(function ($item) {
                        $allTeams = explode(',', $item->sampler ?? '');

                        if ($item->driver != '' && $item->driver != null) {
                            $allTeams[] = $item->driver;
                        }

                        $allTeams = array_values(array_unique($allTeams));

                        return collect($allTeams)
                            ->map(function ($sampler) use ($item) {
                                $sampler = trim($sampler);
                                if ($sampler === '') {
                                    return null;
                                }

                                return $sampler == $item->driver
                                    ? $sampler . ' (Driver)'
                                    : $sampler;
                            })
                            ->filter();
                    })
                    ->unique()
                    ->values()
                    ->implode(', ');

                return [
                    'kendaraan'    => $first->kendaraan,
                    'tim_sampler'  => $timSampler,
                    'jadwal_mobil' => $first->jadwal_mobil,
                    'list_pt'      => $group->map(function ($item) {
                        $arraySamplers = collect(explode(',', $item->sampler ?? ''))->map(function ($sampler) use ($item) {
                            $sampler = trim($sampler);

                            return ($sampler == $item->driver) ? $sampler . ' (Driver)' : $sampler;
                        });

                        return [
                            'no_quotation'    => $item->no_quotation,
                            'nama_perusahaan' => $item->nama_perusahaan,
                            'wilayah'         => $item->wilayah,
                            'sampler'         => $arraySamplers->filter()->implode(', '),
                            'jam_mulai'       => $item->jam_mulai,
                            'jam_selesai'     => $item->jam_selesai,
                            'durasi'          => $item->durasi,
                            'periode'         => $item->periode,
                            'pic'             => $item->pic,
                            'alamat_sampling' => $item->alamat_sampling,
                            'note'            => $item->note,
                        ];
                    })->values(),
                ];
            })
            ->values();

        return response()->json([
            'data' => $data,
        ]);
    }
}
