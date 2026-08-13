<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSenderPushNameToWaMessages extends Migration
{
    public function up()
    {
        if (Schema::hasTable('wa_messages')) {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->string('sender_push_name', 255)->nullable()->after('sender_jid');
        });
        }
    }

    public function down()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn('sender_push_name');
        });
    }
}
