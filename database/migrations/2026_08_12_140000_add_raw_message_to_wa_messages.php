<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddRawMessageToWaMessages extends Migration
{
    public function up()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->longText('raw_message')->nullable()->after('media_filename');
        });
    }

    public function down()
    {
        Schema::table('wa_messages', function (Blueprint $table) {
            $table->dropColumn('raw_message');
        });
    }
}
