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
        Schema::create('form_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('form_publication_id');
            $table->unsignedBigInteger('form_type_version_id');
            $table->unsignedBigInteger('form_assignment_id')->nullable();
            $table->string('respondent_type', 32);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('guest_respondent_id')->nullable();
            $table->string('status', 32)->default('draft');
            $table->dateTime('started_at')->nullable();
            $table->dateTime('last_saved_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->char('access_token_hash', 64)->nullable();
            $table->string('locale', 10)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('company_id', 'fk_form_responses_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('form_publication_id', 'fk_form_responses_publication')
                ->references('id')->on('form_publications')
                ->restrictOnDelete();
            $table->foreign('form_type_version_id', 'fk_form_responses_version')
                ->references('id')->on('form_type_versions')
                ->restrictOnDelete();
            $table->foreign('form_assignment_id', 'fk_form_responses_assignment')
                ->references('id')->on('form_assignments')
                ->nullOnDelete();
            $table->foreign('user_id', 'fk_form_responses_user')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('guest_respondent_id', 'fk_form_responses_guest')
                ->references('id')->on('guest_respondents')
                ->nullOnDelete();

            $table->index(['company_id', 'status'], 'idx_form_responses_company_status');
            $table->index(['form_publication_id', 'status'], 'idx_form_responses_publication_status');
            $table->index(['form_publication_id', 'user_id'], 'idx_form_responses_publication_user');
            $table->index(['form_publication_id', 'guest_respondent_id'], 'idx_form_responses_publication_guest');
            $table->index('submitted_at', 'idx_form_responses_submitted_at');

            $table->comment('Borradores y envios finales de formularios publicados.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_responses');
    }
};
