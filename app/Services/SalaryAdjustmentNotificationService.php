<?php

namespace App\Services;

use App\Models\AksesMenu;
use App\Models\MasterKaryawan;
use App\Models\SalaryAdjustmentRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalaryAdjustmentNotificationService
{
    public const URL_MANAGER = '/request/permohonan/penyesuaian-karyawan';

    public const URL_HRD = '/hrd/permohonan/permohonan-penyesuaian-karyawan';

    public const URL_FINANCE = '/finance/pengajuan-penyesuaian-gaji';

    public const URL_KONSELING = '/hrd/hris/konseling-karyawan';

    /** @var array<string, string> */
    private const MENU_NAMES_BY_URL = [
        self::URL_MANAGER => 'Penyesuaian Karyawan',
        self::URL_HRD => 'Permohonan Penyesuaian Karyawan',
        self::URL_FINANCE => 'Pengajuan Penyesuaian Karyawan',
        self::URL_KONSELING => 'Konseling Karyawan',
    ];

    public static function dispatch(
        int $requestId,
        string $action,
        ?string $fromStatus,
        string $toStatus,
        ?int $actorId = null,
        ?string $actorName = null
    ): void {
        try {
            $record = SalaryAdjustmentRequest::find($requestId);
            if (!$record || !$record->is_active) {
                return;
            }

            $label = self::recordLabel($record);
            $exclude = array_values(array_filter([(int) $actorId]));
            $actor = trim((string) $actorName);

            switch ($action) {
                case 'create':
                case 'store':
                    if ($record->request_type === EmployeeAdjustmentTypeRegistry::TYPE_MUTASI
                        && $record->status === SalaryAdjustmentWorkflowService::STATUS_WAITING_RECEIVER) {
                        self::notifyKaryawanIdsWithMenuAccess(
                            [(int) $record->receiver_manager_id],
                            self::URL_MANAGER,
                            'Permohonan Mutasi Menunggu Respons',
                            "Anda menerima permohonan mutasi karyawan {$label}. Mohon tinjau dan respons di tab Inbox Mutasi.",
                            $exclude
                        );
                    } else {
                        self::notifyMenuUsers(
                            self::URL_HRD,
                            'Penyesuaian Karyawan Baru',
                            "Permohonan penyesuaian Karyawan {$label} menunggu proses HRD.",
                            $exclude
                        );
                    }
                    break;

                case 'mutasi_notify_receiver':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->receiver_manager_id],
                        self::URL_MANAGER,
                        'Permohonan Mutasi Menunggu Respons',
                        "Anda menerima permohonan mutasi karyawan {$label}. Mohon tinjau di tab Inbox Mutasi.",
                        $exclude
                    );
                    break;

                case 'receiver_accept':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Mutasi Diterima Manager Penerima',
                        "Permohonan mutasi {$label} diterima manager penerima. Menunggu proses HRD.",
                        $exclude
                    );
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Mutasi Diterima',
                        "Permohonan mutasi {$label} telah diterima manager penerima.",
                        $exclude
                    );
                    break;

                case 'receiver_reject':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Mutasi Ditolak',
                        "Permohonan mutasi {$label} ditolak oleh manager penerima.",
                        $exclude
                    );
                    break;

                case 'process':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan Diproses',
                        "Permohonan penyesuaian Karyawan {$label} sedang diproses HRD.",
                        $exclude
                    );
                    break;

                case 'reject':
                    self::handleRejectNotification($record, $label, $fromStatus, $toStatus, $exclude, $actor);
                    break;

                case 'generate_assessment':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->employee_id],
                        self::URL_MANAGER,
                        'Assessment Penyesuaian Karyawan',
                        "Assessment Penyesuaian Karyawan untuk {$label} telah dibuat. Silakan selesaikan assessment.",
                        $exclude
                    );
                    break;

                case 'assessment_completed':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Assessment Selesai',
                        "Assessment Penyesuaian Karyawan {$label} telah selesai. Lanjutkan proses HRD.",
                        $exclude
                    );
                    break;

                case 'counseling_schedule':
                    self::notifyMenuUsers(
                        self::URL_KONSELING,
                        'Jadwal Konseling Baru',
                        "Konseling Penyesuaian Karyawan {$label} perlu dijadwalkan/dilaksanakan.",
                        $exclude
                    );
                    break;

                case 'counseling_complete':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Konseling Selesai — Evaluasi Final',
                        "Hasil konseling permohonan {$label} telah diinput. Mohon segera proses di tab Evaluasi Final.",
                        $exclude
                    );
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Konseling Selesai',
                        "Konseling permohonan {$label} telah selesai. HRD akan melanjutkan evaluasi final.",
                        $exclude
                    );
                    break;

                case 'final_eval_approve':
                    self::notifyMenuUsers(
                        self::URL_FINANCE,
                        'Review Finance Diperlukan',
                        "Evaluasi final Penyesuaian Karyawan {$label} menunggu review Finance.",
                        $exclude
                    );
                    break;

                case 'final_eval_reject':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan Ditolak',
                        "Permohonan penyesuaian Karyawan {$label} ditolak pada evaluasi final HRD.",
                        $exclude
                    );
                    break;

                case 'finance_approve':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Review Finance Disetujui',
                        "Permohonan penyesuaian Karyawan {$label} disetujui Finance. Menunggu Waiting Approval.",
                        $exclude
                    );
                    break;

                case 'finance_return':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Dikembalikan dari Finance',
                        "Permohonan penyesuaian Karyawan {$label} dikembalikan Finance ke HRD untuk banding.",
                        $exclude
                    );
                    break;

                case 'finance_reject':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Finance Menolak',
                        "Permohonan penyesuaian Karyawan {$label} ditolak Finance.",
                        $exclude
                    );
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan Ditolak',
                        "Permohonan penyesuaian Karyawan {$label} ditolak pada tahap Finance.",
                        $exclude
                    );
                    break;

                case 'hrd_appeal_approve':
                    self::notifyMenuUsers(
                        self::URL_FINANCE,
                        'Banding HRD — Review Ulang',
                        "HRD mengajukan banding untuk Penyesuaian Karyawan {$label}. Mohon review ulang Finance.",
                        $exclude
                    );
                    break;

                case 'hrd_appeal_reject':
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan Ditolak',
                        "Permohonan penyesuaian Karyawan {$label} ditolak setelah banding HRD.",
                        $exclude
                    );
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Banding Ditolak',
                        "Banding Penyesuaian Karyawan {$label} ditolak HRD. Proses selesai.",
                        $exclude
                    );
                    break;

                case 'ibu_approve':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Waiting Approval',
                        "Permohonan penyesuaian Karyawan {$label} disetujui tahap Waiting Approval. Menunggu Waiting Approval Final.",
                        $exclude
                    );
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan — Waiting Approval',
                        "Permohonan penyesuaian Karyawan {$label} telah disetujui tahap Waiting Approval.",
                        $exclude
                    );
                    break;

                case 'ibu_reject':
                    self::handleRejectNotification($record, $label, $fromStatus, $toStatus, $exclude, $actor);
                    break;

                case 'bapak_approve':
                    self::notifyMenuUsers(
                        self::URL_HRD,
                        'Penyesuaian Karyawan Selesai',
                        "Permohonan penyesuaian Karyawan {$label} disetujui Waiting Approval Final dan telah diterapkan.",
                        $exclude
                    );
                    self::notifyKaryawanIdsWithMenuAccess(
                        [(int) $record->requested_by_id],
                        self::URL_MANAGER,
                        'Penyesuaian Karyawan Selesai',
                        "Permohonan penyesuaian Karyawan {$label} telah selesai dan disetujui Waiting Approval Final.",
                        $exclude
                    );
                    break;

                case 'bapak_reject':
                    self::handleRejectNotification($record, $label, $fromStatus, $toStatus, $exclude, $actor);
                    break;

                default:
                    self::dispatchByStatus($record, $label, $toStatus, $exclude);
                    break;
            }
        } catch (\Throwable $e) {
            Log::warning('Salary adjustment notification failed', [
                'request_id' => $requestId,
                'action' => $action,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private static function handleRejectNotification(
        SalaryAdjustmentRequest $record,
        string $label,
        ?string $fromStatus,
        string $toStatus,
        array $exclude,
        string $actor
    ): void {
        if ($toStatus !== SalaryAdjustmentWorkflowService::STATUS_REJECTED) {
            return;
        }

        $by = $actor !== '' ? " ({$actor})" : '';

        self::notifyKaryawanIdsWithMenuAccess(
            [(int) $record->requested_by_id],
            self::URL_MANAGER,
            'Penyesuaian Karyawan Ditolak',
            "Permohonan penyesuaian Karyawan {$label} ditolak{$by}.",
            $exclude
        );

        self::notifyMenuUsers(
            self::URL_HRD,
            'Penyesuaian Karyawan Ditolak',
            "Permohonan penyesuaian Karyawan {$label} ditolak{$by}.",
            $exclude
        );
    }

    private static function dispatchByStatus(
        SalaryAdjustmentRequest $record,
        string $label,
        string $toStatus,
        array $exclude
    ): void {
        switch ($toStatus) {
            case SalaryAdjustmentWorkflowService::STATUS_SUBMITTED:
                self::notifyMenuUsers(
                    self::URL_HRD,
                    'Penyesuaian Karyawan Baru',
                    "Permohonan penyesuaian Karyawan {$label} menunggu proses HRD.",
                    $exclude
                );
                break;

            case SalaryAdjustmentWorkflowService::STATUS_FINANCE_REVIEW:
                self::notifyMenuUsers(
                    self::URL_FINANCE,
                    'Review Finance Diperlukan',
                    "Permohonan penyesuaian Karyawan {$label} menunggu review Finance.",
                    $exclude
                );
                break;

            case SalaryAdjustmentWorkflowService::STATUS_COMPLETED:
                self::notifyMenuUsers(
                    self::URL_HRD,
                    'Penyesuaian Karyawan Selesai',
                    "Permohonan penyesuaian Karyawan {$label} telah selesai.",
                    $exclude
                );
                self::notifyKaryawanIdsWithMenuAccess(
                    [(int) $record->requested_by_id],
                    self::URL_MANAGER,
                    'Penyesuaian Karyawan Selesai',
                    "Permohonan penyesuaian Karyawan {$label} telah selesai diproses.",
                    $exclude
                );
                break;
        }
    }

    private static function recordLabel(SalaryAdjustmentRequest $record): string
    {
        $employee = MasterKaryawan::find($record->employee_id);
        $name = trim((string) ($employee->nama_lengkap ?? ''));
        $doc = trim((string) ($record->no_document ?? ''));

        if ($doc !== '' && $name !== '') {
            return "{$doc} ({$name})";
        }

        return $doc !== '' ? $doc : ($name !== '' ? $name : 'karyawan');
    }

    private static function notifyMenuUsers(string $url, string $title, string $message, array $exclude = []): void
    {
        $userIds = self::resolveMenuUserIds($url);
        self::notifyKaryawanIds($userIds, $title, $message, $url, $exclude);
    }

    private static function notifyKaryawanIdsWithMenuAccess(
        array $candidateIds,
        string $menuUrl,
        string $title,
        string $message,
        array $exclude = []
    ): void {
        $allowed = array_flip(self::resolveMenuUserIds($menuUrl));
        $userIds = array_values(array_filter(
            array_map('intval', $candidateIds),
            fn ($id) => $id > 0 && isset($allowed[$id])
        ));

        self::notifyKaryawanIds($userIds, $title, $message, $menuUrl, $exclude);
    }

    /**
     * Hanya user aktif yang punya menu terkait dengan akses view (tanpa fallback role).
     *
     * @return int[]
     */
    private static function resolveMenuUserIds(string $menuUrl): array
    {
        $normalizedTarget = self::normalizeMenuPath($menuUrl);

        $records = AksesMenu::query()
            ->join('master_karyawan', 'master_karyawan.user_id', '=', 'akses_menu.user_id')
            ->where('master_karyawan.is_active', 1)
            ->select(['akses_menu.akses', 'master_karyawan.id'])
            ->get();

        $userIds = [];

        foreach ($records as $record) {
            $menus = $record->akses;

            if (is_string($menus)) {
                $menus = json_decode($menus, true);
            }

            if (!is_array($menus)) {
                continue;
            }

            foreach ($menus as $menu) {
                if (!is_array($menu)) {
                    continue;
                }

                if (!self::menuMatchesTarget($menu, $menuUrl, $normalizedTarget)) {
                    continue;
                }

                if (!self::menuHasStrictViewAccess($menu)) {
                    continue;
                }

                $userIds[] = (int) $record->id;
                break;
            }
        }

        return array_values(array_unique(array_filter($userIds)));
    }

    private static function menuMatchesTarget(array $menu, string $menuUrl, string $normalizedTarget): bool
    {
        $path = self::normalizeMenuPath(self::resolveMenuPathFromAksesItem($menu));

        if ($path !== '' && $path !== '/' && $path === $normalizedTarget) {
            return true;
        }

        $expectedName = self::MENU_NAMES_BY_URL[$menuUrl] ?? '';
        $name = trim((string) ($menu['name'] ?? ''));

        return $expectedName !== '' && strcasecmp($name, $expectedName) === 0;
    }

    private static function menuHasStrictViewAccess(array $menu): bool
    {
        if (!empty($menu['view'])) {
            return true;
        }

        $accessList = $menu['access'] ?? [];

        if (is_array($accessList) && in_array('view', $accessList, true)) {
            return true;
        }

        return false;
    }

    private static function normalizeMenuPath(?string $path): string
    {
        $path = trim(strtolower((string) $path));
        $path = preg_replace('#/+#', '/', $path);

        return rtrim($path, '/') ?: '/';
    }

    private static function resolveMenuPathFromAksesItem(array $menu): string
    {
        if (!empty($menu['path'])) {
            return (string) $menu['path'];
        }

        $parent = trim((string) ($menu['parent'] ?? ''));
        $name = trim((string) ($menu['name'] ?? ''));

        if ($parent === '' || $name === '') {
            return '';
        }

        return self::buildMenuPath($parent, $name);
    }

    private static function buildMenuPath(string $parent, string $name): string
    {
        $segments = [];

        foreach (explode('/', $parent) as $part) {
            $slug = Str::slug(str_replace('_', ' ', trim($part)));

            if ($slug !== '') {
                $segments[] = $slug;
            }
        }

        $nameSlug = Str::slug(str_replace('_', ' ', trim($name)));

        if ($nameSlug !== '') {
            $segments[] = $nameSlug;
        }

        return '/' . implode('/', $segments);
    }

    private static function notifyKaryawanIds(
        array $userIds,
        string $title,
        string $message,
        string $url,
        array $exclude = []
    ): void {
        $exclude = array_map('intval', $exclude);
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds))));

        if (!empty($exclude)) {
            $userIds = array_values(array_filter($userIds, fn ($id) => !in_array($id, $exclude, true)));
        }

        if (empty($userIds)) {
            return;
        }

        Notification::whereIn('id', $userIds)
            ->title($title)
            ->message($message)
            ->url($url)
            ->send();
    }
}
