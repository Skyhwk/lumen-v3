<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddWaitingPurchaseProcessToFinanceStatusEnum extends Migration
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
            'Waiting Purchase Process',
            'Waiting Vendor Receipt',
            'Waiting User Receipt',
            'Distributing',
            'Distributed',
            'Void'
        ) NULL");
    }

    public function down()
    {
        DB::statement("UPDATE purchase_requests SET finance_status = 'On Process' WHERE finance_status = 'Waiting Purchase Process'");

        DB::statement("ALTER TABLE purchase_requests MODIFY finance_status ENUM(
            'Waiting to Delegate',
            'Waiting to Create PO',
            'PO Created',
            'Rejected',
            'Waiting Process',
            'On Process',
            'Pending',
            'Waiting Vendor Receipt',
            'Waiting User Receipt',
            'Distributing',
            'Distributed',
            'Void'
        ) NULL");
    }
}
