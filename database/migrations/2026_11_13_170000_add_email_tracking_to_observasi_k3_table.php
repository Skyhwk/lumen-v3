<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            if (!Schema::hasColumn('observasi_k3', 'is_emailed')) {
                $table->boolean('is_emailed')->default(false)->after('file_pengesahan_pdf');
            }
            if (!Schema::hasColumn('observasi_k3', 'emailed_by')) {
                $table->string('emailed_by', 150)->nullable()->after('is_emailed');
            }
            if (!Schema::hasColumn('observasi_k3', 'emailed_at')) {
                $table->timestamp('emailed_at')->nullable()->after('emailed_by');
            }
            if (!Schema::hasColumn('observasi_k3', 'emailed_to')) {
                $table->string('emailed_to', 255)->nullable()->after('emailed_at');
            }
            if (!Schema::hasColumn('observasi_k3', 'emailed_cc')) {
                $table->string('emailed_cc', 500)->nullable()->after('emailed_to');
            }
        });

        if (Schema::hasColumn('observasi_k3', 'email_pengesahan_sent_at')) {
            DB::table('observasi_k3')
                ->whereNotNull('email_pengesahan_sent_at')
                ->where(function ($q) {
                    $q->where('is_emailed', false)->orWhereNull('is_emailed');
                })
                ->update([
                    'is_emailed' => true,
                    'emailed_at' => DB::raw('email_pengesahan_sent_at'),
                ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('observasi_k3')) {
            return;
        }

        Schema::table('observasi_k3', function (Blueprint $table) {
            foreach (['emailed_cc', 'emailed_to', 'emailed_at', 'emailed_by', 'is_emailed'] as $col) {
                if (Schema::hasColumn('observasi_k3', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
