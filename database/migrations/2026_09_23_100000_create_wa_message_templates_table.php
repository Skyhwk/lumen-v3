<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WA message templates (recruitment candidate-facing) + variants + send log.
 * Idempotent: Schema::hasTable / Schema::hasColumn di semua langkah.
 *
 * Isi data: php artisan db:seed --class=WaMessageTemplateSeeder
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->ensureTemplatesTable();
        $this->ensureVariantsTable();
        $this->ensureSendsTable();
    }

    private function ensureTemplatesTable(): void
    {
        if (!Schema::hasTable('wa_message_templates')) {
            Schema::create('wa_message_templates', function (Blueprint $table) {
                $table->id();
                $table->string('code', 80)->unique();
                $table->string('name', 150);
                $table->string('module', 50)->default('recruitment');
                $table->json('variables')->nullable();
                $table->text('description')->nullable();
                $table->string('pick_strategy', 30)->default('weighted_random');
                $table->unsignedSmallInteger('cooldown_hours')->default(72);
                $table->boolean('is_active')->default(true);
                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();
                $table->index(['module', 'is_active'], 'wa_msg_tpl_module_active_idx');
            });

            return;
        }

        Schema::table('wa_message_templates', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_message_templates', 'code')) {
                $table->string('code', 80)->unique();
            }
            if (!Schema::hasColumn('wa_message_templates', 'name')) {
                $table->string('name', 150);
            }
            if (!Schema::hasColumn('wa_message_templates', 'module')) {
                $table->string('module', 50)->default('recruitment');
            }
            if (!Schema::hasColumn('wa_message_templates', 'variables')) {
                $table->json('variables')->nullable();
            }
            if (!Schema::hasColumn('wa_message_templates', 'description')) {
                $table->text('description')->nullable();
            }
            if (!Schema::hasColumn('wa_message_templates', 'pick_strategy')) {
                $table->string('pick_strategy', 30)->default('weighted_random');
            }
            if (!Schema::hasColumn('wa_message_templates', 'cooldown_hours')) {
                $table->unsignedSmallInteger('cooldown_hours')->default(72);
            }
            if (!Schema::hasColumn('wa_message_templates', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('wa_message_templates', 'created_by')) {
                $table->string('created_by', 100)->nullable();
            }
            if (!Schema::hasColumn('wa_message_templates', 'updated_by')) {
                $table->string('updated_by', 100)->nullable();
            }
            if (!Schema::hasColumn('wa_message_templates', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('wa_message_templates', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    private function ensureVariantsTable(): void
    {
        if (!Schema::hasTable('wa_message_template_variants')) {
            Schema::create('wa_message_template_variants', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->string('label', 100)->nullable();
                $table->text('body');
                $table->unsignedSmallInteger('weight')->default(1);
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('use_count')->default(0);
                $table->dateTime('last_used_at')->nullable();
                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();
                $table->timestamps();
                $table->foreign('template_id', 'wa_msg_var_tpl_fk')
                    ->references('id')
                    ->on('wa_message_templates')
                    ->onDelete('cascade');
                $table->index(['template_id', 'is_active'], 'wa_msg_var_tpl_active_idx');
            });

            return;
        }

        Schema::table('wa_message_template_variants', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_message_template_variants', 'template_id')) {
                $table->unsignedBigInteger('template_id');
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'label')) {
                $table->string('label', 100)->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'body')) {
                $table->text('body');
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'weight')) {
                $table->unsignedSmallInteger('weight')->default(1);
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'is_active')) {
                $table->boolean('is_active')->default(true);
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'use_count')) {
                $table->unsignedInteger('use_count')->default(0);
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'last_used_at')) {
                $table->dateTime('last_used_at')->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'created_by')) {
                $table->string('created_by', 100)->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'updated_by')) {
                $table->string('updated_by', 100)->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_variants', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    private function ensureSendsTable(): void
    {
        if (!Schema::hasTable('wa_message_template_sends')) {
            Schema::create('wa_message_template_sends', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('template_id');
                $table->unsignedBigInteger('variant_id');
                $table->string('phone', 32);
                $table->string('context_type', 50)->nullable();
                $table->string('context_id', 64)->nullable();
                $table->dateTime('sent_at');
                $table->timestamps();
                $table->foreign('template_id', 'wa_msg_send_tpl_fk')
                    ->references('id')
                    ->on('wa_message_templates')
                    ->onDelete('cascade');
                $table->foreign('variant_id', 'wa_msg_send_var_fk')
                    ->references('id')
                    ->on('wa_message_template_variants')
                    ->onDelete('cascade');
                $table->index('phone', 'wa_msg_send_phone_idx');
                $table->index(['phone', 'template_id', 'sent_at'], 'wa_msg_send_phone_tpl_sent_idx');
            });

            return;
        }

        Schema::table('wa_message_template_sends', function (Blueprint $table) {
            if (!Schema::hasColumn('wa_message_template_sends', 'template_id')) {
                $table->unsignedBigInteger('template_id');
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'variant_id')) {
                $table->unsignedBigInteger('variant_id');
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'phone')) {
                $table->string('phone', 32);
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'context_type')) {
                $table->string('context_type', 50)->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'context_id')) {
                $table->string('context_id', 64)->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'sent_at')) {
                $table->dateTime('sent_at');
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'created_at')) {
                $table->timestamp('created_at')->nullable();
            }
            if (!Schema::hasColumn('wa_message_template_sends', 'updated_at')) {
                $table->timestamp('updated_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('wa_message_template_sends')) {
            Schema::drop('wa_message_template_sends');
        }
        if (Schema::hasTable('wa_message_template_variants')) {
            Schema::drop('wa_message_template_variants');
        }
        if (Schema::hasTable('wa_message_templates')) {
            Schema::drop('wa_message_templates');
        }
    }
};
