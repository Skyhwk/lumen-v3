<?php
namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\SamplingPlan;
use App\Models\TicketSampling;
use App\Models\DasarTargetPenjadwalan as DasarTarget;
use App\Models\MasterTargetPenjadwalan as MasterTarget;
use App\Models\KalkulasiTargetPenjadwalan as KalkulasiTarget;
use Carbon\Carbon;
use Illuminate\Http\Request;

Carbon::setLocale('id');

class DashboardAdmSamplingController extends Controller
{
    public function index(Request $request)
    {
        $tahunSekarang = $request->periode
            ? explode(' ', $request->periode)[1]
            : Carbon::now()->year;

        $namaBulan = $request->periode
            ? strtolower(explode(' ', $request->periode)[0])
            : strtolower(Carbon::now()->locale('id')->monthName);

        $numMonth = $request->month
            ? $request->month
            : Carbon::now()->format('m');

        /*
        |--------------------------------------------------------------------------
        | SUMMARY CARD
        |--------------------------------------------------------------------------
        */

        $spNormal = SamplingPlan::query()
            ->where('is_active', true)
            ->where('status', 0)
            ->where('is_approved', 0)
            ->whereRaw("no_document NOT REGEXP 'R[0-9]+$'")
            ->count();

        $spRevisi = SamplingPlan::query()
            ->where('is_active', true)
            ->where('status', 0)
            ->where('is_approved', 0)
            ->whereRaw("no_document REGEXP 'R[0-9]+$'")
            ->count();

        $ticketSampling = TicketSampling::query()
            ->where('is_active', true)
            ->whereIn('status', ['WAITING PROCESS', 'PENDING'])
            ->count();

        /*
        |--------------------------------------------------------------------------
        | TARGET PERSENTASE
        |--------------------------------------------------------------------------
        */

        $masterTarget = MasterTarget::query()
            ->where('tahun', $tahunSekarang)
            ->where('is_active', 1)
            ->first();

        $kalkulasiTarget = KalkulasiTarget::query()
            ->where('tahun', $tahunSekarang)
            ->first();

        $nilaiMasterTarget = $masterTarget->$namaBulan ?? 0;
        $nilaiKalkulasi = $kalkulasiTarget->$namaBulan ?? 0;

        $nilaiPersentase = $nilaiMasterTarget > 0
            ? ($nilaiKalkulasi / $nilaiMasterTarget) * 100
            : 0;

        $matched = DasarTarget::query()
            ->where('is_active', 1)
            ->get()
            ->first(function ($item) use ($nilaiPersentase) {
                return $nilaiPersentase > (float) $item->persentase_awal
                    && $nilaiPersentase <= (float) $item->persentase_akhir;
            });

        /*
        |--------------------------------------------------------------------------
        | HELPER FORMAT DURATION
        |--------------------------------------------------------------------------
        */

        $formatDuration = function ($seconds) {
            if (!$seconds || $seconds <= 0) {
                return '-';
            }

            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;

            $parts = [];

            if ($hours > 0) {
                $parts[] = $hours . ' Jam';
            }
            if ($minutes > 0) {
                $parts[] = $minutes . ' Menit';
            }
            if ($hours === 0 && $minutes === 0) {
                $parts[] = $remainingSeconds . ' Detik';
            }

            return implode(', ', $parts);
        };
   

        /*
        |--------------------------------------------------------------------------
        | HELPER CALCULATE AVERAGE
        |--------------------------------------------------------------------------
        */

        $calculateAverage = function (
            $model,
            $startColumn,
            $endColumn,
            $callback = null
        ) {

            $query = $model::query();

            if ($callback) {
                $callback($query);
            }

            $data = $query
                ->whereNotNull($startColumn)
                ->whereNotNull($endColumn)
                ->get([$startColumn, $endColumn]);

            if ($data->isEmpty()) {
                return 0;
            }

            $totalSeconds = $data->sum(function ($item) use ($startColumn, $endColumn) {

                return Carbon::parse($item->$endColumn)
                    ->diffInSeconds(Carbon::parse($item->$startColumn));
            });

            return $totalSeconds / $data->count();
        };

        /*
        |--------------------------------------------------------------------------
        | AVG TIME
        |--------------------------------------------------------------------------
        */

        $avgTicketSampling = $formatDuration(
            $calculateAverage(
                TicketSampling::class,
                'request_time',
                'solve_time',
                function ($query) use ($tahunSekarang, $numMonth) {

                    $query->where('is_active', true)
                        ->where('status', 'DONE')
                        ->whereYear('request_time', $tahunSekarang)
                        ->whereMonth('request_time', $numMonth);
                }
            )
        );

        $avgSP = $formatDuration(
            $calculateAverage(
                SamplingPlan::class,
                'created_at',
                'timestamp_jadwal',
                function ($query) use ($tahunSekarang, $numMonth) {

                    $query->where('is_active', true)
                        ->where('status', 1)
                        ->whereYear('created_at', $tahunSekarang)
                        ->whereMonth('created_at', $numMonth);
                }
            )
        );

        $avgApproval = $formatDuration(
            $calculateAverage(
                SamplingPlan::class,
                'timestamp_jadwal',
                'approved_at',
                function ($query) use ($tahunSekarang, $numMonth) {

                    $query->where('is_active', true)
                        ->where('status', 1)
                        ->where('is_approved', 1)
                        ->whereNotNull('timestamp_jadwal')
                        ->whereYear('approved_at', $tahunSekarang)
                        ->whereMonth('approved_at', $numMonth);
                }
            )
        );

        /*
        |--------------------------------------------------------------------------
        | SUMMARY CARD
        |--------------------------------------------------------------------------
        */

        $summaryCard = [
            [
                'title' => 'Status Pencapaian Jadwal',
                'value' => number_format($nilaiPersentase, 2) . '%',
                'accent' => $matched->color ?? '#217245',
                'badge' => $matched->keterangan ?? null,
                'icon' => '📈',
                'link' => null,
            ],
            [
                'title' => 'Request SP Belum Dijadwalkan',
                'value' => $spNormal,
                'accent' => '#185ABC',
                'icon' => '📄',
                'link' => '/sampling/jadwal/request-sampling-plan',
            ],
            [
                'title' => 'Request SP Revisi Belum Dijadwalkan',
                'value' => $spRevisi,
                'accent' => '#F58A00',
                'icon' => '🔁',
                'link' => '/sampling/jadwal/request-sampling-plan-revisi',
            ],
            [
                'title' => 'Ticketing Sampling Belum Diproses',
                'value' => $ticketSampling,
                'accent' => '#B81A35',
                'icon' => '🎫',
                'link' => '/request/ticket-sampling',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | AVG CARD
        |--------------------------------------------------------------------------
        */

        $avgCards = [
            [
                'title' => 'Rata-rata Waktu Penyelesaian Ticket Sampling',
                'value' => $avgTicketSampling,
                'accent' => '#185ABC',
                'icon' => '⏱️',
            ],
            [
                'title' => 'Rata-rata Waktu Penyelesaian Request SP',
                'value' => $avgSP,
                'accent' => '#F58A00',
                'icon' => '🕒',
            ],
            [
                'title' => 'Rata-rata Waktu Approval SP',
                'value' => $avgApproval,
                'accent' => '#16A34A',
                'icon' => '✅',
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | TABLE MOCK DATA
        |--------------------------------------------------------------------------
        */

        $tableRows = SamplingPlan::query()
        ->selectRaw("
            petugas_jadwal as admin_sampling,

            COUNT(
                CASE
                    WHEN no_document NOT REGEXP 'R[0-9]+$'
                    THEN 1
                END
            ) as requestSp,

            COUNT(
                CASE
                    WHEN no_document REGEXP 'R[0-9]+$'
                    THEN 1
                END
            ) as spRevisi,

            AVG(
                TIMESTAMPDIFF(
                    SECOND,
                    created_at,
                    timestamp_jadwal
                )
            ) as avgSeconds
        ")
        ->where('is_active', true)
        ->where('status', 1)
        ->whereNotNull('timestamp_jadwal')
        ->whereYear('created_at', $tahunSekarang)
        ->whereMonth('created_at', $numMonth)
        ->groupBy('petugas_jadwal')
        ->orderBy('avgSeconds', 'desc')
        ->get()
        ->map(function ($item) use ($formatDuration) {

            return [
                'admin_sampling' => $item->admin_sampling,
                'requestSp' => (int) $item->requestSp,
                'spRevisi' => (int) $item->spRevisi,
                'average' => $formatDuration($item->avgSeconds),
            ];
        });

        return response()->json([
            'summaryCard' => $summaryCard,
            'avgCards' => $avgCards,
            'tableRows' => $tableRows
        ], 200);
    }

    public function getChart(Request $request)
    {
        $tahun = $request->year ?? Carbon::now()->year;
        
        $chartLabels = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'Mei',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Agu',
            9 => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $samplingPlans = SamplingPlan::query()
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as total')
            ->where('is_active', true)
            ->where('status', 1)
            ->whereYear('created_at', $tahun)
            ->groupByRaw('MONTH(created_at)')
            ->pluck('total', 'month');

        $chartValues = collect(range(1, 12))
            ->map(function ($month) use ($chartLabels, $samplingPlans) {

                return [
                    'month' => $chartLabels[$month],
                    'value' => (int) ($samplingPlans[$month] ?? 0),
                ];
            })
            ->values();

        return response()->json([
            'data' => $chartValues
        ], 200);
    }

    private $bulan = [
        'Januari'   => '01', 'Februari' => '02', 'Maret'    => '03', 'April'    => '04',
        'Mei'       => '05', 'Juni'     => '06', 'Juli'     => '07', 'Agustus'  => '08',
        'September' => '09', 'Oktober'  => '10', 'November' => '11', 'Desember' => '12',
    ];

    private $months = [
        "Jan" => "01",
        "Feb" => "02",
        "Mar" => "03",
        "Apr" => "04",
        "May" => "05",
        "Jun" => "06",
        "Jul" => "07",
        "Aug" => "08",
        "Sep" => "09",
        "Oct" => "10",
        "Nov" => "11",
        "Dec" => "12",
    ];

}