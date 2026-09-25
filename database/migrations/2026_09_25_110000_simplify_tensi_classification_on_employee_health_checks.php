<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        DB::table('employee_health_checks')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $classification = $this->classifyTensi((int) $row->tensi_sistolik);

                DB::table('employee_health_checks')
                    ->where('id', $row->id)
                    ->update(['tensi_classification' => $classification]);
            }
        });

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Tinggi'
            ) NOT NULL
        ");
    }

    public function down(): void
    {
        if (!Schema::hasTable('employee_health_checks')) {
            return;
        }

        DB::statement("
            ALTER TABLE employee_health_checks
            MODIFY tensi_classification ENUM(
                'Rendah',
                'Rata-Rata',
                'Normal',
                'Normal Tinggi',
                'Tinggi'
            ) NOT NULL
        ");

        DB::table('employee_health_checks')->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $classification = $this->classifyTensiLegacy((int) $row->tensi_sistolik);

                DB::table('employee_health_checks')
                    ->where('id', $row->id)
                    ->update(['tensi_classification' => $classification]);
            }
        });
    }

    private function classifyTensi(int $tensiSistolik): string
    {
        if ($tensiSistolik <= 90) {
            return 'Rendah';
        }

        if ($tensiSistolik <= 135) {
            return 'Rata-Rata';
        }

        return 'Tinggi';
    }

    private function classifyTensiLegacy(int $tensiSistolik): string
    {
        if ($tensiSistolik < 90) {
            return 'Rendah';
        }

        if ($tensiSistolik <= 99) {
            return 'Rata-Rata';
        }

        if ($tensiSistolik <= 119) {
            return 'Normal';
        }

        if ($tensiSistolik <= 135) {
            return 'Normal Tinggi';
        }

        return 'Tinggi';
    }
};
