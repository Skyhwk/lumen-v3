<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('master_sallary')) {
            $this->addColumn('master_sallary', 'jadwal_pembayaran_thr', function (Blueprint $table) {
                $column = $table->string('jadwal_pembayaran_thr', 100)
                    ->nullable()
                    ->comment('Hari raya pembayaran THR, termasuk isian bebas');
                if (Schema::hasColumn('master_sallary', 'tunjangan_kerja')) {
                    $column->after('tunjangan_kerja');
                }
            });

            $this->addColumn('master_sallary', 'metode_pembayaran_gaji', function (Blueprint $table) {
                $column = $table->string('metode_pembayaran_gaji', 20)
                    ->nullable()
                    ->comment('tunai atau transfer');
                if (Schema::hasColumn('master_sallary', 'jadwal_pembayaran_thr')) {
                    $column->after('jadwal_pembayaran_thr');
                }
            });

            $this->addColumn('master_sallary', 'jumlah_hari_kerja', function (Blueprint $table) {
                $column = $table->unsignedTinyInteger('jumlah_hari_kerja')
                    ->nullable()
                    ->comment('Jumlah hari kerja kontrak per bulan');
                if (Schema::hasColumn('master_sallary', 'metode_pembayaran_gaji')) {
                    $column->after('metode_pembayaran_gaji');
                }
            });

            $this->addColumn('master_sallary', 'jadwal_penggajian', function (Blueprint $table) {
                $column = $table->unsignedSmallInteger('jadwal_penggajian')
                    ->nullable()
                    ->comment('Offset hari kerja setelah akhir bulan, contoh 5 untuk H+5');
                if (Schema::hasColumn('master_sallary', 'jumlah_hari_kerja')) {
                    $column->after('jumlah_hari_kerja');
                }
            });
        }

        if (Schema::hasTable('rekening_karyawan')) {
            $this->addColumn('rekening_karyawan', 'cabang_bank', function (Blueprint $table) {
                $column = $table->string('cabang_bank', 150)->nullable();
                if (Schema::hasColumn('rekening_karyawan', 'nama_bank')) {
                    $column->after('nama_bank');
                }
            });

            $this->addColumn('rekening_karyawan', 'nama_pemilik_rekening', function (Blueprint $table) {
                $column = $table->string('nama_pemilik_rekening', 150)->nullable();
                if (Schema::hasColumn('rekening_karyawan', 'cabang_bank')) {
                    $column->after('cabang_bank');
                } elseif (Schema::hasColumn('rekening_karyawan', 'no_rekening')) {
                    $column->after('no_rekening');
                }
            });
        }
    }

    private function addColumn(string $tableName, string $column, callable $define): void
    {
        if (Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, $define);
    }

    public function down(): void
    {
        if (Schema::hasTable('master_sallary')) {
            Schema::table('master_sallary', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    'jadwal_pembayaran_thr',
                    'metode_pembayaran_gaji',
                    'jumlah_hari_kerja',
                    'jadwal_penggajian',
                ], function ($column) {
                    return Schema::hasColumn('master_sallary', $column);
                }));

                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }

        if (Schema::hasTable('rekening_karyawan')) {
            Schema::table('rekening_karyawan', function (Blueprint $table) {
                $columns = array_values(array_filter([
                    'cabang_bank',
                    'nama_pemilik_rekening',
                ], function ($column) {
                    return Schema::hasColumn('rekening_karyawan', $column);
                }));

                if ($columns) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
