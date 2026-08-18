<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRejectAndActiveColumnsToSallaryOfferTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            if (!Schema::hasColumn('sallary_offer', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('final_sallary');
            }

            if (!Schema::hasColumn('sallary_offer', 'keterangan_reject')) {
                $table->text('keterangan_reject')->nullable()->after('is_active');
            }

            if (!Schema::hasColumn('sallary_offer', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('keterangan_reject');
            }

            if (!Schema::hasColumn('sallary_offer', 'rejected_by')) {
                $table->string('rejected_by', 255)->nullable()->after('rejected_at');
            }
        });

        if (Schema::hasColumn('sallary_offer', 'is_active')) {
            \DB::table('sallary_offer')->whereNull('is_active')->update(['is_active' => true]);
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if (!Schema::hasTable('sallary_offer')) {
            return;
        }

        Schema::table('sallary_offer', function (Blueprint $table) {
            $columns = ['rejected_by', 'rejected_at', 'keterangan_reject', 'is_active'];

            foreach ($columns as $column) {
                if (Schema::hasColumn('sallary_offer', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
