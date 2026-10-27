<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = ['request_quotation', 'request_quotation_kontrak_H', 'request_quotation_kontrak_D'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && !Schema::hasColumn($name, 'promo_id')) {
                $hasKodePromo = Schema::hasColumn($name, 'kode_promo');
                Schema::table($name, function (Blueprint $table) use ($hasKodePromo) {
                    $column = $table->unsignedBigInteger('promo_id')->nullable();
                    if ($hasKodePromo) {
                        $column->after('kode_promo');
                    }
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'promo_id')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropColumn('promo_id');
                });
            }
        }
    }
};
