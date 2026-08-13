<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsManualToWaContacts extends Migration
{
    public function up()
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false)->after('phone');
        });
    }

    public function down()
    {
        Schema::table('wa_contacts', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
}
