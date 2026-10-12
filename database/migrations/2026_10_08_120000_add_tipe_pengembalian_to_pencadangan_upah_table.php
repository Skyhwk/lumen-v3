<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('pencadangan_upah', 'tipe_pengembalian')) {
            Schema::table('pencadangan_upah', function (Blueprint $table) {
                // Preserve the existing refund flow for legacy records.
                $table->string('tipe_pengembalian', 30)
                    ->default('setelah_potongan')
                    ->comment('setelah_potongan atau setelah_kontrak');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('pencadangan_upah', 'tipe_pengembalian')) {
            Schema::table('pencadangan_upah', function (Blueprint $table) {
                $table->dropColumn('tipe_pengembalian');
            });
        }
    }
};
