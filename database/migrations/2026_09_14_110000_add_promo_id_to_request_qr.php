<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('request_qr') && !Schema::hasColumn('request_qr', 'promo_id')) {
            Schema::table('request_qr', function (Blueprint $table) {
                $table->unsignedBigInteger('promo_id')->nullable();
            });
        }
    }
    public function down(): void
    {
        if (Schema::hasTable('request_qr') && Schema::hasColumn('request_qr', 'promo_id')) {
            Schema::table('request_qr', function (Blueprint $table) { $table->dropColumn('promo_id'); });
        }
    }
};
