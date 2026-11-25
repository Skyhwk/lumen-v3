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
            if (!Schema::hasColumn('observasi_k3', 'file_pengesahan_pdf')) {
                $table->string('file_pengesahan_pdf')->nullable()->after('snapshot_pengesahan');
            }
            if (!Schema::hasColumn('observasi_k3', 'email_pengesahan_sent_at')) {
                $table->timestamp('email_pengesahan_sent_at')->nullable()->after('file_pengesahan_pdf');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            foreach (['email_pengesahan_sent_at', 'file_pengesahan_pdf'] as $col) {
                if (Schema::hasColumn('observasi_k3', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
