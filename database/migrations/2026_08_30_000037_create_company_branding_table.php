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
        Schema::create('company_branding', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');

            $table->string('logo_path', 500)->nullable();
            $table->string('logo_dark_path', 500)->nullable();
            $table->string('logo_compact_path', 500)->nullable();
            $table->string('favicon_path', 500)->nullable();

            $table->string('primary_color', 7)->default('#2563EB');
            $table->string('secondary_color', 7)->default('#0F172A');
            $table->string('accent_color', 7)->nullable();

            $table->enum('theme_mode', ['light', 'dark', 'system'])->default('light');

            $table->unsignedInteger('version')->default(1);

            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique('company_id', 'uq_company_branding_company');

            $table->foreign('company_id', 'fk_company_branding_company')
                ->references('id')->on('companies');
            $table->foreign('created_by', 'fk_company_branding_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_company_branding_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->comment('Branding visual por empresa (logos, colores, tema). Una fila por empresa.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_branding');
    }
};
