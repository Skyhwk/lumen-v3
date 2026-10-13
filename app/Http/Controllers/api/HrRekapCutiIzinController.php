<?php



namespace App\Http\Controllers\api;



use App\Http\Controllers\Controller;

use App\Models\MasterKaryawan;

use App\Services\Hr\RekapCutiIzinService;

use Carbon\Carbon;

use Illuminate\Http\Request;

use Illuminate\Support\Facades\DB;

use Yajra\Datatables\Datatables;



class HrRekapCutiIzinController extends Controller

{

    /** @var Carbon|null */

    private static $periodFrom;



    /** @var Carbon|null */

    private static $periodTo;



    /** @var RekapCutiIzinService|null */

    private static $service;

    /** @var array<int, array<string, mixed>> */

    private static $metricsMemo = [];

    /** @var string */

    private static $filterMode = 'tahun';



    public function index(Request $request, RekapCutiIzinService $rekapCutiIzinService)

    {

        [$from, $to] = $this->resolvePeriod($request);

        self::$periodFrom = $from;

        self::$periodTo = $to;

        self::$filterMode = strtolower(trim((string) ($request->input('filter_mode') ?: 'tahun')));

        self::$service = $rekapCutiIzinService;

        self::$metricsMemo = [];



        $query = DB::table('master_karyawan as mk')

            ->leftJoin('master_divisi as d', 'mk.id_department', '=', 'd.id')

            ->where('mk.is_active', true)

            ->select(

                'mk.id',

                'mk.nik_karyawan',

                'mk.nama_lengkap',

                'mk.status_karyawan',

                DB::raw('COALESCE(d.nama_divisi, "") as nama_divisi')

            );

        $this->applyEmployeeFilters($query, $request);

        $dt = Datatables::of($query)

            ->addColumn('cuti_days', function ($row) {

                return $this->metricsForRow($row)['cuti_days'];

            })

            ->addColumn('izin_sakit_days', function ($row) {

                return $this->metricsForRow($row)['izin_sakit_days'];

            })

            ->addColumn('izin_kegiatan_days', function ($row) {

                return $this->metricsForRow($row)['izin_kegiatan_days'];

            })

            ->addColumn('izin_terlambat_count', function ($row) {

                return $this->metricsForRow($row)['izin_terlambat_count'];

            })

            ->addColumn('alpa_days', function ($row) {

                return $this->metricsForRow($row)['alpa_days'];

            })

            ->orderColumn('nik_karyawan', 'mk.nik_karyawan $1')

            ->orderColumn('nama_lengkap', 'mk.nama_lengkap $1')

            ->orderColumn('nama_divisi', 'd.nama_divisi $1')

            ->orderColumn('status_karyawan', 'mk.status_karyawan $1');



        return $this->applyRekapCutiIzinDatatablesFilters($dt, $request)->make(true);

    }



    /**

     * @param  \Yajra\DataTables\DataTableAbstract  $datatables

     * @return \Yajra\DataTables\DataTableAbstract

     */

    private function applyRekapCutiIzinDatatablesFilters($datatables, Request $request)

    {

        return $datatables

            ->filterColumn('nik_karyawan', function ($query, $keyword) {

                $query->where('mk.nik_karyawan', 'like', '%' . $keyword . '%');

            })

            ->filterColumn('nama_lengkap', function ($query, $keyword) {

                $query->where('mk.nama_lengkap', 'like', '%' . $keyword . '%');

            })

            ->filterColumn('nama_divisi', function ($query, $keyword) {

                $query->where('d.nama_divisi', 'like', '%' . $keyword . '%');

            })

            ->filterColumn('status_karyawan', function ($query, $keyword) {

                $query->where('mk.status_karyawan', 'like', '%' . $keyword . '%');

            });

    }



    /**

     * @return array{0: Carbon, 1: Carbon}

     */

    private function resolvePeriod(Request $request): array

