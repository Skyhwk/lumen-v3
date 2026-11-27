<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            if (!Schema::hasColumn('observasi_k3', 'no_quotation')) {
                $table->string('no_quotation')->nullable()->after('id');
            }
            if (!Schema::hasColumn('observasi_k3', 'nomor_ticket_sk_k3')) {
                $table->string('nomor_ticket_sk_k3')->nullable()->after('no_quotation');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            if (Schema::hasColumn('observasi_k3', 'nomor_ticket_sk_k3')) {
                $table->dropColumn('nomor_ticket_sk_k3');
            }
            if (Schema::hasColumn('observasi_k3', 'no_quotation')) {
                $table->dropColumn('no_quotation');
            }
        });
    }
};
