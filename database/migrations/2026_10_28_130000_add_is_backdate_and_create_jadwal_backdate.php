<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddIsBackdateAndCreateJadwalBackdate extends Migration
{
    public function up()
    {
        // 1. Tambah is_backdate di quotation_non_kontrak
        if (Schema::hasTable('quotation_non_kontrak') && !Schema::hasColumn('quotation_non_kontrak', 'is_backdate')) {
            Schema::table('quotation_non_kontrak', function (Blueprint $table) {
                $table->boolean('is_backdate')->default(0)->index()->after('flag_status');
            });
        }

        // 2. Tambah is_backdate di quotation_kontrak_h
        if (Schema::hasTable('quotation_kontrak_h') && !Schema::hasColumn('quotation_kontrak_h', 'is_backdate')) {
            Schema::table('quotation_kontrak_h', function (Blueprint $table) {
                $table->boolean('is_backdate')->default(0)->index()->after('flag_status');
            });
        }

        // 3. Buat tabel jadwal_backdate (duplikat full dari jadwal)
        if (!Schema::hasTable('jadwal_backdate') && Schema::hasTable('jadwal')) {
            DB::statement("CREATE TABLE `jadwal_backdate` LIKE `jadwal`");

            Schema::table('jadwal_backdate', function (Blueprint $table) {
                if (!Schema::hasColumn('jadwal_backdate', 'original_jadwal_id')) {
                    $table->unsignedBigInteger('original_jadwal_id')->nullable()->after('id')->index();
                }
                if (!Schema::hasColumn('jadwal_backdate', 'backdate_order_id')) {
                    $table->unsignedBigInteger('backdate_order_id')->nullable()->after('original_jadwal_id')->index();
                }
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('jadwal_backdate');

        if (Schema::hasTable('quotation_kontrak_h') && Schema::hasColumn('quotation_kontrak_h', 'is_backdate')) {
            Schema::table('quotation_kontrak_h', function (Blueprint $table) {
                $table->dropColumn('is_backdate');
            });
        }

        if (Schema::hasTable('quotation_non_kontrak') && Schema::hasColumn('quotation_non_kontrak', 'is_backdate')) {
            Schema::table('quotation_non_kontrak', function (Blueprint $table) {
                $table->dropColumn('is_backdate');
            });
        }
    }
}