    {

        $year = (int) ($request->input('periode') ?: date('Y'));

        $year = max(2000, min(2100, $year));

        $mode = strtolower(trim((string) ($request->input('filter_mode') ?: 'tahun')));



        $rangeFrom = trim((string) ($request->input('tanggal_mulai') ?: $request->input('range_from') ?: ''));

        $rangeTo = trim((string) ($request->input('tanggal_akhir') ?: $request->input('range_to') ?: ''));



        if ($mode === 'range' && $rangeFrom !== '' && $rangeTo !== '') {

            try {

                $from = Carbon::parse($rangeFrom)->startOfDay();

                $to = Carbon::parse($rangeTo)->startOfDay();

                if ($to->lt($from)) {

                    [$from, $to] = [$to, $from];

                }



                return [$from, $to];

            } catch (\Throwable $e) {

                // fallback ke tahun/bulan

            }

        }



        $month = (int) $request->input('bulan', 0);

        if ($mode === 'bulan' && $month >= 1 && $month <= 12) {

            $from = Carbon::create($year, $month, 1)->startOfDay();

            $to = $from->copy()->endOfMonth()->startOfDay();



            return [$from, $to];

        }



        $from = Carbon::create($year, 1, 1)->startOfDay();

        $to = Carbon::create($year, 12, 31)->startOfDay();



        return [$from, $to];

    }



    /**

     * @param  \Illuminate\Database\Query\Builder  $query

     */

    private function applyEmployeeFilters($query, Request $request): void

    {

        $nik = $this->resolveFilterKeyword($request, 'filter_nik', 'nik_karyawan', 1);

        if ($nik !== '') {

            $query->where('mk.nik_karyawan', 'like', '%' . $nik . '%');

        }



        $nama = $this->resolveFilterKeyword($request, 'filter_nama', 'nama_lengkap', 2);

        if ($nama !== '') {

            $query->where('mk.nama_lengkap', 'like', '%' . $nama . '%');

        }



        $divisi = $this->resolveFilterKeyword($request, 'filter_divisi', 'nama_divisi', 3);

        if ($divisi !== '') {

            $query->where('d.nama_divisi', 'like', '%' . $divisi . '%');

        }



        $status = $this->resolveFilterKeyword($request, 'filter_status', 'status_karyawan', 4);

        if ($status !== '') {

            $query->where('mk.status_karyawan', 'like', '%' . $status . '%');

        }

    }



    private function resolveFilterKeyword(Request $request, string $flatKey, string $columnData, int $columnIndex): string

    {

        $flat = trim((string) $request->input($flatKey, ''));

        if ($flat !== '') {

            return $flat;

        }



        $byData = $this->columnSearchValue($request, $columnData);

        if ($byData !== '') {

            return $byData;

        }



        return $this->columnSearchValueByIndex($request, $columnIndex);

    }



    private function columnSearchValue(Request $request, string $columnData): string

    {

        $columns = $request->input('columns', []);

        if (!is_array($columns)) {

            return '';

        }



        foreach ($columns as $column) {

            if (!is_array($column)) {

                continue;

            }

            $data = $column['data'] ?? null;

            $name = $column['name'] ?? null;

            if ($data === $columnData || $name === $columnData) {

                return trim((string) ($column['search']['value'] ?? ''));

            }

        }



        return '';

    }



    private function columnSearchValueByIndex(Request $request, int $columnIndex): string

    {

        $columns = $request->input('columns', []);

        if (!is_array($columns) || !isset($columns[$columnIndex])) {

            return '';

        }



        $column = $columns[$columnIndex];

        if (!is_array($column)) {

            return '';

        }



        return trim((string) ($column['search']['value'] ?? ''));

    }



    /**

     * @param object $row

     */

    private function employeeFromRow($row): MasterKaryawan

    {

        $employee = new MasterKaryawan();

        $employee->id = (int) $row->id;

        $employee->nik_karyawan = $row->nik_karyawan ?? null;

        $employee->nama_lengkap = $row->nama_lengkap ?? null;

        $employee->status_karyawan = $row->status_karyawan ?? null;



        return $employee;

    }



    /**

     * @param object $row

     * @return array<string, mixed>

     */

    private function metricsForRow($row): array

    {

        $key = (int) $row->id;

        if (!isset(self::$metricsMemo[$key])) {

            self::$metricsMemo[$key] = self::$service->metricsForEmployee(

                $this->employeeFromRow($row),

                self::$periodFrom,

                self::$periodTo,

                self::$filterMode

            );

        }



        return self::$metricsMemo[$key];

    }

}


