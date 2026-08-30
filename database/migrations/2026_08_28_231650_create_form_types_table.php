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
        Schema::create('form_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 150);
            $table->string('description', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('company_id', 'fk_form_types_company')
                ->references('id')->on('companies')
                ->onDelete('cascade')->onUpdate('cascade');
            $table->foreign('created_by', 'fk_form_types_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_form_types_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(['company_id', 'name'], 'uq_form_types_company_name');
            $table->index('company_id', 'idx_form_types_company');
            $table->index('status', 'idx_form_types_status');

            $table->comment('Catalogo de tipos de formulario definidos por cada empresa.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_types');
    }
};
