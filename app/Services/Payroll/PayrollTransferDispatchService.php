<?php

namespace App\Services\Payroll;

use App\Models\Payroll;
use App\Models\PayrollHeader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Setelah finance approve payroll: enqueue header, panggil Python /lumen/invoke.
 *
 * Payload ke Python (minimal):
 * - controller, function, async
 *
 * Python → POST Lumen /api/route dengan X-Slice saja (body {}).
 * Method slice memproses antrian via PayrollDisbursementPendingQueue.
 */
class PayrollTransferDispatchService
{
    public function dispatchForHeaderId(int $payrollHeaderId): array
    {
        if (!config('payroll_disbursement.enabled')) {
            return ['dispatched' => false, 'reason' => 'disabled'];
        }

        $header = PayrollHeader::query()
            ->where('id', $payrollHeaderId)
            ->where('is_active', true)
            ->first();

        if (!$header) {
            return ['dispatched' => false, 'reason' => 'header_not_found'];
        }

        return $this->dispatchForHeader($header);
    }

    public function dispatchForHeader(PayrollHeader $header): array
    {
        if (!config('payroll_disbursement.enabled')) {
            return ['dispatched' => false, 'reason' => 'disabled'];
        }

        $hasRows = Payroll::query()
            ->where('payroll_header_id', $header->id)
            ->where('is_active', true)
            ->exists();

        if (!$hasRows) {
            return ['dispatched' => false, 'reason' => 'no_payroll_rows'];
        }

        PayrollDisbursementPendingQueue::enqueue((int) $header->id);

        $controller = (string) config('payroll_disbursement.invoke_controller');
        $function = (string) config('payroll_disbursement.invoke_function');
        if ($controller === '' || $function === '') {
            Log::warning('payroll_disbursement.dispatch_skipped', [
                'payroll_header_id' => $header->id,
                'reason' => 'missing_invoke_target',
            ]);

            return ['dispatched' => false, 'reason' => 'missing_invoke_target'];
        }

        $payload = [
            'controller' => $controller,
            'function' => $function,
            'async' => (bool) config('payroll_disbursement.invoke_async', true),
        ];

        $url = (string) config('payroll_disbursement.python_invoke_url');

        $http = Http::timeout((int) config('payroll_disbursement.http_timeout_seconds'))
            ->acceptJson()
            ->asJson();

        $invokeSecret = trim((string) config('payroll_disbursement.invoke_secret'));
        if ($invokeSecret !== '') {
            $http = $http->withHeaders(['X-Worker-Secret' => $invokeSecret]);
        }

        try {
            $response = $http->post($url, $payload);

            if (!$response->successful()) {
                Log::error('payroll_disbursement.python_failed', [
                    'payroll_header_id' => $header->id,
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [
                    'dispatched' => false,
                    'reason' => 'python_http_error',
                    'status' => $response->status(),
                ];
            }

            Log::info('payroll_disbursement.dispatched', [
                'payroll_header_id' => $header->id,
                'url' => $url,
                'invoke' => $payload,
            ]);

            return [
                'dispatched' => true,
                'python_status' => $response->status(),
                'python_body' => $response->json(),
            ];
        } catch (\Throwable $exception) {
            Log::error('payroll_disbursement.python_exception', [
                'payroll_header_id' => $header->id,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            return [
                'dispatched' => false,
                'reason' => 'python_exception',
                'message' => $exception->getMessage(),
            ];
        }
    }
}
