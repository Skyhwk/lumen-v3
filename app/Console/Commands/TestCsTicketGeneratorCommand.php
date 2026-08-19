<?php

namespace App\Console\Commands;

use App\Services\CustomerServiceTicketNumberGenerator;
use Illuminate\Console\Command;
use ReflectionMethod;

class TestCsTicketGeneratorCommand extends Command
{
    protected $signature = 'cs:test-ticket-generator {--count=1000 : Jumlah kode yang di-generate}';

    protected $description = 'Load test generator nomor ticket CS (format, collision in-memory, opsional DB)';

    public function handle(): int
    {
        $count = max((int) $this->option('count'), 1);
        $method = new ReflectionMethod(CustomerServiceTicketNumberGenerator::class, 'buildRandomCode');
        $method->setAccessible(true);

        $codes = [];
        $invalid = 0;
        $duplicates = 0;

        $startedAt = microtime(true);
        for ($i = 0; $i < $count; $i++) {
            $code = $method->invoke(null);
            if (!preg_match('/^[A-Z0-9]{8}$/', $code)) {
                $invalid++;
                continue;
            }

            if (isset($codes[$code])) {
                $duplicates++;
            } else {
                $codes[$code] = true;
            }
        }
        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

        $this->info("In-memory load test ({$count} generate):");
        $this->line("- Durasi: {$elapsedMs} ms");
        $this->line('- Format invalid: ' . $invalid);
        $this->line('- Duplikat in-memory: ' . $duplicates);
        $this->line('- Unik in-memory: ' . count($codes));

        if ($invalid > 0 || $duplicates > 0) {
            $this->warn('Terdapat invalid/duplikat in-memory — periksa generator.');
            return 1;
        }

        try {
            $dbStartedAt = microtime(true);
            $dbCode = CustomerServiceTicketNumberGenerator::generate();
            $dbElapsedMs = round((microtime(true) - $dbStartedAt) * 1000, 2);
            $this->info("DB uniqueness check OK — sample: {$dbCode} ({$dbElapsedMs} ms)");
        } catch (\Throwable $exception) {
            $this->warn('DB check dilewati: ' . $exception->getMessage());
        }

        $this->info('Load test generator selesai.');
        return 0;
    }
}
