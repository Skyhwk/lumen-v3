<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('master_shift_karyawan')) {
            Schema::create('master_shift_karyawan', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('kode', 50)->unique();
                $table->string('label', 150);
                $table->string('time_in', 8)->nullable();
                $table->string('time_out', 8)->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedTinyInteger('is_active')->default(1);
                $table->string('created_by', 150)->nullable();
                $table->string('updated_by', 150)->nullable();
                $table->timestamps();

                $table->index(['is_active', 'sort_order'], 'idx_master_shift_karyawan_active_order');
            });
        }

        if (DB::table('master_shift_karyawan')->exists()) {
            return;
        }

        $now = Carbon::now()->format('Y-m-d H:i:s');
        $rows = [
            ['kode' => 'SHREGULAR', 'label' => 'SHREGULAR', 'time_in' => '08:00', 'time_out' => '17:00', 'sort_order' => 10],
            ['kode' => 'SHTEKNISI', 'label' => 'SHTEKNISI', 'time_in' => '10:00', 'time_out' => '19:00', 'sort_order' => 20],
            ['kode' => 'SHANALYST', 'label' => 'SHANALYST', 'time_in' => '07:00', 'time_out' => '16:00', 'sort_order' => 30],
            ['kode' => 'SHADMSAMPLING', 'label' => 'SHADMSAMPLING', 'time_in' => '07:00', 'time_out' => '16:00', 'sort_order' => 40],
            ['kode' => 'SHOB', 'label' => 'SHOB', 'time_in' => '06:00', 'time_out' => '15:00', 'sort_order' => 50],
            ['kode' => 'SHOB2', 'label' => 'SHOB2', 'time_in' => '11:00', 'time_out' => '20:00', 'sort_order' => 60],
            ['kode' => 'SWOB', 'label' => 'SWOB', 'time_in' => '07:00', 'time_out' => '12:00', 'sort_order' => 70],
            ['kode' => 'SWOB2', 'label' => 'SWOB2', 'time_in' => '09:00', 'time_out' => '18:00', 'sort_order' => 80],
            ['kode' => 'SHSECURITY', 'label' => 'SHSECURITY', 'time_in' => '08:00', 'time_out' => '20:00', 'sort_order' => 90],
            ['kode' => 'SHSECURITY2', 'label' => 'SHSECURITY2', 'time_in' => '20:00', 'time_out' => '08:00', 'sort_order' => 100],
            ['kode' => 'SHSECURITYGO1', 'label' => 'SHSECURITYGO1', 'time_in' => '07:00', 'time_out' => '19:00', 'sort_order' => 110],
            ['kode' => 'SHSECURITYGO2', 'label' => 'SHSECURITYGO2', 'time_in' => '07:00', 'time_out' => '15:00', 'sort_order' => 120],
            ['kode' => 'SHSPVOPFIN', 'label' => 'SPV Operational Finance', 'time_in' => '09:00', 'time_out' => '18:00', 'sort_order' => 130],
            ['kode' => 'SHANALISMIKRO', 'label' => 'Analis Mikro', 'time_in' => '09:00', 'time_out' => '13:00', 'sort_order' => 140],
            ['kode' => 'SHKORSAMP1', 'label' => 'Koordinator Sampling shift 1', 'time_in' => '08:00', 'time_out' => '17:00', 'sort_order' => 150],
            ['kode' => 'SHKORSAMP2', 'label' => 'Koordinator Sampling shift 2', 'time_in' => '11:00', 'time_out' => '20:00', 'sort_order' => 160],
        ];

        foreach ($rows as $row) {
            DB::table('master_shift_karyawan')->insert(array_merge($row, [
                'is_active' => 1,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }

        if (Schema::hasTable('shift_karyawan')) {
            DB::table('shift_karyawan')
                ->where('shift', 'SHOB2')
                ->update([
                    'time_in' => '11:00:00',
                    'time_out' => '20:00:00',
                ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('master_shift_karyawan');
    }
};
