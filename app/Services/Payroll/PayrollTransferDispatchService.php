<?php

namespace App\Services\Payroll;

use App\Models\MasterKaryawan;
use App\Models\Payroll;
use App\Models\PayrollHeader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Memanggil worker Python setelah finance mengisi tanggal transfer.
 *
 * Payload ke Python (JSON):
 * - payroll_header_id, no_document, periode_payroll, tgl_transfer
 * - callback_url, callback_token
 * - items[]: payroll_id, id_karyawan, nik_karyawan, email, take_home_pay, nama
 *
 * Python selesai proses → POST callback_url dengan payroll_header_id + token.
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

        $callbackToken = (string) config('payroll_disbursement.callback_token');
        if ($callbackToken === '') {
            Log::warning('payroll_disbursement.dispatch_skipped', [
                'payroll_header_id' => $header->id,
                'reason' => 'missing_callback_token',
            ]);

            return ['dispatched' => false, 'reason' => 'missing_callback_token'];
        }

        $payrolls = Payroll::query()
            ->where('payroll_header_id', $header->id)
            ->where('is_active', true)
            ->get();

        if ($payrolls->isEmpty()) {
            return ['dispatched' => false, 'reason' => 'no_payroll_rows'];
        }

        $karyawanMap = MasterKaryawan::query()
            ->whereIn('id', $payrolls->pluck('id_karyawan')->filter()->unique()->values())
            ->get(['id', 'nik_karyawan', 'nama_lengkap', 'email'])
            ->keyBy('id');

        $items = $payrolls->map(function (Payroll $row) use ($karyawanMap) {
            $karyawan = $karyawanMap->get((int) $row->id_karyawan);

            return [
                'payroll_id' => (int) $row->id,
                'id_karyawan' => (int) ($row->id_karyawan ?: optional($karyawan)->id),
                'nik_karyawan' => $row->nik_karyawan ?: optional($karyawan)->nik_karyawan,
                'nama' => $row->karyawan ?: optional($karyawan)->nama_lengkap,
                'email' => optional($karyawan)->email,
                'take_home_pay' => (float) $row->take_home_pay,
                'status' => $row->status,
            ];
        })->values()->all();

        $payload = [
            'payroll_header_id' => (int) $header->id,
            'no_document' => $header->no_document,
            'periode_payroll' => $header->periode_payroll,
            'tgl_transfer' => $header->tgl_transfer,
            'callback_url' => $this->resolveCallbackUrl(),
            'callback_token' => $callbackToken,
            'items' => $items,
        ];

        $url = config('payroll_disbursement.python_base_url')
            . config('payroll_disbursement.python_disburse_path');

        try {
            $response = Http::timeout((int) config('payroll_disbursement.http_timeout_seconds'))
                ->acceptJson()
                ->asJson()
                ->post($url, $payload);

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
                'items' => count($items),
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

    protected function resolveCallbackUrl(): string
    {
        $configured = trim((string) config('payroll_disbursement.callback_url'));
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $public = rtrim((string) config('greatday.web_public', env('WEB_PUBLIC', '')), '/');

        return $public . '/api/payroll-transfer/complete';
    }
}
