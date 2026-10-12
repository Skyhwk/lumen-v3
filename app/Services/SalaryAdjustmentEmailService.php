<?php

namespace App\Services;

use App\Helpers\FrontendPublicUrl;
use App\Models\SalaryAdjustmentApprovalToken;
use App\Models\SalaryAdjustmentRequest;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SalaryAdjustmentEmailService
{
    public const ROLE_IBU = 'ibu';
    public const ROLE_BAPAK = 'bapak';

    public const LABEL_APPROVAL = 'Waiting Approval';
    public const LABEL_APPROVAL_FINAL = 'Waiting Approval Final';

    /**
     * @param array{email?: string, persist_token?: bool, throw?: bool} $options
     */
    public function sendForRole(SalaryAdjustmentRequest $request, string $role, string $sender, array $options = []): bool
    {
        $emailOverride = trim((string) ($options['email'] ?? ''));
        $persistToken = array_key_exists('persist_token', $options)
            ? (bool) $options['persist_token']
            : true;
        $throw = (bool) ($options['throw'] ?? false);

        $emailTo = $emailOverride !== '' ? $emailOverride : $this->resolveEmail($role);
        if ($emailTo === '') {
            Log::warning('Salary adjustment approval email skipped: target email empty', [
                'request_id' => $request->id,
                'role' => $role,
            ]);

            if ($throw) {
                throw new \RuntimeException('target_email atau EMAIL_DIREKTUR belum diisi');
            }

            return false;
        }

        $approvalToken = null;
        if ($persistToken) {
            SalaryAdjustmentApprovalToken::where('request_id', $request->id)
                ->where('approver_role', $role)
                ->where('is_active', true)
                ->update(['is_active' => false, 'updated_at' => Carbon::now()]);

            $token = bin2hex(random_bytes(32));
            $approvalToken = SalaryAdjustmentApprovalToken::create([
                'request_id' => $request->id,
                'approver_role' => $role,
                'token' => $token,
                'email_to' => $emailTo,
                'is_active' => true,
                'created_by' => $sender,
            ]);
        } else {
            $token = 'preview-' . $request->id . '-' . $role;
        }

        $bundle = $this->normalizeBundleForView(
            (new SalaryAdjustmentEvaluationService())->buildBundle($request)
        );
        $documentService = new SalaryAdjustmentDocumentService();
        $pdf = null;

        try {
            $pdf = $documentService->generateSummaryPdf($bundle);
            $buttons = $this->buildDecisionButtons($token);
            $approverLabel = $this->approverLabel($role);
            $employeeName = $bundle['request']['nama_lengkap'] ?? 'Karyawan';
            $subject = 'Permohonan Penyesuaian Karyawan - ' . $approverLabel . ' - ' . $employeeName;
            $body = $this->renderApprovalBody($bundle, $role, $buttons);

            SendEmail::where('to', $emailTo)
                ->where('subject', $subject)
                ->where('body', $body)
                ->where('karyawan', $sender)
                ->where('attachments', [$pdf['full_path']])
                ->noReply()
                ->send();

            if ($approvalToken) {
                $approvalToken->email_sent_at = Carbon::now();
                $approvalToken->save();
            }

            return true;
        } catch (\Throwable $e) {
            Log::warning('Salary adjustment approval email failed', [
                'request_id' => $request->id,
                'role' => $role,
                'message' => $e->getMessage(),
            ]);

            if ($throw) {
                throw $e;
            }

            return false;
        } finally {
            if ($pdf) {
                $documentService->cleanup($pdf['batch_dir'] ?? null);
            }
        }
    }

    public function buildDecisionButtons(string $token): object
    {
        $encodedToken = rawurlencode($token);

        return (object) [
            'approve' => FrontendPublicUrl::build("private/decision/{$encodedToken}?decision=approve"),
            'reject' => FrontendPublicUrl::build("private/decision/{$encodedToken}?decision=reject"),
        ];
    }

    public function renderApprovalBody(array $bundle, string $role, object $buttons): string
    {
        return view('TemplateEmail.hrd.salary-adjustment-approval', [
            'bundle' => $bundle,
            'data' => $bundle['request'],
            'btn' => $buttons,
            'approverLabel' => $this->approverLabel($role),
            'approverRole' => $role,
        ])->render();
    }

    public function previewApprovalHtml(SalaryAdjustmentRequest $request, string $role): string
    {
        $bundle = $this->normalizeBundleForView(
            (new SalaryAdjustmentEvaluationService())->buildBundle($request)
        );
        $buttons = $this->buildDecisionButtons('preview-' . $request->id . '-' . $role);

        return $this->renderApprovalBody($bundle, $role, $buttons);
    }

    public function approverLabel(string $role): string
    {
        return self::actorLabel($role);
    }

    public static function actorLabel(string $role): string
    {
        return $role === self::ROLE_IBU ? self::LABEL_APPROVAL : self::LABEL_APPROVAL_FINAL;
    }

    public function resolveRecipient(string $role): string
    {
        return $this->resolveEmail($role);
    }

    private function resolveEmail(string $role): string
    {
        if ($role === self::ROLE_IBU) {
            return trim((string) env('EMAIL_DIREKTUR_IBU', ''));
        }

        return trim((string) env('EMAIL_DIREKTUR_BAPAK', ''));
    }

    private function normalizeBundleForView(array $bundle): array
    {
        $normalized = json_decode(json_encode($bundle), true);

        return is_array($normalized) ? $normalized : $bundle;
    }
}
