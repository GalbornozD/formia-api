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
        Schema::create('company_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            $table->string('timezone', 100)->default('America/Santiago');
            $table->string('locale', 10)->default('es-CL');
            $table->string('date_format', 30)->default('DD/MM/YYYY');
            $table->string('time_format', 20)->default('HH:mm');

            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique('company_id', 'uq_company_settings_company');

            $table->foreign('company_id', 'fk_company_settings_company')
                ->references('id')->on('companies');
            $table->foreign('created_by', 'fk_company_settings_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_company_settings_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->comment('Preferencias regionales por empresa (idioma, zona horaria, formatos). Una fila por empresa.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
