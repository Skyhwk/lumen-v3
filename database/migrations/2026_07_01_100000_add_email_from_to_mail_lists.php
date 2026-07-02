<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Services\MailListSubscriberService;

class AddEmailFromToMailLists extends Migration
{
    public function up()
    {
        if (Schema::hasTable('mail_lists') && !Schema::hasColumn('mail_lists', 'email_from')) {
            Schema::table('mail_lists', function (Blueprint $table) {
                $table->string('email_from', 50)->default('fromPromoSales')->after('email_to');
            });
        }

        try {
            app(MailListSubscriberService::class)->syncIfMissing(['sales.info@intilab.com']);
        } catch (\Throwable $e) {
            // API milis mungkin tidak tersedia saat migrate; bisa ditambahkan manual lewat Data Subscriber
        }
    }

    public function down()
    {
        if (Schema::hasTable('mail_lists') && Schema::hasColumn('mail_lists', 'email_from')) {
            Schema::table('mail_lists', function (Blueprint $table) {
                $table->dropColumn('email_from');
            });
        }
    }
}
