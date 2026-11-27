<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddEnablePhonebookToCiscoPhoneConfigs extends Migration
{
    public function up()
    {
        if (Schema::hasTable('cisco_phone_configs') && !Schema::hasColumn('cisco_phone_configs', 'enable_phonebook')) {
            Schema::table('cisco_phone_configs', function (Blueprint $table) {
                $table->boolean('enable_phonebook')->default(false)->after('is_active');
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('cisco_phone_configs') && Schema::hasColumn('cisco_phone_configs', 'enable_phonebook')) {
            Schema::table('cisco_phone_configs', function (Blueprint $table) {
                $table->dropColumn('enable_phonebook');
            });
        }
    }
}