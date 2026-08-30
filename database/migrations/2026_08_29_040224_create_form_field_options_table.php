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
        Schema::create('form_field_options', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_field_id');
            $table->string('option_value', 255);
            $table->string('option_label', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('form_field_id', 'fk_form_field_options_field')
                ->references('id')->on('form_fields')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_form_field_options_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_form_field_options_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(['form_field_id', 'option_value'], 'uq_form_field_options_field_value');
            $table->index(['form_field_id', 'sort_order'], 'idx_form_field_options_field_sort');

            $table->comment('Opciones normalizadas de los campos que admiten seleccion.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_field_options');
    }
};
