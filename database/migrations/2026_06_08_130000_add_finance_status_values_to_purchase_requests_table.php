<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddFinanceStatusValuesToPurchaseRequestsTable extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Distributed'
        ) NULL");
    }

    public function down()
    {
        DB::statement("UPDATE purchase_requests SET finance_status = 'Waiting Process' WHERE finance_status IN ('Waiting to Create PO', 'PO Created')");

        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Distributed'
        ) NULL");
    }
}
