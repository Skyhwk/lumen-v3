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
                $name = strtolower(trim((string) ($group['nama_inden_menu'] ?? '')));
                if ($name !== 'applicant tracking system') continue;
                $group['sub_menu'] = is_array($group['sub_menu'] ?? null) ? $group['sub_menu'] : [];
                if (!in_array('Jobpost Category', $group['sub_menu'], true)) $group['sub_menu'][] = 'Jobpost Category';
            }
            unset($group);
            DB::table('menu')->where('id', $row->id)->update(['submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE)]);
        });
        $this->copyAccess('akses_menu', 'akses');
        $this->copyAccess('template_akses', 'akses');
    }

    private function copyAccess(string $table, string $column): void
    {
        if (!Schema::hasTable($table)) return;
        DB::table($table)->orderBy('id')->each(function ($row) use ($table, $column) {
            $items = json_decode($row->{$column} ?? '[]', true);
            if (!is_array($items) || collect($items)->contains(fn ($item) => is_array($item) && ($item['name'] ?? '') === 'Jobpost Category')) return;
            $source = collect($items)->first(fn ($item) => is_array($item) && strtolower(trim((string) ($item['name'] ?? ''))) === 'data personel request');
            if (!$source) return;
            $source['name'] = 'Jobpost Category';
            $items[] = $source;
            DB::table($table)->where('id', $row->id)->update([$column => json_encode(array_values($items), JSON_UNESCAPED_UNICODE)]);
        });
    }
};
