<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PointClaimDetails extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('point_claim_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('claim_id')->index();
            $table->unsignedBigInteger('earning_id')->index();
            $table->bigInteger('points_used');
        
            $table->timestamps();
        
            $table->foreign('claim_id')->references('id')->on('point_claims')->cascadeOnDelete();
            $table->foreign('earning_id')->references('id')->on('point_earnings')->cascadeOnDelete();
        
            $table->index(['earning_id', 'claim_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('point_claim_details');
    }
}
