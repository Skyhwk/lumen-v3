<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const BANK_SOAL_LABELS = ['Bank Soal', 'Bank_Soal', 'bank soal'];

    private const RECRUITMENT_LABELS = ['Recruitment', 'Recruitment_HRD', 'recruitment'];

    private const ATS_LABELS = [
        'Applicant Tracking System',
        'Applicant_Tracking_System',
        'Applicant Tracking System (ATS)',
    ];

    public function up(): void
    {
        $this->moveBankSoalMenuItem();
        $this->updateAccessRecords('akses_menu', 'akses');
        $this->updateAccessRecords('template_akses', 'akses');
    }

    public function down(): void
    {
        $this->moveBankSoalMenuItem(true);
        $this->revertAccessRecords('akses_menu', 'akses');
        $this->revertAccessRecords('template_akses', 'akses');
    }

    private function moveBankSoalMenuItem(bool $reverse = false): void
    {
        $menus = DB::table('menu')->where('is_active', true)->get();

        foreach ($menus as $menuRow) {
            $submenu = json_decode($menuRow->submenu ?? '[]', true);
            if (!is_array($submenu) || empty($submenu)) {
                continue;
            }

            $fromLabels = $reverse ? self::ATS_LABELS : self::RECRUITMENT_LABELS;
            $toLabels = $reverse ? self::RECRUITMENT_LABELS : self::ATS_LABELS;

            $changed = $this->transferBankSoalBetweenGroups($submenu, $fromLabels, $toLabels);

            if ($changed) {
                DB::table('menu')
                    ->where('id', $menuRow->id)
                    ->update(['submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function transferBankSoalBetweenGroups(array &$submenu, array $fromLabels, array $toLabels): bool
    {
        $fromIndex = $this->findGroupIndex($submenu, $fromLabels);
        $toIndex = $this->findGroupIndex($submenu, $toLabels);

        if ($fromIndex === null || $toIndex === null) {
            return false;
        }

        $fromGroup = &$submenu[$fromIndex];
        $toGroup = &$submenu[$toIndex];

        if (!isset($fromGroup['sub_menu']) || !is_array($fromGroup['sub_menu'])) {
            return false;
        }

        if (!isset($toGroup['sub_menu']) || !is_array($toGroup['sub_menu'])) {
            $toGroup['sub_menu'] = [];
        }

        $remaining = [];
        $moved = false;

        foreach ($fromGroup['sub_menu'] as $item) {
            if ($this->isBankSoalLabel($item)) {
                if (!$this->containsBankSoalLabel($toGroup['sub_menu'])) {
                    $toGroup['sub_menu'][] = is_string($item) ? $item : 'Bank Soal';
                }
                $moved = true;
                continue;
            }

            $remaining[] = $item;
        }

        if (!$moved) {
            return false;
        }

        $fromGroup['sub_menu'] = array_values($remaining);

        return true;
    }

    private function findGroupIndex(array $submenu, array $labels): ?int
    {
        foreach ($submenu as $index => $group) {
            if (!is_array($group)) {
                continue;
            }

            $name = $group['nama_inden_menu'] ?? $group['name'] ?? null;
            if ($name !== null && $this->matchesLabel($name, $labels)) {
                return $index;
            }
        }

        return null;
    }

    private function isBankSoalLabel($label): bool
    {
        if (!is_string($label)) {
            return false;
        }

        return $this->matchesLabel($label, self::BANK_SOAL_LABELS);
    }

    private function containsBankSoalLabel(array $items): bool
    {
        foreach ($items as $item) {
            if ($this->isBankSoalLabel($item)) {
                return true;
            }
        }

        return false;
    }

    private function matchesLabel(string $value, array $labels): bool
    {
        $normalized = strtolower(trim(str_replace('_', ' ', $value)));

        foreach ($labels as $label) {
            if ($normalized === strtolower(trim(str_replace('_', ' ', $label)))) {
                return true;
            }
        }

        return false;
    }

    private function updateAccessRecords(string $table, string $column): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->get();

        foreach ($rows as $row) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) {
                continue;
            }

            $changed = false;

            foreach ($access as &$item) {
                if (!is_array($item) || empty($item['name']) || !$this->isBankSoalLabel($item['name'])) {
                    continue;
                }

                $parent = (string) ($item['parent'] ?? '');
                $updatedParent = $this->replaceRecruitmentWithAts($parent);

                if ($updatedParent !== $parent) {
                    $item['parent'] = $updatedParent;
                    $changed = true;
                }
            }
            unset($item);

            if ($changed) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($access, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function revertAccessRecords(string $table, string $column): void
    {
        if (!DB::getSchemaBuilder()->hasTable($table)) {
            return;
        }

        $rows = DB::table($table)->get();

        foreach ($rows as $row) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) {
                continue;
            }

            $changed = false;

            foreach ($access as &$item) {
                if (!is_array($item) || empty($item['name']) || !$this->isBankSoalLabel($item['name'])) {
                    continue;
                }

                $parent = (string) ($item['parent'] ?? '');
                $updatedParent = $this->replaceAtsWithRecruitment($parent);

                if ($updatedParent !== $parent) {
                    $item['parent'] = $updatedParent;
                    $changed = true;
                }
            }
            unset($item);

            if ($changed) {
                DB::table($table)
                    ->where('id', $row->id)
                    ->update([$column => json_encode($access, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }

    private function replaceRecruitmentWithAts(string $parent): string
    {
        return preg_replace(
            '/(^|\/)Recruitment(\/|$)/i',
            '$1Applicant Tracking System$2',
            $parent
        ) ?? $parent;
    }

    private function replaceAtsWithRecruitment(string $parent): string
    {
        return preg_replace(
            '/(^|\/)Applicant Tracking System(\/|$)/i',
            '$1Recruitment$2',
            $parent
        ) ?? $parent;
    }
};
