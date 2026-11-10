<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('jadwal_mobilisasi')) {
            return;
        }

        Schema::table('jadwal_mobilisasi', function (Blueprint $table) {
            if (!Schema::hasColumn('jadwal_mobilisasi', 'keterangan_operasional_sp')) {
                $table->text('keterangan_operasional_sp')
                    ->nullable()
                    ->after('peralatan_sampling')
                    ->comment('Catatan operasional awal (tentative) dari menu SP/RSP');
            }

            if (!Schema::hasColumn('jadwal_mobilisasi', 'keterangan_operasional')) {
                $table->text('keterangan_operasional')
                    ->nullable()
                    ->after('keterangan_operasional_sp')
                    ->comment('Catatan operasional yang diteruskan & disesuaikan oleh Tim Operasional (IO / Catatan OPS)');
            }

            if (!Schema::hasColumn('jadwal_mobilisasi', 'status_rilis_io')) {
                $table->enum('status_rilis_io', ['draft', 'released'])
                    ->default('draft')
                    ->after('keterangan_operasional');
            }

            if (!Schema::hasColumn('jadwal_mobilisasi', 'released_io_by')) {
                $table->string('released_io_by', 150)
                    ->nullable()
                    ->after('status_rilis_io');
            }

            if (!Schema::hasColumn('jadwal_mobilisasi', 'released_io_at')) {
                $table->timestamp('released_io_at')
                    ->nullable()
                    ->after('released_io_by');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('jadwal_mobilisasi')) {
            return;
        }

        Schema::table('jadwal_mobilisasi', function (Blueprint $table) {
            $cols = [
                'released_io_at',
                'released_io_by',
                'status_rilis_io',
                'keterangan_operasional',
                'keterangan_operasional_sp',
            ];
            foreach ($cols as $col) {
                if (Schema::hasColumn('jadwal_mobilisasi', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
