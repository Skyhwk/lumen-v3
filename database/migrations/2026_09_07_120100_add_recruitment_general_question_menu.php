<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('menu')) return;
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) return;
            foreach ($submenu as &$group) {
                $label = strtolower(trim(str_replace('_', ' ', (string) ($group['nama_inden_menu'] ?? $group['name'] ?? ''))));
                if ($label !== 'administrator') continue;
                $group['sub_menu'] = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                if (!in_array('Pertanyaan Umum Rekrutmen', $group['sub_menu'], true)) $group['sub_menu'][] = 'Pertanyaan Umum Rekrutmen';
            }
            unset($group);
            DB::table('menu')->where('id', $row->id)->update(['submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE)]);
        });
        $this->copyBankSoalAccess('akses_menu', 'akses');
        $this->copyBankSoalAccess('template_akses', 'akses');
    }

    public function down(): void
    {
        if (!Schema::hasTable('menu')) return;
        DB::table('menu')->where('is_active', 1)->orderBy('id')->each(function ($row) {
            $submenu = json_decode($row->submenu ?? '[]', true);
            if (!is_array($submenu)) return;
            foreach ($submenu as &$group) {
                if (!is_array($group['sub_menu'] ?? null)) continue;
                $group['sub_menu'] = array_values(array_filter($group['sub_menu'], fn ($item) => $item !== 'Pertanyaan Umum Rekrutmen'));
            }
            unset($group);
            DB::table('menu')->where('id', $row->id)->update(['submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE)]);
        });
        $this->removeRecruitmentGeneralAccess('akses_menu', 'akses');
        $this->removeRecruitmentGeneralAccess('template_akses', 'akses');
    }

    private function copyBankSoalAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table)) return;
        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access) || collect($access)->contains(fn ($item) => is_array($item) && ($item['name'] ?? null) === 'Pertanyaan Umum Rekrutmen')) return;
            $bankSoal = collect($access)->first(fn ($item) => is_array($item) && strtolower(trim((string) ($item['name'] ?? ''))) === 'bank soal');
            if (!$bankSoal) return;
            $bankSoal['name'] = 'Pertanyaan Umum Rekrutmen';
            $access[] = $bankSoal;
            DB::table($table)->where('id', $row->id)->update([$column => json_encode(array_values($access), JSON_UNESCAPED_UNICODE)]);
        });
    }

    private function removeRecruitmentGeneralAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table)) return;
        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $access = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($access)) return;
            $filtered = array_values(array_filter($access, fn ($item) => !is_array($item) || ($item['name'] ?? null) !== 'Pertanyaan Umum Rekrutmen'));
            DB::table($table)->where('id', $row->id)->update([$column => json_encode($filtered, JSON_UNESCAPED_UNICODE)]);
        });
    }
};
