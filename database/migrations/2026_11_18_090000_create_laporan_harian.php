<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const MENU = 'Laporan Harian';

    public function up(): void
    {
        if (!Schema::hasTable('laporan_harian')) {
            Schema::create('laporan_harian', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->char('no_dokumen', 9);
                $table->unsignedBigInteger('karyawan_id');
                $table->date('tanggal');
                $table->longText('isi')->nullable();
                $table->dateTime('created_at')->nullable();
                $table->dateTime('updated_at')->nullable();

                $table->unique('no_dokumen', 'uq_laporan_harian_no_dokumen');
                $table->unique(['karyawan_id', 'tanggal'], 'uq_laporan_harian_karyawan_tanggal');
            });
        }

        $this->addMenu();
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan_harian');
        $this->removeMenu();
    }

    private function addMenu(): void
    {
        $row = DB::table('menu')->where('menu', 'Request')->where('is_active', 1)->first();
        if (!$row) {
            return;
        }

        $submenu = json_decode($row->submenu ?? '[]', true);
        if (!is_array($submenu)) {
            $submenu = [];
        }

        foreach ($submenu as $item) {
            $label = is_array($item) ? ($item['nama_inden_menu'] ?? '') : (string) $item;
            if (strcasecmp(trim((string) $label), self::MENU) === 0) {
                return;
            }
        }

        $submenu[] = [
            'nama_inden_menu' => self::MENU,
            'sub_menu' => null,
        ];

        DB::table('menu')->where('id', $row->id)->update([
            'submenu' => json_encode(array_values($submenu), JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function removeMenu(): void
    {
        $row = DB::table('menu')->where('menu', 'Request')->first();
        if (!$row) {
            return;
        }

        $submenu = json_decode($row->submenu ?? '[]', true);
        if (!is_array($submenu)) {
            return;
        }

        $submenu = array_values(array_filter($submenu, function ($item) {
            $label = is_array($item) ? ($item['nama_inden_menu'] ?? '') : (string) $item;
            return strcasecmp(trim((string) $label), self::MENU) !== 0;
        }));

        DB::table('menu')->where('id', $row->id)->update([
            'submenu' => json_encode($submenu, JSON_UNESCAPED_UNICODE),
        ]);
    }
};
