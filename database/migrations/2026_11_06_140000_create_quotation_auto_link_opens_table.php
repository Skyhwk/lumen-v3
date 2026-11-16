<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('quotation_auto_link_opens')) {
            return;
        }

        Schema::create('quotation_auto_link_opens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('quotation_id')->index();
            $table->string('no_document', 191)->index();
            $table->unsignedBigInteger('promo_id')->nullable()->index();
            $table->string('kode_promo', 100)->nullable();
            $table->string('nama_perusahaan', 500)->nullable();
            $table->string('nama_pic_order', 255)->nullable();
            $table->string('email_pic_order', 255)->nullable();
            $table->string('no_telpon_pic_order', 50)->nullable();
            $table->unsignedInteger('sales_id')->nullable()->index();
            $table->dateTime('first_opened_at');
            $table->dateTime('last_opened_at');
            $table->unsignedInteger('open_count')->default(1);
            $table->string('last_ip_address', 45)->nullable();
            $table->text('last_user_agent')->nullable();
            $table->timestamps();

            $table->unique('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_auto_link_opens');
    }
};
