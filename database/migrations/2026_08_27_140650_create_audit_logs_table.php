<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sin updated_by/updated_at a propósito: los registros de auditoría no
     * se modifican después de insertados.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('action', 100);
            $table->string('entity', 100)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->json('details')->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('actor_user_id', 'fk_audit_logs_actor_user')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('company_id', 'fk_audit_logs_company')
                ->references('id')->on('companies')
                ->nullOnDelete();

            $table->index('actor_user_id', 'idx_audit_logs_actor_user');
            $table->index(['company_id', 'created_at'], 'idx_audit_logs_company_created_at');
            $table->index('action', 'idx_audit_logs_action');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
