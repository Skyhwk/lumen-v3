<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\MasterKategori;
use App\Models\LogAnalisa;
use App\Models\RekapLiburKalender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Yajra\DataTables\Facades\DataTables;

class MonitoringKeterlambatanAnalisaController extends Controller
{
    public function index(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tahun = (int) ($request->tahun ?: Carbon::now()->year);

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->whereYear('tanggal_jadwal', $tahun)
            ->where('is_active', true)
            ->whereNull('input_analisa')
            ->select(
                'id_parameter',
                'nama_parameter',
                DB::raw('COUNT(*) as total_keterlambatan')
            )
            ->groupBy('id_parameter', 'nama_parameter')
            ->orderByDesc('total_keterlambatan')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data keterlambatan hasil analisa berhasil diambil',
        ]);
    }

    public function detail(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tahun = (int) ($request->tahun ?: Carbon::now()->year);
        $namaParameter = $request->nama_parameter;

        if (empty($namaParameter)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib diisi',
            ], 422);
        }

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->whereYear('tanggal_jadwal', $tahun)
            ->where('nama_parameter', $namaParameter)
            ->where('is_active', true)
            ->whereNull('input_analisa')
            ->select(
                'no_sampel',
                'id_parameter',
                'nama_parameter',
                'tanggal_jadwal',
                'ftc_verifier',
                'ftc_laboratory',
                'input_analisa'
            )
            ->orderBy('ftc_verifier', 'asc');

        return DataTables::of($data)
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%{$keyword}%");
            })
            ->filterColumn('id_parameter', function ($query, $keyword) {
                $query->where('id_parameter', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_laboratory', function ($query, $keyword) {
                $query->where('ftc_laboratory', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_verifier', function ($query, $keyword) {
                $query->where('ftc_verifier', 'like', "%{$keyword}%");
            })
            ->filterColumn('input_analisa', function ($query, $keyword) {
                $query->where('input_analisa', 'like', "%{$keyword}%");
            })
            ->filterColumn('nama_parameter', function ($query, $keyword) {
                $query->where('nama_parameter', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_jadwal', function ($query, $keyword) {
                $query->where('tanggal_jadwal', 'like', "%{$keyword}%");
            })
            ->orderColumn('ftc_laboratory', 'ftc_laboratory $1')
            ->orderColumn('ftc_verifier', 'ftc_verifier $1')
            ->orderColumn('input_analisa', 'input_analisa $1')
            ->orderColumn('no_sampel', 'no_sampel $1')
            ->orderColumn('id_parameter', 'id_parameter $1')
            ->orderColumn('nama_parameter', 'nama_parameter $1')
            ->orderColumn('tanggal_jadwal', 'tanggal_jadwal $1')
            ->make(true);
    }

    public function indexPersentase(Request $request)
    {
        try {
            $kategori = $request->kategori ?: '1-Air';
            $tanggal = $request->tanggal ?: $this->getDefaultTanggalFilter();
            $tahun = (int) Carbon::parse($tanggal)->year;
            $kalenderLengkap = array_merge(
                $this->getKalenderHariKerja($tahun - 1),
                $this->getKalenderHariKerja($tahun)
            );
            sort($kalenderLengkap);

            $tanggalSebelumnya = $this->getHariKerjaSebelumnya($tanggal, $kalenderLengkap);
            $tanggalSebelumSebelumnya = $this->getHariKerjaSebelumnya($tanggalSebelumnya, $kalenderLengkap);

            $statsHariIni = $this->getStatistikParameterPerHari($kategori, $tanggal);
            $statsSebelumnya = $this->getStatistikParameterPerHari($kategori, $tanggalSebelumnya);
            $statsSebelumSebelumnya = $this->getStatistikParameterPerHari($kategori, $tanggalSebelumSebelumnya);

            $allParameterIds = $statsHariIni->keys()
                ->merge($statsSebelumnya->keys())
                ->unique();

            $data = $allParameterIds->map(function ($idParameter) use (
                $statsHariIni,
                $statsSebelumnya,
                $statsSebelumSebelumnya
            ) {
                $hariIni = $statsHariIni->get($idParameter);
                $sebelumnya = $statsSebelumnya->get($idParameter);
                $sebelumSebelumnya = $statsSebelumSebelumnya->get($idParameter);

                $carryHariIni = $sebelumnya ? (int) $sebelumnya->total_belum : 0;
                $carrySebelumnya = $sebelumSebelumnya ? (int) $sebelumSebelumnya->total_belum : 0;

                if (!$hariIni && $carryHariIni === 0) {
                    return null;
                }

                $persentaseHariIni = $this->hitungPersentaseDenganCarry($hariIni, $carryHariIni);
                $persentaseSebelumnya = $this->hitungPersentaseDenganCarry($sebelumnya, $carrySebelumnya);

                return (object) [
                    'id_parameter' => $idParameter,
                    'nama_parameter' => $hariIni
                        ? $hariIni->nama_parameter
                        : ($sebelumnya ? $sebelumnya->nama_parameter : null),
                    'total' => $persentaseHariIni['total'],
                    'total_belum' => $persentaseHariIni['belum'],
                    'total_sudah' => $persentaseHariIni['sudah'],
                    'carry_belum' => $carryHariIni,
                    'persentase' => $persentaseHariIni['persentase'],
                    'indikator' => $persentaseHariIni['persentase'] > $persentaseSebelumnya['persentase']
                        ? 'naik'
                        : 'turun',
                ];
            })
                ->filter()
                ->sortByDesc('persentase')
                ->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'info_filter' => $this->buildInfoFilterPersentase($tanggal, $tanggalSebelumnya),
                'message' => 'Data persentase keterlambatan berhasil diambil',
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memuat data persentase: ' . $th->getMessage(),
                'data' => [],
            ], 500);
        }
    }

    public function detailPersentase(Request $request)
    {
        $kategori = $request->kategori ?: '1-Air';
        $tanggal = $request->tanggal ?: $this->getDefaultTanggalFilter();
        $namaParameter = $request->nama_parameter;

        if (empty($namaParameter)) {
            return response()->json([
                'success' => false,
                'message' => 'Parameter wajib diisi',
            ], 422);
        }

        $tahun = (int) Carbon::parse($tanggal)->year;
        $kalenderLengkap = array_merge(
            $this->getKalenderHariKerja($tahun - 1),
            $this->getKalenderHariKerja($tahun)
        );
        sort($kalenderLengkap);
        $tanggalSebelumnya = $this->getHariKerjaSebelumnya($tanggal, $kalenderLengkap);

        $data = LogAnalisa::where('kategori_2', $kategori)
            ->where('nama_parameter', $namaParameter)
            ->where('is_active', true)
            ->where(function ($query) use ($tanggal, $tanggalSebelumnya) {
                $query->where(function ($q) use ($tanggal) {
                    $this->applyFilterTanggalPersentase($q, $tanggal);
                })->orWhere(function ($subQuery) use ($tanggalSebelumnya) {
                    $this->applyFilterTanggalPersentase($subQuery, $tanggalSebelumnya);
                    $subQuery->whereNull('input_analisa');
                });
            })
            ->when($request->status_analisa === 'belum', function ($q) {
                return $q->whereNull('input_analisa');
            })
            ->when($request->status_analisa === 'sudah', function ($q) {
                return $q->whereNotNull('input_analisa');
            })
            ->select(
                'no_sampel',
                'id_parameter',
                'ftc_laboratory',
                'ftc_verifier',
                'nama_parameter',
                'tanggal_jadwal',
                'input_analisa'
            )
            ->orderByRaw(
                'CASE WHEN ' . $this->sqlDateFilterPersentase() . ' = ? THEN 0 ELSE 1 END',
                [$tanggalSebelumnya]
            )
            ->orderBy('ftc_verifier', 'asc');

        return DataTables::of($data)
            ->addColumn('status_analisa', function ($row) {
                return $row->input_analisa ? 'sudah' : 'belum';
            })
            ->addColumn('sumber', function ($row) use ($tanggal) {
                return $this->isTanggalFilterPersentase($row, $tanggal)
                    ? 'Scan analis hari ini'
                    : 'Belum selesai kemarin';
            })
            ->addColumn('is_carry', function ($row) use ($tanggal) {
                return !$this->isTanggalFilterPersentase($row, $tanggal);
            })
            ->filterColumn('no_sampel', function ($query, $keyword) {
                $query->where('no_sampel', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_laboratory', function ($query, $keyword) {
                $query->where('ftc_laboratory', 'like', "%{$keyword}%");
            })
            ->filterColumn('ftc_verifier', function ($query, $keyword) {
                $query->where('ftc_verifier', 'like', "%{$keyword}%");
            })
            ->filterColumn('input_analisa', function ($query, $keyword) {
                $query->where('input_analisa', 'like', "%{$keyword}%");
            })
            ->filterColumn('tanggal_jadwal', function ($query, $keyword) {
                $query->where('tanggal_jadwal', 'like', "%{$keyword}%");
            })
            ->filterColumn('sumber', function ($query, $keyword) use ($tanggal, $tanggalSebelumnya) {
                if (stripos('Belum selesai kemarin', $keyword) !== false) {
                    $this->applyFilterTanggalPersentase($query, $tanggalSebelumnya);
                } elseif (stripos('Scan analis hari ini', $keyword) !== false) {
                    $this->applyFilterTanggalPersentase($query, $tanggal);
                }
            })
            ->orderColumn('ftc_laboratory', 'ftc_laboratory $1')
            ->orderColumn('ftc_verifier', 'ftc_verifier $1')
            ->orderColumn('input_analisa', 'input_analisa $1')
            ->orderColumn('no_sampel', 'no_sampel $1')
            ->orderColumn('tanggal_jadwal', 'tanggal_jadwal $1')
            ->make(true);
    }

    public function getKategori(Request $request)
    {
        $data = MasterKategori::where('is_active', 1)->get();

        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => 'Data kategori berhasil diambil',
        ]);
    }

    private function getDefaultTanggalFilter(): string
    {
        return Carbon::today()->subDay()->format('Y-m-d');
    }

    private function getStatistikParameterPerHari(string $kategori, string $tanggal)
    {
        $query = LogAnalisa::where('kategori_2', $kategori)
            ->where('is_active', true);

        $this->applyFilterTanggalPersentase($query, $tanggal);

        return $query
            ->select(
                'id_parameter',
                'nama_parameter',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN input_analisa IS NULL THEN 1 ELSE 0 END) as total_belum'),
                DB::raw('SUM(CASE WHEN input_analisa IS NOT NULL THEN 1 ELSE 0 END) as total_sudah')
            )
            ->groupBy('id_parameter', 'nama_parameter')
            ->get()
            ->keyBy('id_parameter');
    }

    private function getKolomFilterPersentase(): string
    {
        return 'ftc_laboratory';
    }

    private function applyFilterTanggalPersentase($query, string $tanggal)
    {
        // untuk kasus di mana filter adalah ftc_laboratory.
        return $query->whereDate($this->getKolomFilterPersentase(), $tanggal)
            ->whereNotNull($this->getKolomFilterPersentase());

        // untuk kasus di mana filter adalah tanggal jadwal / sampling.
        // return $query->where($this->getKolomFilterPersentase(), $tanggal)
        //     ->whereNotNull($this->getKolomFilterPersentase());
    }

    private function isTanggalFilterPersentase($row, string $tanggal): bool
    {
        $kolom = $this->getKolomFilterPersentase();
        if (empty($row->{$kolom})) {
            return false;
        }

        return Carbon::parse($row->{$kolom})->format('Y-m-d') === $tanggal;
    }

    private function sqlDateFilterPersentase(): string
    {
        return 'DATE(' . $this->getKolomFilterPersentase() . ')';
    }

    private function buildInfoFilterPersentase(string $tanggal, string $tanggalSebelumnya): string
    {
        return sprintf(
            'Filter pencarian ini berdasarkan tanggal scan analis.',
            Carbon::parse($tanggal)->format('d-m-Y'),
            Carbon::parse($tanggalSebelumnya)->format('d-m-Y')
        );
    }

    private function hitungPersentaseDenganCarry($statHari, int $carryBelum): array
    {
        $totalHari = (int) ($statHari->total ?? 0);
        $belumHari = (int) ($statHari->total_belum ?? 0);
        $sudahHari = (int) ($statHari->total_sudah ?? 0);

        $total = $totalHari + $carryBelum;
        $belum = $belumHari + $carryBelum;
        $sudah = $sudahHari;

        $persentase = $total > 0
            ? round(($belum / $total) * 100, 2)
            : 0;

        return [
            'total' => $total,
            'belum' => $belum,
            'sudah' => $sudah,
            'persentase' => $persentase,
        ];
    }

    private function getKalenderHariKerja(int $tahun): array
    {
        $dbKalender = RekapLiburKalender::where('tahun', $tahun)
            ->where('is_active', 1)
            ->first();

        if (!$dbKalender) {
            return [];
        }

        $kalenderBulan = json_decode($dbKalender->tanggal, true);
        if (!is_array($kalenderBulan)) {
            return [];
        }

        $kalenderLengkap = [];
        foreach ($kalenderBulan as $dates) {
            if (is_array($dates)) {
                $kalenderLengkap = array_merge($kalenderLengkap, $dates);
            }
        }

        return $kalenderLengkap;
    }

    private function getHariKerjaSebelumnya(string $tanggal, array $kalenderLengkap): string
    {
        $index = array_search($tanggal, $kalenderLengkap);
        if ($index !== false && $index > 0) {
            return $kalenderLengkap[$index - 1];
        }

        return Carbon::parse($tanggal)->subWeekdays(1)->format('Y-m-d');
    }
}
