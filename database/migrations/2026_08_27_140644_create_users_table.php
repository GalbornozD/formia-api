<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedTinyInteger('role_id');
            $table->string('email', 255);
            $table->dateTime('email_verified_at')->nullable();
            $table->string('password_hash', 255);
            $table->string('password_algorithm', 20)->default('argon2id');
            $table->dateTime('password_updated_at')->useCurrent();
            $table->string('first_name', 150);
            $table->string('last_name', 150);
            $table->char('rut_hash', 64)->nullable();
            $table->binary('rut_encrypted', 255)->nullable();
            $table->string('phone', 30)->nullable();
            $table->boolean('mfa_enabled')->default(false);
            $table->binary('mfa_secret_encrypted', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->dateTime('locked_until')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->binary('last_login_ip', 16)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('deleted_at')->nullable();

            $table->unique('uuid', 'uq_users_uuid');
            $table->unique('email', 'uq_users_email');

            $table->foreign('role_id', 'fk_users_role')
                ->references('id')->on('roles');
            $table->foreign('created_by', 'fk_users_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_users_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('role_id', 'idx_users_role');
            $table->index('status', 'idx_users_status');

            $table->comment('Identidad global del usuario. El rol vive aquí (role_id), no por empresa.');
        });

        // roles.created_by/updated_by solo pueden apuntar a users una vez que
        // la tabla existe (mismo orden que el esquema SQL entregado).
        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('created_by', 'fk_roles_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_roles_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropForeign('fk_roles_created_by');
            $table->dropForeign('fk_roles_updated_by');
        });

        Schema::dropIfExists('users');
    }
};
