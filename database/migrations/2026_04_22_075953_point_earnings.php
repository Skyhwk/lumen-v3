<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class PointEarnings extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('point_earnings', function (Blueprint $table) {
            $table->id();
            $table->string('customer_id')->index();
        
            $table->string('source_type')->nullable(); // invoice
            $table->string('source_id')->nullable();
        
            $table->bigInteger('points');
            $table->bigInteger('claimed_points')->default(0);
            $table->bigInteger('expired_points')->default(0);
        
            $table->timestamp('earned_at')->index();
            $table->timestamp('claim_expired_at')->index();
            $table->timestamp('tier_expired_at')->index();
        
            $table->timestamps();
        
            $table->index(['customer_id', 'claim_expired_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('point_earnings');
    }
}
