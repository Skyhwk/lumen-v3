<?php

namespace App\Http\Controllers\api;

use App\Http\Controllers\Controller;
use App\Models\EmailHistory;
use App\Services\SendEmail;
use Illuminate\Http\Request;
use Yajra\Datatables\Datatables;

class EmailHistoryController extends Controller
{
    public function index()
    {
        $data = EmailHistory::orderBy('id', 'desc');
        return Datatables::of($data)
            ->filterColumn('created_at', function ($query, $keyword) {
                $query->where('created_at', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_to', function ($query, $keyword) {
                $query->where('email_to', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_cc', function ($query, $keyword) {
                $query->where('email_cc', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_bcc', function ($query, $keyword) {
                $query->where('email_bcc', 'like', '%' . $keyword . '%');
            })
            ->filterColumn('email_subject', function ($query, $keyword) {
                $query->where('email_subject', 'like', '%' . $keyword . '%');
            })
        ->make(true);
    }

    public function getDetailData(Request $request)
    {
        $data = EmailHistory::where('id', $request->id)->first();

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }

        $resolved = $this->resolveEmailBody($data->email_body);
        $data->email_content = $resolved['content'];
        $data->body_source = $resolved['source'];

        return response()->json([
            'status' => true,
            'data' => $data,
        ], 200);
    }

    public function resendEmail(Request $request)
    {
        $history = EmailHistory::where('id', $request->input('id'))->first();

        if (!$history) {
            return response()->json([
                'status' => false,
                'message' => 'Data email history tidak ditemukan',
            ], 404);
        }

        $emailTo = $this->normalizeRecipient($request->input('email_to', $history->email_to));
        if ($emailTo === '') {
            return response()->json([
                'status' => false,
                'message' => 'Email tujuan wajib diisi',
            ], 422);
        }

        $resolved = $this->resolveEmailBody($history->email_body);
        $body = $resolved['content'];
        if (trim($body) === '') {
            return response()->json([
                'status' => false,
                'message' => 'Isi body email tidak ditemukan (file .txt maupun inline).',
            ], 404);
        }

        $cc = $this->parseEmailList($request->input('email_cc', $history->email_cc));
        $bcc = $this->parseEmailList($request->input('email_bcc', $history->email_bcc));
        $sender = trim((string) ($this->karyawan ?? 'Email History Resend'));

        try {
            $mail = SendEmail::where('to', $emailTo)
                ->where('subject', $history->email_subject)
                ->where('body', $body)
                ->where('karyawan', $sender);

            if (!empty($cc)) {
                $mail = $mail->where('cc', $cc);
            }

            if (!empty($bcc)) {
                $mail = $mail->where('bcc', $bcc);
            }

            $this->applySenderFromAddress($mail, $history->email_from);
            $mail->send();

            return response()->json([
                'status' => true,
                'message' => 'Email berhasil dikirim ulang ke ' . $emailTo,
                'body_source' => $resolved['source'],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => 'Gagal mengirim ulang email: ' . $th->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve email body from history record.
     * Supports two storage patterns:
     * 1) New: email_body = "1786110031273.txt" (file in storage/repository/email_history/)
     * 2) Legacy: email_body = raw HTML string stored directly in DB column
     */
    private function resolveEmailBody($emailBody): array
    {
        if ($emailBody === null || $emailBody === '') {
            return ['content' => '', 'source' => 'empty'];
        }

        $value = (string) $emailBody;

        if ($this->isEmailBodyFilename($value)) {
            $filePath = storage_path('repository/email_history/' . basename($value));
            if (is_file($filePath)) {
                return [
                    'content' => file_get_contents($filePath),
                    'source' => 'file',
                ];
            }
        }

        // Legacy rows keep full HTML/content inline in email_body column.
        if (trim($value) !== '') {
            return [
                'content' => $value,
                'source' => 'inline',
            ];
        }

        return ['content' => '', 'source' => 'empty'];
    }

    private function isEmailBodyFilename(string $value): bool
    {
        $base = basename($value);

        return $base === $value && (bool) preg_match('/^\d+\.txt$/i', $base);
    }

    private function readEmailBody($emailBody): string
    {
        return $this->resolveEmailBody($emailBody)['content'];
    }

    private function normalizeRecipient($value): string
    {
        if (is_array($value)) {
            $emails = array_filter(array_map('trim', $value));
            return implode(',', $emails);
        }

        return trim((string) $value);
    }

    private function parseEmailList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map('trim', $value)));
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter(array_map('trim', $decoded)));
            }

            return array_values(array_filter(array_map('trim', preg_split('/[,;]+/', $value))));
        }

        return [];
    }

    private function applySenderFromAddress(SendEmail $mail, ?string $emailFrom): SendEmail
    {
        $email = strtolower(trim((string) $emailFrom));
        $map = [
            strtolower((string) env('MAIL_ADMSALES_USERNAME', '')) => 'fromAdmsales',
            strtolower((string) env('MAIL_SALES_USERNAME', '')) => 'fromSales',
            strtolower((string) env('MAIL_FINANCE_USERNAME', '')) => 'fromFinance',
            strtolower((string) env('MAIL_TC_USERNAME', '')) => 'fromTc',
            strtolower((string) env('MAIL_PROMO_USERNAME', '')) => 'fromPromoSales',
            strtolower((string) env('MAIL_INFO_USERNAME', '')) => 'fromInfoIntilab',
            strtolower((string) env('MAIL_LHP_USERNAME', '')) => 'fromLhp',
            strtolower((string) env('MAIL_NOREPLY_USERNAME', '')) => 'noReply',
            'no-reply@intilab.com' => 'noReply',
            'e-lhp@intilab.com' => 'fromLhp',
        ];

        $method = $map[$email] ?? null;

        switch ($method) {
            case 'fromAdmsales':
                return $mail->fromAdmsales();
            case 'fromSales':
                return $mail->fromSales();
            case 'fromFinance':
                return $mail->fromFinance();
            case 'fromTc':
                return $mail->fromTc();
            case 'fromPromoSales':
                return $mail->fromPromoSales();
            case 'fromInfoIntilab':
                return $mail->fromInfoIntilab();
            case 'fromLhp':
                return $mail->fromLhp();
            default:
                return $mail->noReply();
        }
    }
}