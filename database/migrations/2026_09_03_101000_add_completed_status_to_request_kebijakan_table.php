<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddCompletedStatusToRequestKebijakanTable extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected',
            'completed'
        ) NULL");
    }

    public function down(): void
    {
        if (!Schema::hasTable('request_kebijakan')) {
            return;
        }

        DB::statement("UPDATE request_kebijakan SET status = 'approved' WHERE status = 'completed'");
        DB::statement("ALTER TABLE request_kebijakan MODIFY status ENUM(
            'waiting_approval',
            'approved',
            'on_process',
            'rejected'
        ) NULL");
    }
}
