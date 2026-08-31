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
        Schema::create('form_publications', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('form_type_id');
            $table->unsignedBigInteger('form_type_version_id');
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->string('access_mode', 32);
            $table->string('guest_mode', 32)->default('anonymous');
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->boolean('allow_draft')->default(true);
            $table->boolean('allow_edit_after_submit')->default(false);
            $table->boolean('show_progress')->default(true);
            $table->boolean('show_question_numbers')->default(true);
            $table->unsignedSmallInteger('max_responses_per_respondent')->nullable();
            $table->string('thank_you_title', 150)->nullable();
            $table->text('thank_you_description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('company_id', 'fk_form_publications_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('form_type_id', 'fk_form_publications_form_type')
                ->references('id')->on('form_types')
                ->restrictOnDelete();
            $table->foreign('form_type_version_id', 'fk_form_publications_version')
                ->references('id')->on('form_type_versions')
                ->restrictOnDelete();
            $table->foreign('created_by', 'fk_form_publications_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_form_publications_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique('uuid', 'uq_form_publications_uuid');
            $table->unique(['company_id', 'slug'], 'uq_form_publications_company_slug');
            $table->index(['company_id', 'is_active', 'starts_at', 'ends_at'], 'idx_form_publications_availability');
            $table->index(['form_type_id', 'form_type_version_id'], 'idx_form_publications_form_version');

            $table->comment('Publicaciones de versiones de formularios listas para ser respondidas.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_publications');
    }
};
