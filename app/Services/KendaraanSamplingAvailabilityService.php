<?php

namespace App\Services;

use App\Models\DaftarMobil;
use App\Models\DaftarMobilSewa;
use App\Models\PenggunaanMobilDetail;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\DB;

class KendaraanSamplingAvailabilityService
{
    public function availableByTanggal(?string $tanggal, $idSamplingEdit = null): array
    {
        if (!$tanggal) {
            return [
                'status' => true,
                'data' => [],
                'conflicts' => new \stdClass(),
            ];
        }

        $tanggal = Carbon::parse($tanggal)->format('Y-m-d');
        $bookedPlats = $this->bookedPenggunaanPlats($tanggal);

        $options = [];
        $seen = [];

        foreach ($this->operationalCars() as $mobil) {
            $plat = trim((string) $mobil->plat_mobil);
            if ($plat === '') {
                continue;
            }
            $key = $this->normalizePlat($plat);
            if (isset($bookedPlats[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = [
                'id' => $plat,
                'text' => $plat,
                'source' => 'ops',
                'source_id' => (int) $mobil->id,
            ];
        }

        foreach ($this->sewaCarsOnDate($tanggal) as $mobil) {
            $plat = trim((string) $mobil->plat_mobil);
            if ($plat === '') {
                continue;
            }
            $key = $this->normalizePlat($plat);
            if (isset($bookedPlats[$key]) || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $options[] = [
                'id' => $plat,
                'text' => $plat,
                'source' => 'sewa',
                'source_id' => (int) $mobil->id,
            ];
        }

        usort($options, fn ($a, $b) => strcmp($a['id'], $b['id']));

        return [
            'status' => true,
            'data' => $options,
            'conflicts' => $this->jadwalConflicts($tanggal, $idSamplingEdit),
        ];
    }

    /**
     * @return array{ok:bool,plat:?string,tanggal:?string,alasan:?string,message:?string}
     */
    public function validateSelection(?string $kendaraan, ?string $tanggal): array
    {
        $tanggalFmt = $tanggal ? Carbon::parse($tanggal)->format('Y-m-d') : null;
        $plat = $this->stripTebeng((string) $kendaraan);

        if ($plat === '' || !$tanggalFmt) {
            return [
                'ok' => false,
                'plat' => $plat ?: null,
                'tanggal' => $tanggalFmt,
                'alasan' => 'Plat kendaraan atau tanggal sampling kosong.',
                'message' => $this->formatRejectMessage($plat ?: '-', $tanggalFmt ?: '-', 'Plat kendaraan atau tanggal sampling kosong.'),
            ];
        }

        $key = $this->normalizePlat($plat);
        $booked = $this->bookedPenggunaanPlats($tanggalFmt);
        if (isset($booked[$key])) {
            $alasan = 'Mobil memiliki catatan Penggunaan Mobil aktif pada tanggal tersebut.';
            return [
                'ok' => false,
                'plat' => $plat,
                'tanggal' => $tanggalFmt,
                'alasan' => $alasan,
                'message' => $this->formatRejectMessage($plat, $tanggalFmt, $alasan),
            ];
        }

        $ops = $this->findOperationalByPlat($plat);
        if ($ops) {
            return [
                'ok' => true,
                'plat' => $plat,
                'tanggal' => $tanggalFmt,
                'alasan' => null,
                'message' => null,
            ];
        }

        $sewa = $this->findSewaByPlat($plat);
        if ($sewa) {
            $hasTanggal = $sewa->tanggalSewaAktif()
                ->whereDate('tanggal_sewa', $tanggalFmt)
                ->exists();

            if (!$hasTanggal) {
                $alasan = 'Mobil sewa tidak tercatat pada tanggal tersebut (termasuk jeda antar tanggal sewa atau di luar masa sewa).';
                return [
                    'ok' => false,
                    'plat' => $plat,
                    'tanggal' => $tanggalFmt,
                    'alasan' => $alasan,
                    'message' => $this->formatRejectMessage($plat, $tanggalFmt, $alasan),
                ];
            }

            return [
                'ok' => true,
                'plat' => $plat,
                'tanggal' => $tanggalFmt,
                'alasan' => null,
                'message' => null,
            ];
        }

        $alasan = 'Mobil tidak ditemukan di Daftar Mobil maupun Daftar Mobil Sewa.';
        return [
            'ok' => false,
            'plat' => $plat,
            'tanggal' => $tanggalFmt,
            'alasan' => $alasan,
            'message' => $this->formatRejectMessage($plat, $tanggalFmt, $alasan),
        ];
    }

    /**
     * @param  array<int, string|null>  $kendaraanList
     * @param  array<int, string|null>  $tanggalList
     * @throws Exception
     */
    public function assertSelectionsAvailable(array $kendaraanList, array $tanggalList): void
    {
        $count = max(count($kendaraanList), count($tanggalList));
        for ($i = 0; $i < $count; $i++) {
            $kendaraan = $kendaraanList[$i] ?? null;
            $tanggal = $tanggalList[$i] ?? ($tanggalList[0] ?? null);
            if ($kendaraan === null || $kendaraan === '') {
                continue;
            }
            $result = $this->validateSelection($kendaraan, $tanggal);
            if (!$result['ok']) {
                throw new Exception($result['message'], 422);
            }
        }
    }

    public function assertSelectionAvailable(?string $kendaraan, ?string $tanggal): void
    {
        $result = $this->validateSelection($kendaraan, $tanggal);
        if (!$result['ok']) {
            throw new Exception($result['message'], 422);
        }
    }

    public function stripTebeng(string $kendaraan): string
    {
        return trim(preg_replace(
            '/\s*\(Tebeng Transportasi(?:\s*[-:]\s*(?:Tim\s+)?[^)]*)?\)\s*$/i',
            '',
            $kendaraan
        ) ?? '');
    }

    public function normalizePlat(string $plat): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($plat)) ?? '');
    }

    public function formatRejectMessage(string $plat, string $tanggal, string $alasan): string
    {
        return "Kendaraan {$plat} tidak tersedia pada tanggal {$tanggal}. Alasan: {$alasan}";
    }

    protected function operationalCars()
    {
        return DaftarMobil::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->orderBy('plat_mobil')
            ->get(['id', 'plat_mobil', 'merk_mobil', 'tipe_mobil']);
    }

    protected function sewaCarsOnDate(string $tanggal)
    {
        return DaftarMobilSewa::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereHas('tanggalSewaAktif', function ($q) use ($tanggal) {
                $q->whereDate('tanggal_sewa', $tanggal);
            })
            ->orderBy('plat_mobil')
            ->get(['id', 'plat_mobil', 'merk_mobil', 'tipe_mobil']);
    }

    /**
     * @return array<string, true> keyed by normalized plat
     */
    protected function bookedPenggunaanPlats(string $tanggal): array
    {
        $rows = PenggunaanMobilDetail::query()
            ->from('penggunaan_mobil_detail as d')
            ->join('penggunaan_mobil_header as h', function ($join) {
                $join->on('h.id', '=', 'd.header_id')->where('h.is_active', true);
            })
            ->join('daftar_mobil as m', 'm.id', '=', 'd.mobil_id')
            ->where('d.is_active', true)
            ->whereNull('d.deleted_at')
            ->whereDate('d.tanggal_penggunaan', $tanggal)
            ->where('m.is_active', true)
            ->get(['m.plat_mobil']);

        $map = [];
        foreach ($rows as $row) {
            $key = $this->normalizePlat((string) $row->plat_mobil);
            if ($key !== '') {
                $map[$key] = true;
            }
        }

        return $map;
    }

    protected function findOperationalByPlat(string $plat): ?DaftarMobil
    {
        $key = $this->normalizePlat($plat);
        if ($key === '') {
            return null;
        }

        return DaftarMobil::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("UPPER(REPLACE(REPLACE(plat_mobil, ' ', ''), '\t', '')) = ?", [$key])
            ->first();
    }

    protected function findSewaByPlat(string $plat): ?DaftarMobilSewa
    {
        $key = $this->normalizePlat($plat);
        if ($key === '') {
            return null;
        }

        return DaftarMobilSewa::query()
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->whereRaw("UPPER(REPLACE(REPLACE(plat_mobil, ' ', ''), '\t', '')) = ?", [$key])
            ->first();
    }

    /**
     * Jadwal conflicts keyed by base plat (for Tebeng Transportasi UX).
     */
    protected function jadwalConflicts(string $tanggal, $idSamplingEdit = null): object
    {
        $query = DB::table('jadwal')
            ->select('kendaraan', 'sampler', 'jam_mulai', 'created_at', 'updated_at')
            ->where('tanggal', $tanggal)
            ->whereNotNull('kendaraan')
            ->where('is_active', 1);

        if ($idSamplingEdit) {
            $query->where('id_sampling', '!=', $idSamplingEdit);
        }

        $detailKonflik = KendaraanConflictHelper::buildDetailKonflik($query->get());

        return (object) $detailKonflik;
    }
}
