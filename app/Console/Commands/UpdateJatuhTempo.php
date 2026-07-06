<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateJatuhTempo extends Command
{
    protected $signature = 'update-jatuh-tempo-invoice:run';

    protected $description = 'Update tanggal jatuh tempo pada invoice yang memiliki tanggal invoice lebih besar dari tanggal hari ini, namun tanggal jatuh temponya lebih kecil dari tanggal invoice.';

    public function handle()
    {
        printf("\n[UpdateJatuhTempo] [%s] Start", Carbon::now());

        try {
            $query = Invoice::whereDate('tgl_invoice', '>=', Carbon::now()->toDateString())
                ->whereColumn('tgl_jatuh_tempo', '<', 'tgl_invoice')
                ->whereNotNull('periode');

            $invoices = $query->get(['id', 'no_invoice', 'tgl_jatuh_tempo', 'tgl_invoice']);
            $totalData = $invoices->count();
            printf("\n[UpdateJatuhTempo] [%s] Total matching data: %d", Carbon::now(), $totalData);

            if ($totalData > 0) {
                foreach ($invoices as $invoice) {
                    $logMsg = sprintf(
                        "Updating No. Invoice: %s | Old tgl_jatuh_tempo: %s | New tgl_jatuh_tempo: %s",
                        $invoice->no_invoice,
                        $invoice->tgl_jatuh_tempo,
                        $invoice->tgl_invoice
                    );
                    Log::channel('update_jatuh_tempo')->info($logMsg);
                    printf("\n[UpdateJatuhTempo] %s", $logMsg);
                }

                $updatedCount = $query->update([
                    'tgl_jatuh_tempo' => DB::raw('tgl_invoice')
                ]);

                printf("\n[UpdateJatuhTempo] Successfully updated %d invoices.", $updatedCount);
            } else {
                printf("\n[UpdateJatuhTempo] No invoices need to be updated.");
            }

        } catch (\Throwable $th) {
            Log::error('[UpdateJatuhTempo] Error: ' . $th->getMessage());
            printf("\n[UpdateJatuhTempo] Error: %s", $th->getMessage());
        }
        printf("\n[UpdateJatuhTempo] [%s] End\n", Carbon::now());
    }

}
