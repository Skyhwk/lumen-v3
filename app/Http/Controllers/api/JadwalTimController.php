<?php

namespace App\Http\Controllers\api;
use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\JadwalMobil;
use App\Models\Jadwal;


use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Yajra\Datatables\Datatables;
use Carbon\Carbon;
class JadwalTimController extends Controller
{
    private function normalizedTeamKey(object $item): string
    {
        $teamSamplers = collect(explode(',', (string) ($item->sampler ?? '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->values();

        if (!empty($item->driver) && !$teamSamplers->contains($item->driver)) {
            $teamSamplers->push($item->driver);
        }

        return $teamSamplers->sort()->values()->implode(', ');
    }

    private function displayTeamSampler(object $item): string
    {
        $teamSamplers = collect(explode(',', (string) ($item->sampler ?? '')))
            ->map(fn ($s) => trim($s))
            ->filter()
            ->unique()
            ->values();

        if (!empty($item->driver) && !$teamSamplers->contains($item->driver)) {
            $teamSamplers->push($item->driver);
        }

        return $teamSamplers
            ->map(fn ($sampler) => $sampler === $item->driver ? $sampler . ' (Driver)' : $sampler)
            ->implode(', ');
    }

    private function teamSamplerSet(string $normalizedTeam): Collection
    {
        return collect(explode(',', $normalizedTeam))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values();
    }

    /** Baris jadwal mentah hanya dihitung jika petugasnya termasuk tim jadwal baris tersebut. */
    private function matchesTimJadwal(object $raw, string $identityTeamSampler): bool
    {
        $teamSet = $this->teamSamplerSet($identityTeamSampler);
        $rawSet = $this->teamSamplerSet($this->normalizedTeamKey($raw));

        if ($teamSet->isEmpty() || $rawSet->isEmpty()) {
            return false;
        }

        return $rawSet->every(fn ($name) => $teamSet->contains($name));
    }

    private function normalizeIdentityValue($value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return trim((string) $value);
    }

    /** Satu baris tabel = satu jadwal QT (bukan digabung per nama PT). */
    private function jadwalRowIdentity(object $item): array
    {
        return [
            'no_quotation' => $item->no_quotation,
            'parsial' => $item->parsial,
            'note' => $item->note,
            'jam_mulai' => $item->jam_mulai,
            'jam_selesai' => $item->jam_selesai,
            'team_sampler' => $item->team_sampler ?? $this->normalizedTeamKey($item),
        ];
    }

    private function matchesJadwalIdentity(object $raw, array $identity): bool
    {
        foreach (['no_quotation', 'parsial', 'note', 'jam_mulai', 'jam_selesai'] as $field) {
            if (
                $this->normalizeIdentityValue($raw->{$field} ?? null)
                !== $this->normalizeIdentityValue($identity[$field] ?? null)
            ) {
                return false;
            }
        }

        return $this->matchesTimJadwal($raw, (string) $identity['team_sampler']);
    }

    /** @return array<int, string> */
    private function parseKategoriField($kategori): array
    {
        if ($kategori === null || $kategori === '') {
            return [];
        }

        if (is_array($kategori)) {
            $list = $kategori;
        } elseif (is_string($kategori)) {
            $decoded = json_decode($kategori, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $list = is_array($decoded) ? $decoded : [$decoded];
            } else {
                $list = [$kategori];
            }
        } else {
            return [];
        }

        return collect($list)
            ->flatten()
            ->filter(fn ($item) => is_string($item) && trim($item) !== '')
            ->values()
            ->all();
    }

    /** @return array<int, array{nama: string, jumlah: int}> */
    private function ringkasanKategoriPerPt(Collection $rawJadwalRows, array $identity): array
    {
        /** @var array<string, array<string, string>> $titikUnikPerSubKategori */
        $titikUnikPerSubKategori = [];

        $rawJadwalRows
            ->filter(fn ($raw) => $this->matchesJadwalIdentity($raw, $identity))
            ->each(function ($raw) use (&$titikUnikPerSubKategori) {
                foreach ($this->parseKategoriField($raw->kategori) as $kategori) {
                    $kategori = trim($kategori);
                    if ($kategori === '') {
                        continue;
                    }

                    $parts = explode(' - ', $kategori, 2);
                    $subKategori = trim($parts[0]);
                    if ($subKategori === '') {
                        continue;
                    }

                    $subKey = mb_strtolower($subKategori);
                    $titikKey = mb_strtolower($kategori);

                    if (!isset($titikUnikPerSubKategori[$subKey])) {
                        $titikUnikPerSubKategori[$subKey] = [];
                    }

                    $titikUnikPerSubKategori[$subKey][$titikKey] = $subKategori;
                }
            });

        return collect($titikUnikPerSubKategori)
            ->map(function (array $titikMap, string $subKey) {
                $nama = reset($titikMap) ?: $subKey;

                return [
                    'nama' => $nama,
                    'jumlah' => count($titikMap),
                ];
            })
            ->sortBy('nama', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    public function index(Request $request)
    {
        $rawJadwalForKategori = Jadwal::query()
            ->select(
                'no_quotation',
                'parsial',
                'jam_mulai',
                'jam_selesai',
                'note',
                'sampler',
                'driver',
                'kategori'
            )
            ->whereNotNull('no_quotation')
            ->where('is_active', true)
            ->where('tanggal', $request->tanggal)
            ->get();

        $jadwalItems = Jadwal::with([
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
            ->where('is_active', true)
            ->where('tanggal', $request->tanggal)
            ->orderBy('jam_mulai', 'asc')
            ->get()
            ->map(function ($item) {
                $quotation = strpos($item->no_quotation, 'QTC') !== false
                    ? $item->quotationKontrakH
                    : $item->quotationNonKontrak;
                $item->pic = $quotation ? [
                    'nama_pic_sampling' => $quotation->nama_pic_sampling,
                    'no_tlp_pic_sampling' => $quotation->no_tlp_pic_sampling,
                ] : null;
                unset($item->quotationKontrakH, $item->quotationNonKontrak);

                $item->team_sampler = $this->normalizedTeamKey($item);
                $item->display_sampler = $this->displayTeamSampler($item);
                return $item;
            });

        $data = $jadwalItems
            ->groupBy('team_sampler')
            ->map(function ($group) use ($rawJadwalForKategori) {
                $first = $group->first();

                $listPt = $group->map(function ($item) use ($rawJadwalForKategori) {
                    $identity = $this->jadwalRowIdentity($item);
                    $kategoriRingkasan = $this->ringkasanKategoriPerPt(
                        $rawJadwalForKategori,
                        $identity
                    );

                    return [
                        'no_quotation' => $item->no_quotation,
                        'nama_perusahaan' => $item->nama_perusahaan,
                        'wilayah' => $item->wilayah,
                        'sampler' => $item->display_sampler,
                        'jam_mulai' => $item->jam_mulai,
                        'jam_selesai' => $item->jam_selesai,
                        'durasi' => $item->durasi,
                        'periode' => $item->periode,
                        'pic' => $item->pic,
                        'note' => $item->note,
                        'kategori_ringkasan' => $kategoriRingkasan,
                    ];
                })->values();

                return [
                    'tim_sampler' => $first->display_sampler,
                    'list_pt' => $listPt,
                ];
            })
            ->values();

        return Datatables::of($data)
            ->make(true);
    }

}