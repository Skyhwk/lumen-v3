<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddForecastOrderToQsdForecastTransactionLog extends Migration
{
    public function up()
    {
        Schema::table('qsd_forecast_transaction_log', function (Blueprint $table) {
            // true = penawaran ini sudah pindah jadi order (di-delete dari forecast_sp)
            $table->boolean('forecast_order')->default(false)->after('total')
                  ->comment('true jika penawaran sudah menjadi order dan dihapus dari forecast_sp');
        });
    }

    public function down()
    {
        Schema::table('qsd_forecast_transaction_log', function (Blueprint $table) {
            $table->dropColumn('forecast_order');
        });
    }
}
