<?php

namespace App\Services;

use App\Models\AksesMenu;
use App\Models\MasterDivisi;
use App\Models\MasterKaryawan;
use App\Models\RequestKebijakan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RequestKebijakanNotificationService
{
    public const URL_APPROVAL = '/legal/ketetapan-perusahaan/persetujuan-request-ketetapan';

    public const URL_DRAFTING = '/legal/ketetapan-perusahaan/drafting-ketetapan';

    public const URL_REQUEST = '/request/request-ketetapan';

    public const URL_DOKUMEN = '/legal/dokumen/ketetapan-perusahaan';

    public static function requestSubmitted(RequestKebijakan $record): void
    {
        $label = self::recordLabel($record);

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Request Kebijakan Baru',
            "Terdapat request kebijakan baru {$label} yang menunggu persetujuan.",
            self::URL_APPROVAL
        );
    }

    public static function requestApproved(RequestKebijakan $record, $approver): void
    {
        $label = self::recordLabel($record);
        $by = $approver->nama_lengkap ?? 'Executive';

        self::notifyUserIds(
            self::getDraftingMenuUserIds(),
            'Request Kebijakan Disetujui',
            "Request kebijakan {$label} telah disetujui oleh {$by} pada " . self::formatDate() . ' dan siap untuk drafting.',
            self::URL_DRAFTING
        );
    }

    public static function requestRejected(RequestKebijakan $record, $approver): void
    {
        $label = self::recordLabel($record);
        $by = $approver->nama_lengkap ?? 'Executive';
        $requesterId = self::getUserIdByName($record->request_by);

        if (!$requesterId) {
            return;
        }

        self::notifyUserIds(
            [$requesterId],
            'Request Kebijakan Ditolak',
            "Request kebijakan {$label} ditolak oleh {$by} pada " . self::formatDate() . '.',
            self::URL_REQUEST
        );
    }

    public static function draftSubmitted(RequestKebijakan $record, $legalUser): void
    {
        $label = self::recordLabel($record);
        $by = $legalUser->nama_lengkap ?? 'Tim Legal';

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Pengajuan Preview Draft Kebijakan',
            "Draft kebijakan {$label} telah diajukan oleh {$by} pada " . self::formatDate() . '. Silakan preview dan review draft di menu Request Kebijakan Approval.',
            self::URL_APPROVAL
        );
    }

    public static function reviewAssignedToVerifiers(RequestKebijakan $record, $executive, array $verifierKaryawanIds): void
    {
        $label = self::recordLabel($record);
        $by = $executive->nama_lengkap ?? 'Executive';

        self::notifyUserIds(
            $verifierKaryawanIds,
            'Draft Kebijakan Menunggu Verifikasi Anda',
            "Draft kebijakan {$label} ditugaskan kepada Anda oleh {$by} pada " . self::formatDate() . ' untuk verifikasi dokumen.',
            self::URL_REQUEST
        );
    }

    public static function verifierReopenedAfterResubmit(RequestKebijakan $record, array $verifierKaryawanIds): void
    {
        $label = self::recordLabel($record);

        self::notifyUserIds(
            $verifierKaryawanIds,
            'Draft Kebijakan Perlu Verifikasi Ulang',
            "Draft kebijakan {$label} telah direvisi tim legal. Silakan verifikasi ulang dokumen.",
            self::URL_REQUEST
        );
    }

    public static function verifierReviewRejectedPendingApproval(RequestKebijakan $record, $rejectedVerifiers): void
    {
        $label = self::recordLabel($record);
        $count = is_countable($rejectedVerifiers) ? count($rejectedVerifiers) : 0;

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Penolakan Verifikasi - Request Kebijakan',
            "Draft kebijakan {$label} memiliki {$count} penolakan verifikasi setelah semua reviewer respond. Menunggu tindak lanjut approval.",
            self::URL_APPROVAL
        );
    }

    public static function allVerifiersCompletedPendingLegalFinal(RequestKebijakan $record): void
    {
        $label = self::recordLabel($record);
        $by = $record->user_reviewed_by ?? 'Reviewer';

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Verifikasi Awal Selesai - Menunggu Legal Final',
            "Draft kebijakan {$label} telah diverifikasi reviewer ({$by}) pada " . self::formatDate() . '. Menunggu verifikasi final legal manager.',
            self::URL_APPROVAL
        );
    }

    public static function legalFinalVerifiedPendingDirector(RequestKebijakan $record, $dokumen): void
    {
        $label = self::recordLabel($record);
        $noDokumen = $dokumen->no_dokumen ?? '-';

        self::notifyUserIds(
            self::getDirectorUserIds(),
            'Dokumen Kebijakan Menunggu Pengesahan',
            "Dokumen kebijakan {$noDokumen} ({$label}) menunggu pengesahan Director.",
            self::URL_DOKUMEN
        );
    }

    public static function directorApprovedDocument(RequestKebijakan $record, $dokumen): void
    {
        $label = self::recordLabel($record);
        $noDokumen = $dokumen->no_dokumen ?? '-';
        $requesterId = self::getUserIdByName($record->request_by);

        $targets = array_merge(self::getApprovalMenuUserIds(), self::getDraftingMenuUserIds());
        if ($requesterId) {
            $targets[] = $requesterId;
        }

        self::notifyUserIds(
            $targets,
            'Dokumen Kebijakan Disahkan',
            "Dokumen kebijakan {$noDokumen} ({$label}) telah disahkan Director dan dinyatakan aktif.",
            self::URL_DOKUMEN
        );
    }

    public static function directorRejectedToLegalFinal(RequestKebijakan $record, $dokumen, $director): void
    {
        $label = self::recordLabel($record);
        $by = $director->nama_lengkap ?? 'Director';
        $noDokumen = $dokumen->no_dokumen ?? '-';

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Penolakan Pengesahan Director',
            "Dokumen kebijakan {$noDokumen} ({$label}) ditolak Director ({$by}). Menunggu verifikasi final legal.",
            self::URL_APPROVAL
        );
    }

    public static function canAccessApprovalMenu($employee): bool
    {
        if (!$employee || empty($employee->id)) {
            return false;
        }

        return in_array((int) $employee->id, self::getApprovalMenuUserIds(), true);
    }

    private static function getDirectorUserIds(): array
    {
        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->whereIn(DB::raw('UPPER(REPLACE(grade, "_", " "))'), ['DIRECTOR'])
            ->pluck('id')
            ->all();
    }

    public static function reviewRejectedByExecutive(RequestKebijakan $record, $executive): void
    {
        $label = self::recordLabel($record);
        $by = $executive->nama_lengkap ?? 'Executive';

        self::notifyUserIds(
            self::getDraftingMenuUserIds(),
            'Draft Kebijakan Perlu Revisi',
            "Draft kebijakan {$label} ditolak review oleh {$by} pada " . self::formatDate() . ' dan perlu diperbaiki.',
            self::URL_DRAFTING
        );
    }

    public static function userReviewApproved(RequestKebijakan $record): void
    {
        $label = self::recordLabel($record);
        $by = $record->user_reviewed_by ?? 'Pemohon';

        self::notifyUserIds(
            array_merge(self::getApprovalMenuUserIds(), self::getDraftingMenuUserIds()),
            'Review Kebijakan Selesai',
            "Draft kebijakan {$label} telah disetujui pemohon ({$by}) pada " . self::formatDate() . '.',
            self::URL_APPROVAL
        );
    }

    public static function userReviewRejectedPendingApproval(RequestKebijakan $record, $user): void
    {
        $label = self::recordLabel($record);
        $by = $user->nama_lengkap ?? 'Pemohon';

        self::notifyUserIds(
            self::getApprovalMenuUserIds(),
            'Penolakan Review User - Request Kebijakan',
            "Draft kebijakan {$label} ditolak review pemohon ({$by}) pada " . self::formatDate() . ' dan menunggu tindak lanjut approval.',
            self::URL_APPROVAL
        );
    }

    public static function reviewRejectedAfterUserToLegal(RequestKebijakan $record, $executive, bool $usedUserNote): void
    {
        $label = self::recordLabel($record);
        $by = $executive->nama_lengkap ?? 'Executive';
        $noteInfo = $usedUserNote ? ' dengan catatan penolakan user' : '';

        self::notifyUserIds(
            self::getDraftingMenuUserIds(),
            'Draft Kebijakan Perlu Revisi',
            "Draft kebijakan {$label} dikembalikan ke legal oleh {$by} pada " . self::formatDate() . "{$noteInfo}.",
            self::URL_DRAFTING
        );
    }

    private static function getApprovalMenuUserIds(): array
    {
        return self::getUserIdsByMenuPath(self::URL_APPROVAL, ['view', 'approve']);
    }

    private static function getDraftingMenuUserIds(): array
    {
        return self::getUserIdsByMenuPath(self::URL_DRAFTING, ['view', 'update']);
    }

    private static function getUserIdsByMenuPath(string $menuPath, array $requiredAccessTypes = ['view']): array
    {
        $normalizedTarget = self::normalizeMenuPath($menuPath);

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

                $path = self::normalizeMenuPath(self::resolveMenuPathFromAksesItem($menu));

                if ($path !== $normalizedTarget || !self::menuHasAccess($menu, $requiredAccessTypes)) {
                    continue;
                }

                $userIds[] = (int) $record->id;
                break;
            }
        }

        $userIds = array_values(array_unique(array_filter($userIds)));

        if (!empty($userIds)) {
            return $userIds;
        }

        return self::getFallbackUserIdsForMenuPath($menuPath);
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

    private static function menuHasAccess(array $menu, array $requiredAccessTypes): bool
    {
        foreach ($requiredAccessTypes as $type) {
            if (!empty($menu[$type])) {
                return true;
            }
        }

        $accessList = $menu['access'] ?? [];

        if (is_array($accessList)) {
            foreach ($requiredAccessTypes as $type) {
                if (in_array($type, $accessList, true)) {
                    return true;
                }
            }
        }

        foreach (['view', 'create', 'update', 'delete', 'approve', 'reject', 'all'] as $type) {
            if (!empty($menu[$type])) {
                return true;
            }
        }

        return false;
    }

    private static function getFallbackUserIdsForMenuPath(string $menuPath): array
    {
        if ($menuPath === self::URL_APPROVAL) {
            return self::getApproverUserIds();
        }

        if ($menuPath === self::URL_DRAFTING) {
            return self::getLegalTeamUserIds();
        }

        return [];
    }

    private static function recordLabel(RequestKebijakan $record): string
    {
        $noRequest = trim((string) ($record->no_request ?? ''));
        $judul = trim((string) ($record->judul ?? ''));

        if ($noRequest !== '' && $judul !== '') {
            return "{$noRequest} - {$judul}";
        }

        if ($noRequest !== '') {
            return $noRequest;
        }

        return $judul !== '' ? $judul : 'kebijakan';
    }

    private static function formatDate(): string
    {
        return date('d-m-Y H:i');
    }

    private static function notifyUserIds(array $userIds, string $title, string $message, string $url): void
    {
        $userIds = array_values(array_unique(array_filter($userIds)));

        if (empty($userIds)) {
            return;
        }

        Notification::whereIn('id', $userIds)
            ->title($title)
            ->message($message)
            ->url($url)
            ->send();
    }

    private static function getApproverUserIds(): array
    {
        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->whereIn(DB::raw('UPPER(REPLACE(grade, "_", " "))'), RequestKebijakanWorkflowService::APPROVER_GRADES)
            ->pluck('id')
            ->all();
    }

    private static function getExecutiveUserIds(): array
    {
        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->whereIn(DB::raw('UPPER(REPLACE(grade, "_", " "))'), ['EXECUTIVE', 'DIRECTOR'])
            ->pluck('id')
            ->all();
    }

    private static function getLegalTeamUserIds(): array
    {
        $legalDeptIds = MasterDivisi::query()
            ->where('is_active', true)
            ->where('nama_divisi', 'like', '%Legal%')
            ->pluck('id')
            ->all();

        if (empty($legalDeptIds)) {
            return [];
        }

        return MasterKaryawan::query()
            ->where('is_active', 1)
            ->whereIn('id_department', $legalDeptIds)
            ->pluck('id')
            ->all();
    }

    private static function getUserIdByName(?string $namaLengkap): ?int
    {
        if (!$namaLengkap) {
            return null;
        }

        $id = MasterKaryawan::query()
            ->where('is_active', 1)
            ->where('nama_lengkap', $namaLengkap)
            ->value('id');

        return $id ? (int) $id : null;
    }
}
