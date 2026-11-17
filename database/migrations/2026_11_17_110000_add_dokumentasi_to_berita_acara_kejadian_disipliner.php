<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        if (Schema::hasColumn('berita_acara_kejadian_disipliner', 'dokumentasi')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            $column = $table->text('dokumentasi')->nullable()->comment('JSON path foto dokumentasi');
            if (Schema::hasColumn('berita_acara_kejadian_disipliner', 'uraian_kejadian')) {
                $column->after('uraian_kejadian');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('berita_acara_kejadian_disipliner')) {
            return;
        }

        if (!Schema::hasColumn('berita_acara_kejadian_disipliner', 'dokumentasi')) {
            return;
        }

        Schema::table('berita_acara_kejadian_disipliner', function (Blueprint $table) {
            $table->dropColumn('dokumentasi');
        });
    }
};
