<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return;
        }

        Schema::table('surat_peringatan_disipliner', function (Blueprint $table) {
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'catatan_hrd')) {
                $table->longText('catatan_hrd')->nullable()->after('uraian_pelanggaran');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'bentuk_sanksi')) {
                $table->text('bentuk_sanksi')->nullable()->after('catatan_hrd');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'masa_berlaku_bulan')) {
                $table->unsignedTinyInteger('masa_berlaku_bulan')->nullable()->after('bentuk_sanksi');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'tanggal_berlaku_mulai')) {
                $table->date('tanggal_berlaku_mulai')->nullable()->after('masa_berlaku_bulan');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'tanggal_berlaku_selesai')) {
                $table->date('tanggal_berlaku_selesai')->nullable()->after('tanggal_berlaku_mulai');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'nama_penandatangan')) {
                $table->string('nama_penandatangan', 150)->nullable()->after('published_by');
            }
            if (!Schema::hasColumn('surat_peringatan_disipliner', 'jabatan_penandatangan')) {
                $table->string('jabatan_penandatangan', 150)->nullable()->after('nama_penandatangan');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('surat_peringatan_disipliner')) {
            return;
        }

        Schema::table('surat_peringatan_disipliner', function (Blueprint $table) {
            $columns = [
                'catatan_hrd',
                'bentuk_sanksi',
                'masa_berlaku_bulan',
                'tanggal_berlaku_mulai',
                'tanggal_berlaku_selesai',
                'nama_penandatangan',
                'jabatan_penandatangan',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('surat_peringatan_disipliner', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
