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
    private function jadwalTimGroupKey(object $row): string
    {
        return implode('|', [
            $row->no_quotation ?? '',
            $row->kendaraan ?? '',
            $row->parsial ?? '',
            $row->periode ?? '',
            $row->nama_perusahaan ?? '',
            $row->durasi ?? '',
            $row->driver ?? '',
            $row->jam_mulai ?? '',
            $row->jam_selesai ?? '',
            $row->wilayah ?? '',
            $row->id_cabang ?? '',
            $row->note ?? '',
        ]);
    }

    private function normalizeKategoriList($raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return array_values(array_filter($raw, fn ($v) => $v !== null && $v !== ''));
        }

        $decoded = json_decode((string) $raw, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($v) => $v !== null && $v !== ''));
        }

        return [(string) $raw];
    }

    private function subKategoriFromKategori(string $kategori): string
    {
        $parts = explode(' - ', $kategori);

        return trim($parts[0] ?? $kategori);
    }

    /** @return array<string, array<int, array{sub_kategori: string, jumlah_titik: int}>> */
    private function buildKategoriSummaryByGroupKey(string $tanggal): array
    {
        $rows = Jadwal::query()
            ->select(
                'no_quotation',
                'kendaraan',
                'parsial',
                'periode',
                'nama_perusahaan',
                'durasi',
                'driver',
                'jam_mulai',
                'jam_selesai',
                'wilayah',
                'id_cabang',
                'note',
                'kategori'
            )
            ->whereNotNull('no_quotation')
            ->where('is_active', true)
            ->where('tanggal', $tanggal)
            ->get();

        $countsByKey = [];

        foreach ($rows as $row) {
            $key = $this->jadwalTimGroupKey($row);
            foreach ($this->normalizeKategoriList($row->kategori) as $kat) {
                $sub = $this->subKategoriFromKategori((string) $kat);
                if ($sub === '') {
                    continue;
                }
                if (!isset($countsByKey[$key][$sub])) {
                    $countsByKey[$key][$sub] = 0;
                }
                $countsByKey[$key][$sub]++;
            }
        }

        $result = [];
        foreach ($countsByKey as $key => $counts) {
            ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
            $result[$key] = collect($counts)->map(function ($jumlah, $sub) {
                return [
                    'sub_kategori' => $sub,
                    'jumlah_titik' => $jumlah,
                ];
            })->values()->all();
        }

        return $result;
    }

    public function index(Request $request)
    {
        $kategoriSummaryByKey = $this->buildKategoriSummaryByGroupKey(
            (string) $request->tanggal
        );

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
                'no_quotation',
                'kendaraan',
                'parsial',
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
                'no_quotation',
                'kendaraan',
                'parsial',
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
            ->where('is_active',true)
            ->where('tanggal',$request->tanggal)
            ->orderBy('jam_mulai', 'asc')
            ->get()

            ->map(function ($item) {
                $quotation = strpos($item->no_quotation,'QTC') !== false
                        ? $item->quotationKontrakH
                        : $item->quotationNonKontrak;
                $item->pic = $quotation ? [
                        'nama_pic_sampling'=> $quotation->nama_pic_sampling,
                        'no_tlp_pic_sampling'=> $quotation->no_tlp_pic_sampling,
                    ]: null;
                unset($item->quotationKontrakH,$item->quotationNonKontrak);
                $teamSamplers = collect(
                    explode(',', $item->sampler)
                )->map(fn($s) => trim($s))->filter()->unique()->values();

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

            ->map(function ($group) use ($kategoriSummaryByKey) {
                $first = $group->first();
                return [
                    'tim_sampler' => $first->display_sampler,
                    'list_pt' =>
                        $group->map(function ($item) use ($kategoriSummaryByKey) {
                            $groupKey = $this->jadwalTimGroupKey($item);

                            return [
                                'nama_perusahaan'=> $item->nama_perusahaan,
                                'wilayah'=> $item->wilayah,
                                'sampler'=> $item->display_sampler,
                                'jam_mulai'=> $item->jam_mulai,
                                'jam_selesai'=> $item->jam_selesai,
                                'durasi'=> $item->durasi,
                                'periode'=> $item->periode,
                                'pic'=> $item->pic,
                                'note' => $item->note,
                                'kategori_summary' => $kategoriSummaryByKey[$groupKey] ?? [],
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