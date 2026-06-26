<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('dashboard_component')
            ->where('nama_komponen', 'DashboardPaymentPerformance')
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        DB::table('dashboard_component')->insert([
            'nama_komponen' => 'DashboardPaymentPerformance',
            'nama_dashboard' => 'Dashboard Payment Performance',
            'owner' => 'System',
            'owner_id' => '1',
            'is_active' => 1,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function down(): void
    {
        DB::table('dashboard_component')
            ->where('nama_komponen', 'DashboardPaymentPerformance')
            ->update([
                'deleted_at' => Carbon::now()->format('Y-m-d H:i:s'),
                'deleted_by' => 'system',
                'is_active' => 0,
                'updated_at' => Carbon::now()->format('Y-m-d H:i:s'),
            ]);
    }
};
