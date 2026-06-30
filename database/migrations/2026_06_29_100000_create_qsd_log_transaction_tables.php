<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('qsd_revenue_transaction_log')) {
            Schema::create('qsd_revenue_transaction_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_order', 50)->index();
                $table->string('periode', 20)->comment('Format YYYY-MM dari tanggal_kelompok');
                $table->decimal('revenue', 18, 2)->default(0);
                $table->enum('status', ['penambahan', 'pengurangan']);
                $table->timestamp('created_at')->useCurrent();

                $table->index('periode');
            });
        }

        if (!Schema::hasTable('qsd_forecast_transaction_log')) {
            Schema::create('qsd_forecast_transaction_log', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('no_penawaran', 50)->index();
                $table->string('periode', 20)->comment('Format YYYY-MM dari tanggal_sampling_min');
                $table->decimal('revenue_forecast', 18, 2)->default(0);
                $table->enum('status', ['penambahan', 'pengurangan']);
                $table->timestamp('created_at')->useCurrent();

                $table->index('periode');
            });
        }

        if (!Schema::hasTable('qsd_revenue_snapshot')) {
            Schema::create('qsd_revenue_snapshot', function (Blueprint $table) {
                $table->string('uuid', 512)->primary();
                $table->string('no_order', 50);
                $table->string('periode_kontrak', 100)->nullable();
                $table->string('bulan_periode', 7);
                $table->decimal('total_revenue', 18, 2)->default(0);
                $table->timestamp('updated_at')->useCurrent();
            });
        }

        if (!Schema::hasTable('qsd_forecast_snapshot')) {
            Schema::create('qsd_forecast_snapshot', function (Blueprint $table) {
                $table->string('uuid', 512)->primary();
                $table->string('no_penawaran', 50);
                $table->string('periode_kontrak', 100)->nullable();
                $table->string('bulan_periode', 7);
                $table->decimal('revenue_forecast', 18, 2)->default(0);
                $table->timestamp('updated_at')->useCurrent();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('qsd_forecast_snapshot');
        Schema::dropIfExists('qsd_revenue_snapshot');
        Schema::dropIfExists('qsd_forecast_transaction_log');
        Schema::dropIfExists('qsd_revenue_transaction_log');
    }
};
