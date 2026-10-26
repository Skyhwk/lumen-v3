<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateHrLeaveBalanceLedger extends Migration
{
    public function up()
    {
        if (Schema::hasTable('hr_leave_balance_ledger')) {
            return;
        }

        Schema::create('hr_leave_balance_ledger', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('karyawan_id');
            $table->unsignedBigInteger('balance_period_id')->nullable();
            $table->string('entry_type', 32);
            $table->smallInteger('days_delta');
            $table->date('reference_date')->nullable();
            $table->string('source', 32)->default('hrd_portal');
            $table->string('external_ref', 128)->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('created_by_karyawan_id')->nullable();
            $table->string('created_by_name')->nullable();
            $table->dateTime('created_at');
            $table->boolean('is_void')->default(false);
            $table->dateTime('voided_at')->nullable();
            $table->string('voided_by_name')->nullable();
            $table->text('void_reason')->nullable();

            $table->unique('external_ref', 'hr_leave_balance_ledger_ext_ref');
            $table->index(['karyawan_id', 'balance_period_id', 'is_void'], 'hr_leave_ledger_karyawan_period');
            $table->index(['reference_date', 'entry_type'], 'hr_leave_ledger_ref_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('hr_leave_balance_ledger');
    }
}
