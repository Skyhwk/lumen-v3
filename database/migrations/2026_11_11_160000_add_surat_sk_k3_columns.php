<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSuratSkK3Columns extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('ticket_sk_k3')) {
            return;
        }

        if (!Schema::hasColumn('ticket_sk_k3', 'no_document_surat')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->string('no_document_surat', 80)->nullable()->after('dokumentasi');
            });
        }

        if (!Schema::hasColumn('ticket_sk_k3', 'filename_surat')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->string('filename_surat', 255)->nullable()->after('no_document_surat');
            });
        }
    }

    public function down()
    {
        if (!Schema::hasTable('ticket_sk_k3')) {
            return;
        }

        if (Schema::hasColumn('ticket_sk_k3', 'filename_surat')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->dropColumn('filename_surat');
            });
        }

        if (Schema::hasColumn('ticket_sk_k3', 'no_document_surat')) {
            Schema::table('ticket_sk_k3', function (Blueprint $table) {
                $table->dropColumn('no_document_surat');
            });
        }
    }
}
