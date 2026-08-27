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
        Schema::create('company_user', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            // Bitmask 1=leer, 2=crear, 4=actualizar, 8=eliminar. No es el rol
            // (ese vive en users.role_id): esto es para autorización fina por
            // módulo (ej. solicitudes), a definir más adelante.
            $table->unsignedTinyInteger('permission')->default(0);
            $table->boolean('status')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->primary(['user_id', 'company_id']);

            $table->foreign('user_id', 'fk_company_user_user')
                ->references('id')->on('users')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('company_id', 'fk_company_user_company')
                ->references('id')->on('companies')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('created_by', 'fk_company_user_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_company_user_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->index('company_id', 'idx_company_user_company');
            $table->index('user_id', 'idx_company_user_user');
            $table->index('status', 'idx_company_user_status');

            $table->comment('Membresía usuario-empresa y permisos finos por empresa. El rol vive en users.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_user');
    }
};
