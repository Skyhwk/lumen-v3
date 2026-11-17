<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('peralatan')) {
            Schema::table('peralatan', function (Blueprint $table) {
                $drop = [];

                foreach (['kode_alat', 'status_alat', 'keterangan', 'start_date', 'end_date'] as $column) {
                    if (Schema::hasColumn('peralatan', $column)) {
                        $drop[] = $column;
                    }
                }

                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });
        }

        if (Schema::hasTable('monitoring_peralatan')) {
            Schema::table('monitoring_peralatan', function (Blueprint $table) {
                if (Schema::hasColumn('monitoring_peralatan', 'id_cabang')) {
                    $table->dropForeign('mp_master_cabang_fk');
                }
            });

            Schema::table('monitoring_peralatan', function (Blueprint $table) {
                $drop = [];

                foreach ([
                    'nama_alat',
                    'jenis_peralatan',
                    'warna',
                    'nama_kategori_pengujian',
                    'id_cabang',
                    'lokasi_penempatan_alat',
                    'jumlah_alat_tersedia',
                ] as $column) {
                    if (Schema::hasColumn('monitoring_peralatan', $column)) {
                        $drop[] = $column;
                    }
                }

                if ($drop !== []) {
                    $table->dropColumn($drop);
                }
            });

            if (!Schema::hasColumn('monitoring_peralatan', 'id_peralatan')) {
                Schema::table('monitoring_peralatan', function (Blueprint $table) {
                    $column = $table->unsignedBigInteger('id_peralatan')->nullable();

                    if (Schema::hasColumn('monitoring_peralatan', 'id')) {
                        $column->after('id');
                    }

                    $table->foreign('id_peralatan', 'mp_peralatan_fk')
                        ->references('id')
                        ->on('peralatan')
                        ->restrictOnDelete();

                    $table->index('id_peralatan', 'mp_id_peralatan_idx');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('monitoring_peralatan')) {
            if (Schema::hasColumn('monitoring_peralatan', 'id_peralatan')) {
                Schema::table('monitoring_peralatan', function (Blueprint $table) {
                    $table->dropForeign('mp_peralatan_fk');
                    $table->dropIndex('mp_id_peralatan_idx');
                    $table->dropColumn('id_peralatan');
                });
            }

            Schema::table('monitoring_peralatan', function (Blueprint $table) {
                if (!Schema::hasColumn('monitoring_peralatan', 'nama_alat')) {
                    $table->string('nama_alat', 255)->nullable()->after('id');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'jenis_peralatan')) {
                    $table->string('jenis_peralatan', 255)->nullable()->after('nama_alat');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'warna')) {
                    $table->enum('warna', ['Merah', 'Biru', 'Hitam'])->nullable()->after('jenis_peralatan');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'nama_kategori_pengujian')) {
                    $table->string('nama_kategori_pengujian', 70)->nullable()->after('warna');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'id_cabang')) {
                    $table->unsignedBigInteger('id_cabang')->nullable()->after('nama_kategori_pengujian');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'lokasi_penempatan_alat')) {
                    $table->string('lokasi_penempatan_alat', 70)->nullable()->after('id_cabang');
                }
                if (!Schema::hasColumn('monitoring_peralatan', 'jumlah_alat_tersedia')) {
                    $after = Schema::hasColumn('monitoring_peralatan', 'status_alat')
                        ? 'status_alat'
                        : 'lokasi_penempatan_alat';
                    $table->unsignedInteger('jumlah_alat_tersedia')->default(0)->after($after);
                }
            });

            if (Schema::hasColumn('monitoring_peralatan', 'id_cabang')) {
                Schema::table('monitoring_peralatan', function (Blueprint $table) {
                    $table->foreign('id_cabang', 'mp_master_cabang_fk')
                        ->references('id')
                        ->on('master_cabang')
                        ->restrictOnDelete();

                    if (Schema::hasColumn('monitoring_peralatan', 'nama_alat')) {
                        $table->index('nama_alat', 'mp_nama_alat_idx');
                    }
                    if (Schema::hasColumn('monitoring_peralatan', 'warna')) {
                        $table->index('warna', 'mp_warna_idx');
                    }
                });
            }
        }

        if (Schema::hasTable('peralatan')) {
            Schema::table('peralatan', function (Blueprint $table) {
                if (!Schema::hasColumn('peralatan', 'kode_alat')) {
                    $column = $table->text('kode_alat')->nullable();
                    if (Schema::hasColumn('peralatan', 'nama_alat')) {
                        $column->after('nama_alat');
                    }
                }
                if (!Schema::hasColumn('peralatan', 'status_alat')) {
                    $column = $table->text('status_alat')->nullable();
                    if (Schema::hasColumn('peralatan', 'lokasi_penempatan_alat')) {
                        $column->after('lokasi_penempatan_alat');
                    }
                }
                if (!Schema::hasColumn('peralatan', 'keterangan')) {
                    $column = $table->text('keterangan')->nullable();
                    if (Schema::hasColumn('peralatan', 'status_alat')) {
                        $column->after('status_alat');
                    }
                }
                if (!Schema::hasColumn('peralatan', 'start_date')) {
                    $column = $table->date('start_date')->nullable();
                    if (Schema::hasColumn('peralatan', 'keterangan')) {
                        $column->after('keterangan');
                    }
                }
                if (!Schema::hasColumn('peralatan', 'end_date')) {
                    $column = $table->date('end_date')->nullable();
                    if (Schema::hasColumn('peralatan', 'start_date')) {
                        $column->after('start_date');
                    }
                }
            });
        }
    }
};
