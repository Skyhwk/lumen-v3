<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('it_asset_locations')) {
            Schema::create('it_asset_locations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('parent_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('name');
                $table->string('type', 30)->default('room'); // building, floor, room, department
                $table->json('layout_json')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('it_support_assets')) {
            Schema::create('it_support_assets', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('asset_code')->unique();
                $table->string('asset_type', 50); // PC, Laptop, Printer, Network, etc.
                $table->string('name');
                $table->string('brand')->nullable();
                $table->string('model')->nullable();
                $table->string('serial_number')->nullable()->index();
                $table->unsignedBigInteger('location_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->unsignedBigInteger('assigned_karyawan_id')->nullable()->index();
                $table->string('status', 30)->default('active'); // active, issue, maintenance, retired
                $table->json('specifications')->nullable();
                $table->json('map_position')->nullable();
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('it_asset_maintenance_logs')) {
            Schema::create('it_asset_maintenance_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_id')->index();
                $table->string('status', 30); // issue, maintenance, resolved
                $table->text('description');
                $table->string('handled_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('it_asset_maintenance_logs')) Schema::drop('it_asset_maintenance_logs');
        if (Schema::hasTable('it_support_assets')) Schema::drop('it_support_assets');
        if (Schema::hasTable('it_asset_locations')) Schema::drop('it_asset_locations');
    }
};
