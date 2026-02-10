<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FeeSalesMonthly;
use App\Services\FeeSalesMonthly2;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

class CalculateFeeSales extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'calculatefee:run {periode?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate fee sales';

    /**
     * @var FeeSalesMonthly
     */
    private $feeSalesMonthly;
    /**
     * @var FeeSalesMonthly2
     */
    private $feeSalesMonthly2;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(FeeSalesMonthly $feeSalesMonthly, FeeSalesMonthly2 $feeSalesMonthly2)
    {
        parent::__construct();
        $this->feeSalesMonthly = $feeSalesMonthly;
        $this->feeSalesMonthly2 = $feeSalesMonthly2;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $periode = $this->argument('periode') ?? NULL;

        // Validasi format periode (wajib tahun-bulan, contoh 2026-01)
        if ($periode !== NULL) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode)) {
                $this->error("Format periode tidak valid. Gunakan format tahun-bulan, contoh: 2026-01");
                return 1;
            }
        }

        $this->feeSalesMonthly->run($periode);
        printf("[CalculateFeeSales] [%s] Calculate Achievement Done \n", date('Y-m-d H:i:s'));
        printf("[CalculateFeeSales] [%s] sleep 3 detik untuk lanjut hitung kredit saldo fee \n", date('Y-m-d H:i:s'));
        sleep(3);
        printf("[CalculateFeeSales] [%s] Start Calculate Fee Sales Monthly \n", date('Y-m-d H:i:s'));
        $this->feeSalesMonthly2->run();
        printf("[CalculateFeeSales] [%s] Calculate Fee Sales Monthly Done \n", date('Y-m-d H:i:s'));
    }
}