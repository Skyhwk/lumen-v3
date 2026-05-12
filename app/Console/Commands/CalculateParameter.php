<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\OrderDetail;
use App\Models\Parameter;
use Carbon\Carbon;

class CalculateParameter extends Command
{
    protected $signature = 'calculate:parameter';
    protected $description = 'Calculate parameter';

    public function handle()
    {
        $startTime = microtime(true);

        $this->output->newLine();
        $this->info("STARTING PARAMETER CALCULATION  ");
        $this->info("Time: " . Carbon::now()->toDateTimeString());

        /*
        |--------------------------------------------------------------------------
        | Ambil semua parameter aktif
        |--------------------------------------------------------------------------
        */
        $parameters = Parameter::where('is_active', 1)
            ->select('id', 'nama_lab', 'nama_kategori')
            ->get();

        if ($parameters->isEmpty()) {
            $this->error('Tidak ada parameter aktif');
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Mapping parameter
        |--------------------------------------------------------------------------
        */
        $parameterMap = [];

        foreach ($parameters as $param) {
            $parameterMap[] = [
                'id' => $param->id,
                'nama_lab' => $param->nama_lab,
                'nama_kategori' => $param->nama_kategori,
                'search' => $param->id . ';' . $param->nama_lab,
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Ambil seluruh order detail
        |--------------------------------------------------------------------------
        */
        $this->info('Mengambil seluruh order detail...');

        $orderDetails = OrderDetail::where('is_active', 1)
            ->whereYear('tanggal_sampling', '>=', Carbon::now()->year)
            ->select(
                'id',
                'parameter',
                DB::raw('MONTH(tanggal_sampling) as month'),
                DB::raw('YEAR(tanggal_sampling) as year')
            )
            ->get();

        $this->info('Total order detail: ' . number_format($orderDetails->count()));

        /*
        |--------------------------------------------------------------------------
        | Proses di memory
        |--------------------------------------------------------------------------
        */
        $summary = [];
        $processed = 0;

        foreach ($orderDetails as $detail) {

            $processed++;

            if ($processed % 5000 == 0) {
                $this->info('Processed : ' . number_format($processed));
            }

            $tahun_bulan = $detail->year . '-' . str_pad($detail->month, 2, '0', STR_PAD_LEFT);

            foreach ($parameterMap as $param) {

                if (
                    !empty($detail->parameter) &&
                    str_contains($detail->parameter, $param['search'])
                ) {

                    $key = $param['id'] . '_' . $tahun_bulan;

                    if (!isset($summary[$key])) {

                        $summary[$key] = [
                            'id_parameter' => $param['id'],
                            'nama_parameter' => $param['nama_lab'],
                            'jumlah_order' => 0,
                            'nama_kategori' => $param['nama_kategori'],
                            'tahun_bulan' => $tahun_bulan,
                            'bulan' => Carbon::createFromDate(
                                null,
                                $detail->month,
                                1
                            )->locale('id')->isoFormat('MMMM'),
                            'tahun' => $detail->year,
                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ];
                    }

                    $summary[$key]['jumlah_order']++;
                    $summary[$key]['updated_at'] = Carbon::now();
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Flatten array
        |--------------------------------------------------------------------------
        */
        $insertData = array_values($summary);

        /*
        |--------------------------------------------------------------------------
        | UPSERT
        | Hindari truncate agar tabel tidak pernah kosong
        |--------------------------------------------------------------------------
        */
        if (!empty($insertData)) {

            try {

                foreach (array_chunk($insertData, 1000) as $chunk) {

                    DB::table('summary_parameter')->upsert(
                        $chunk,
                        [
                            'id_parameter',
                            'tahun_bulan'
                        ],
                        [
                            'nama_parameter',
                            'jumlah_order',
                            'nama_kategori',
                            'bulan',
                            'tahun',
                            'updated_at'
                        ]
                    );
                }

                $this->info('SummaryParameter berhasil di-upsert');
                $this->info('Total data: ' . number_format(count($insertData)));

            } catch (\Throwable $th) {

                $this->error(
                    'SummaryParameter gagal dijalankan: ' .
                    $th->getMessage()
                );
            }

        } else {

            $this->error(
                'SummaryParameter dijalankan, namun tidak ada data.'
            );
        }

        $endTime = microtime(true);
        $duration = $endTime - $startTime;

        $this->output->newLine();

        $this->info("========================================================================");
        $this->info("PARAMETER CALCULATED SUCCESSFULLY  ");
        $seconds = (int)$duration;
        $minutes = (int)($seconds / 60);
        $hours = (int)($minutes / 60);

        $remainingSeconds = $seconds % 60;
        $remainingMinutes = $minutes % 60;

        $executionTimeMsg = "Total Execution Time: ";

        if ($hours > 0) {
            $executionTimeMsg .= "{$hours} Jam ";
        }
        if ($minutes > 0) {
            $executionTimeMsg .= "{$remainingMinutes} Menit ";
        }
        $executionTimeMsg .= "{$remainingSeconds} Detik";

        $this->info($executionTimeMsg);
        $this->info("========================================================================");
    }
}