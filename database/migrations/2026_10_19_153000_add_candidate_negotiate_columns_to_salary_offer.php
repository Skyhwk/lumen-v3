<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sallary_offer')) {
            if (!Schema::hasColumn('sallary_offer', 'salary_offer_candidate')) {
                Schema::table('sallary_offer', function (Blueprint $table) {
                    $table->decimal('salary_offer_candidate', 15, 2)->nullable()->after('sallary_offer_hrd');
                });
            }

            if (!Schema::hasColumn('sallary_offer', 'tunjangan_offer_candidate')) {
                $after = Schema::hasColumn('sallary_offer', 'salary_offer_candidate')
                    ? 'salary_offer_candidate'
                    : 'sallary_offer_hrd';
                Schema::table('sallary_offer', function (Blueprint $table) use ($after) {
                    $table->decimal('tunjangan_offer_candidate', 15, 2)->nullable()->after($after);
                });
            }
        }

        if (Schema::hasTable('candidate_data_offers')
            && !Schema::hasColumn('candidate_data_offers', 'tanggal_mulai_kerja_history')
        ) {
            Schema::table('candidate_data_offers', function (Blueprint $table) {
                $table->json('tanggal_mulai_kerja_history')->nullable()->after('tanggal_mulai_kerja');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sallary_offer')) {
            $columns = [];
            if (Schema::hasColumn('sallary_offer', 'tunjangan_offer_candidate')) {
                $columns[] = 'tunjangan_offer_candidate';
            }
            if (Schema::hasColumn('sallary_offer', 'salary_offer_candidate')) {
                $columns[] = 'salary_offer_candidate';
            }
            if ($columns) {
                Schema::table('sallary_offer', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('candidate_data_offers')
            && Schema::hasColumn('candidate_data_offers', 'tanggal_mulai_kerja_history')
        ) {
            Schema::table('candidate_data_offers', function (Blueprint $table) {
                $table->dropColumn('tanggal_mulai_kerja_history');
            });
        }
    }
};
