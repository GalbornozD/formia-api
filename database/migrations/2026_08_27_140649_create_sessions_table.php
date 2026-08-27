<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Refresh tokens con la empresa activa; no es el store de sesión de
     * Laravel (SESSION_DRIVER=file en este proyecto, ver .env) — es el
     * registro auditable de dispositivo/empresa activa por usuario.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->char('refresh_token_hash', 64);
            $table->string('user_agent', 255)->nullable();
            $table->binary('ip_address', 16)->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('user_id', 'fk_sessions_user')
                ->references('id')->on('users')
                ->onDelete('cascade');
            $table->foreign('company_id', 'fk_sessions_company')
                ->references('id')->on('companies')
                ->nullOnDelete();
            $table->foreign('created_by', 'fk_sessions_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_sessions_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('user_id', 'idx_sessions_user');
            $table->index('company_id', 'idx_sessions_company');
            $table->index('refresh_token_hash', 'idx_sessions_token_hash');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
    }
};
