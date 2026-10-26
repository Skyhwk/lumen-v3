<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrLeaveBalancePeriodTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('hr_leave_balance_period')) {
            return;
        }

        Schema::create('hr_leave_balance_period', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('quota_days')->default(12);
            $table->unsignedSmallInteger('opening_used_days')->default(0);
            $table->dateTime('opening_imported_at')->nullable();
            $table->string('opening_source', 64)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['karyawan_id', 'period_start'], 'hr_leave_balance_karyawan_period');
            $table->index(['karyawan_id', 'is_active'], 'hr_leave_balance_karyawan_active');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_leave_balance_period');
    }
}
