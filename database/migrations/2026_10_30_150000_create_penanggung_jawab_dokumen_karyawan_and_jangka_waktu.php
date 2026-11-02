<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('master_penanggung_jawab_dokumen')
            && !Schema::hasTable('penanggung_jawab_dokumen_karyawan')
        ) {
            Schema::rename('master_penanggung_jawab_dokumen', 'penanggung_jawab_dokumen_karyawan');
        }

        if (!Schema::hasTable('penanggung_jawab_dokumen_karyawan')) {
            Schema::create('penanggung_jawab_dokumen_karyawan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nama_karyawan', 150);
                $table->string('jabatan', 150);
                $table->unsignedTinyInteger('is_active')->default(1);
                $table->string('created_by', 150)->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamps();
                $table->index('is_active', 'mpjd_is_active_idx');
            });
        } else {
            Schema::table('penanggung_jawab_dokumen_karyawan', function (Blueprint $table) {
                if (Schema::hasColumn('penanggung_jawab_dokumen_karyawan', 'id_karyawan')) {
                    $table->dropUnique('mpjd_id_karyawan_unique');
                    $table->dropColumn('id_karyawan');
                }
            });

            Schema::table('penanggung_jawab_dokumen_karyawan', function (Blueprint $table) {
                if (!Schema::hasColumn('penanggung_jawab_dokumen_karyawan', 'nama_karyawan')) {
                    $table->string('nama_karyawan', 150)->after('id');
                }
                if (!Schema::hasColumn('penanggung_jawab_dokumen_karyawan', 'jabatan')) {
                    $table->string('jabatan', 150)->after('nama_karyawan');
                }
            });
        }

        if (!Schema::hasTable('master_sallary')) {
            return;
        }

        if (!Schema::hasColumn('master_sallary', 'satuan_jangka_waktu')) {
            Schema::table('master_sallary', function (Blueprint $table) {
                $column = $table->string('satuan_jangka_waktu', 10)
                    ->nullable()
                    ->comment('bulan atau tahun');
                if (Schema::hasColumn('master_sallary', 'jadwal_penggajian')) {
                    $column->after('jadwal_penggajian');
                }
            });
        }

        if (!Schema::hasColumn('master_sallary', 'jumlah_jangka_waktu')) {
            Schema::table('master_sallary', function (Blueprint $table) {
                $column = $table->unsignedSmallInteger('jumlah_jangka_waktu')
                    ->nullable()
                    ->comment('Jumlah bulan atau tahun perjanjian masa pelatihan');
                if (Schema::hasColumn('master_sallary', 'satuan_jangka_waktu')) {
                    $column->after('satuan_jangka_waktu');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penanggung_jawab_dokumen_karyawan');

        if (!Schema::hasTable('master_sallary')) {
            return;
        }

        Schema::table('master_sallary', function (Blueprint $table) {
            $columns = array_values(array_filter([
                'satuan_jangka_waktu',
                'jumlah_jangka_waktu',
            ], function ($column) {
                return Schema::hasColumn('master_sallary', $column);
            }));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
