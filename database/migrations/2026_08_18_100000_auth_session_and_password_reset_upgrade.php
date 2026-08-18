<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuthSessionAndPasswordResetUpgrade extends Migration
{
    public function up()
    {
        if (Schema::hasTable('user_token')) {
            Schema::table('user_token', function (Blueprint $table) {
                if (!Schema::hasColumn('user_token', 'platform')) {
                    $table->string('platform', 50)->nullable()->after('type');
                }
                if (!Schema::hasColumn('user_token', 'user_agent')) {
                    $table->string('user_agent', 500)->nullable()->after('platform');
                }
                if (!Schema::hasColumn('user_token', 'ip_address')) {
                    $table->string('ip_address', 45)->nullable()->after('user_agent');
                }
                if (!Schema::hasColumn('user_token', 'last_active_at')) {
                    $table->dateTime('last_active_at')->nullable()->after('ip_address');
                }
            });

            $this->extendActiveTokenExpiry();
        }

        if (!Schema::hasTable('password_reset_otps')) {
            Schema::create('password_reset_otps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('user_id');
                $table->string('email', 255);
                $table->string('otp_hash', 255);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->boolean('is_used')->default(false);
                $table->dateTime('expires_at');
                $table->dateTime('created_at');
                $table->dateTime('used_at')->nullable();
                $table->string('ip_address', 45)->nullable();

                $table->index('email');
                $table->index('user_id');
                $table->index(['email', 'is_used', 'expires_at'], 'password_reset_otps_lookup_idx');
            });
        }
    }

    private function extendActiveTokenExpiry()
    {
        $ttlDays = (int) config('auth.token_ttl_days', 365);

        DB::table('user_token')
            ->where('is_expired', false)
            ->where('expired', '>', date('Y-m-d H:i:s'))
            ->update([
                'expired' => DB::raw(sprintf(
                    "CASE WHEN DATE_ADD(create_date, INTERVAL %d DAY) > NOW() THEN DATE_ADD(create_date, INTERVAL %d DAY) ELSE DATE_ADD(NOW(), INTERVAL %d DAY) END",
                    $ttlDays,
                    $ttlDays,
                    $ttlDays
                )),
            ]);
    }

    public function down()
    {
        if (Schema::hasTable('password_reset_otps')) {
            Schema::dropIfExists('password_reset_otps');
        }

        if (Schema::hasTable('user_token')) {
            Schema::table('user_token', function (Blueprint $table) {
                $columns = ['platform', 'user_agent', 'ip_address', 'last_active_at'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('user_token', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
}
