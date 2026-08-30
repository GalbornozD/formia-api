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
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_type_version_id');
            $table->unsignedSmallInteger('field_type_id');
            $table->unsignedBigInteger('parent_field_id')->nullable();
            $table->string('field_key', 100);
            $table->string('label', 255);
            $table->text('description')->nullable();
            $table->string('placeholder', 255)->nullable();
            $table->json('default_value')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_readonly')->default(false);
            $table->boolean('is_hidden')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedSmallInteger('width')->default(12);
            $table->json('validation_rules')->nullable();
            $table->json('settings')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('form_type_version_id', 'fk_form_fields_version')
                ->references('id')->on('form_type_versions')
                ->restrictOnDelete();
            $table->foreign('field_type_id', 'fk_form_fields_field_type')
                ->references('id')->on('field_types')
                ->restrictOnDelete();
            $table->foreign('parent_field_id', 'fk_form_fields_parent')
                ->references('id')->on('form_fields')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_form_fields_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_form_fields_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(['form_type_version_id', 'field_key'], 'uq_form_fields_version_key');
            $table->index('form_type_version_id', 'idx_form_fields_version');
            $table->index('field_type_id', 'idx_form_fields_field_type');
            $table->index('parent_field_id', 'idx_form_fields_parent');
            $table->index(['form_type_version_id', 'sort_order'], 'idx_form_fields_version_sort');

            $table->comment('Definicion versionada y jerarquica de los campos del formulario.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
