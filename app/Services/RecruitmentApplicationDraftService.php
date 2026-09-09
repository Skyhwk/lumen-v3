<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RecruitmentApplicationDraftService
{
    public function normalizeEmail(?string $email): ?string
    {
        $email = strtolower(trim((string) $email));

        return $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    public function normalizePhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone);
        if ($phone === '') {
            return null;
        }

        if (strpos($phone, '62') === 0) {
            $phone = '0' . ltrim(substr($phone, 2), '0');
        } elseif (strpos($phone, '0') !== 0) {
            $phone = '0' . $phone;
        }

        return preg_match('/^08\d{8,11}$/', $phone) ? $phone : null;
    }

    public function save(array $payload): array
    {
        $now = Carbon::now();
        $draftToken = trim((string) ($payload['draft_token'] ?? ''));
        if ($draftToken === '') {
            $draftToken = (string) Str::uuid();
        }

        $formData = $payload['form_data'] ?? null;
        if (is_string($formData)) {
            $decoded = json_decode($formData, true);
            $formData = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        $fields = is_array($formData) ? ($formData['fields'] ?? []) : [];
        $email = $this->normalizeEmail($payload['email'] ?? ($fields['email'] ?? null));
        $phone = $this->normalizePhone(
            $payload['no_telepon']
            ?? ($fields['no_telepon'] ?? ($fields['no_hp'] ?? null))
        );

        $currentStep = max(1, (int) ($payload['current_step'] ?? 1));
        $maxStep = max($currentStep, (int) ($payload['max_step_reached'] ?? $currentStep));
        $personnelRequestId = $payload['personnel_request_id'] ?? null;
        $noRequest = trim((string) ($payload['no_request'] ?? ''));

        $existing = DB::table('recruitment_application_drafts')
            ->where('draft_token', $draftToken)
            ->first();

        if (!$existing && ($email || $phone)) {
            $existing = DB::table('recruitment_application_drafts')
                ->where(function ($query) use ($email, $phone) {
                    if ($email) {
                        $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                    }
                    if ($phone) {
                        $query->orWhere('no_telepon', $phone);
                    }
                })
                ->orderByDesc('last_activity_at')
                ->first();
        }

        if ($existing && $existing->draft_token !== $draftToken) {
            $draftToken = $existing->draft_token;
        }

        $row = [
            'personnel_request_id' => $personnelRequestId ?: ($existing->personnel_request_id ?? null),
            'no_request' => $noRequest !== '' ? $noRequest : ($existing->no_request ?? null),
            'email' => $email ?: ($existing->email ?? null),
            'no_telepon' => $phone ?: ($existing->no_telepon ?? null),
            'current_step' => $currentStep,
            'max_step_reached' => max($maxStep, (int) ($existing->max_step_reached ?? 1)),
            'form_data' => $formData !== null ? json_encode($formData) : ($existing->form_data ?? null),
            'last_activity_at' => $now,
            'updated_at' => $now,
        ];

        if ($existing) {
            DB::table('recruitment_application_drafts')
                ->where('id', $existing->id)
                ->update($row);
            $id = (int) $existing->id;
        } else {
            $row['draft_token'] = $draftToken;
            $row['started_at'] = $now;
            $row['created_at'] = $now;
            $id = (int) DB::table('recruitment_application_drafts')->insertGetId($row);
        }

        $this->deleteDuplicates($id, $draftToken, $email, $phone);

        $saved = DB::table('recruitment_application_drafts')->where('id', $id)->first();

        return $this->formatDraft($saved);
    }

    public function get(array $payload): ?array
    {
        $draftToken = trim((string) ($payload['draft_token'] ?? ''));
        $email = $this->normalizeEmail($payload['email'] ?? null);
        $phone = $this->normalizePhone($payload['no_telepon'] ?? null);
        $noRequest = trim((string) ($payload['no_request'] ?? ''));

        if ($draftToken === '' && !$email && !$phone) {
            return null;
        }

        if ($draftToken !== '') {
            $query = DB::table('recruitment_application_drafts')->where('draft_token', $draftToken);
            if ($noRequest !== '') {
                $query->where('no_request', $noRequest);
            }
            $row = $query->first();
        } else {
            // Keep the email and phone lookup separate so MySQL can use the
            // corresponding composite index without sorting the entire draft table.
            $candidates = [];
            if ($email) {
                $candidates[] = $this->latestDraftByContact('email', $email, $noRequest);
            }
            if ($phone) {
                $candidates[] = $this->latestDraftByContact('no_telepon', $phone, $noRequest);
            }

            $candidates = array_values(array_filter($candidates));
            usort($candidates, function ($left, $right) {
                return strcmp((string) $right->last_activity_at, (string) $left->last_activity_at);
            });
            $row = $candidates[0] ?? null;
        }

        return $row ? $this->formatDraft($row) : null;
    }

    private function latestDraftByContact(string $column, string $value, string $noRequest)
    {
        $index = $column === 'email'
            ? 'recruitment_drafts_request_email_activity_idx'
            : 'recruitment_drafts_request_phone_activity_idx';
        $table = $noRequest !== ''
            ? DB::raw("recruitment_application_drafts FORCE INDEX ({$index})")
            : 'recruitment_application_drafts';

        $query = DB::table($table)->where($column, $value);
        if ($noRequest !== '') {
            $query->where('no_request', $noRequest);
        }

        return $query->orderByDesc('last_activity_at')->first();
    }

    public function deleteByContact(?string $email, ?string $phone, ?string $draftToken = null): void
    {
        $email = $this->normalizeEmail($email);
        $phone = $this->normalizePhone($phone);
        $draftToken = trim((string) $draftToken);

        if (!$email && !$phone && $draftToken === '') {
            return;
        }

        DB::table('recruitment_application_drafts')
            ->where(function ($query) use ($email, $phone, $draftToken) {
                if ($draftToken !== '') {
                    $query->orWhere('draft_token', $draftToken);
                }
                if ($email) {
                    $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }
                if ($phone) {
                    $query->orWhere('no_telepon', $phone);
                }
            })
            ->delete();
    }

    private function deleteDuplicates(int $keepId, string $keepToken, ?string $email, ?string $phone): void
    {
        if (!$email && !$phone) {
            return;
        }

        DB::table('recruitment_application_drafts')
            ->where('id', '!=', $keepId)
            ->where('draft_token', '!=', $keepToken)
            ->where(function ($query) use ($email, $phone) {
                if ($email) {
                    $query->orWhereRaw('LOWER(TRIM(email)) = ?', [$email]);
                }
                if ($phone) {
                    $query->orWhere('no_telepon', $phone);
                }
            })
            ->delete();
    }

    private function formatDraft($row): array
    {
        $formData = $row->form_data;
        if (is_string($formData)) {
            $decoded = json_decode($formData, true);
            $formData = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return [
            'draft_token' => $row->draft_token,
            'personnel_request_id' => $row->personnel_request_id,
            'no_request' => $row->no_request,
            'email' => $row->email,
            'no_telepon' => $row->no_telepon,
            'current_step' => (int) $row->current_step,
            'max_step_reached' => (int) $row->max_step_reached,
            'form_data' => $formData,
            'started_at' => $row->started_at,
            'last_activity_at' => $row->last_activity_at,
        ];
    }
}
