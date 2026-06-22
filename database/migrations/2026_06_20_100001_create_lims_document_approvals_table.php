<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLimsDocumentApprovalsTable extends Migration
{
    public function up()
    {
        Schema::create('lims_document_approvals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lims_document_id');
            $table->string('action', 20);
            $table->string('nama', 255);
            $table->string('jabatan', 255)->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->string('approved_by', 255)->nullable();
            $table->unsignedSmallInteger('step')->default(0);
            $table->boolean('is_active')->default(true);

            $table->index(['lims_document_id', 'action', 'is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('lims_document_approvals');
    }
}
