<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AlterCsTicketsCustomerIdToString extends Migration
{
    protected $connection = 'portal_customer';

    public function up()
    {
        if (!Schema::connection($this->connection)->hasTable('cs_tickets')) {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE `cs_tickets` MODIFY `customer_id` VARCHAR(30) NOT NULL'
        );
    }

    public function down()
    {
        if (!Schema::connection($this->connection)->hasTable('cs_tickets')) {
            return;
        }

        DB::connection($this->connection)->statement(
            'ALTER TABLE `cs_tickets` MODIFY `customer_id` BIGINT UNSIGNED NOT NULL'
        );
    }
}
