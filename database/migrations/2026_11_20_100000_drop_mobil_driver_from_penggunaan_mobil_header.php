<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('penggunaan_mobil_header')) {
            return;
        }

        if (Schema::hasColumn('penggunaan_mobil_header', 'driver_id') && Schema::hasTable('penggunaan_mobil_driver')) {
            $headers = DB::table('penggunaan_mobil_header')
                ->whereNotNull('driver_id')
                ->where('driver_id', '>', 0)
                ->get(['id', 'driver_id', 'created_by', 'created_at']);

            foreach ($headers as $header) {
                $hasActive = DB::table('penggunaan_mobil_driver')
                    ->where('header_id', $header->id)
                    ->where('is_active', true)
                    ->exists();

                if ($hasActive) {
                    continue;
                }

                DB::table('penggunaan_mobil_driver')->insert([
                    'header_id' => $header->id,
                    'driver_id' => $header->driver_id,
                    'is_active' => true,
                    'created_by' => $header->created_by,
                    'created_at' => $header->created_at ?: date('Y-m-d H:i:s'),
                ]);
            }
        }

        if (Schema::hasColumn('penggunaan_mobil_header', 'mobil_id') && Schema::hasTable('penggunaan_mobil_detail')) {
            $detailRows = DB::table('penggunaan_mobil_detail as d')
                ->join('penggunaan_mobil_header as h', 'h.id', '=', 'd.header_id')
                ->where('d.is_active', true)
                ->where(function ($query) {
                    $query->whereNull('d.mobil_id')->orWhere('d.mobil_id', 0);
                })
                ->whereNotNull('h.mobil_id')
                ->where('h.mobil_id', '>', 0)
                ->get(['d.id as detail_id', 'h.mobil_id']);

            foreach ($detailRows as $row) {
                DB::table('penggunaan_mobil_detail')
                    ->where('id', $row->detail_id)
                    ->update(['mobil_id' => $row->mobil_id]);
            }
        }

        $columnsToDrop = [];
        if (Schema::hasColumn('penggunaan_mobil_header', 'mobil_id')) {
            $columnsToDrop[] = 'mobil_id';
        }
        if (Schema::hasColumn('penggunaan_mobil_header', 'driver_id')) {
            $columnsToDrop[] = 'driver_id';
        }

        if (!empty($columnsToDrop)) {
            Schema::table('penggunaan_mobil_header', function (Blueprint $table) use ($columnsToDrop) {
                $table->dropColumn($columnsToDrop);
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('penggunaan_mobil_header')) {
            return;
        }

        Schema::table('penggunaan_mobil_header', function (Blueprint $table) {
            if (!Schema::hasColumn('penggunaan_mobil_header', 'mobil_id')) {
                $table->unsignedBigInteger('mobil_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('penggunaan_mobil_header', 'driver_id')) {
                $table->unsignedBigInteger('driver_id')->nullable()->after('mobil_id');
            }
        });
    }
};