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
        Schema::create('form_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('form_publication_id');
            $table->unsignedBigInteger('user_id');
            $table->dateTime('assigned_at')->useCurrent();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('company_id', 'fk_form_assignments_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('form_publication_id', 'fk_form_assignments_publication')
                ->references('id')->on('form_publications')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'fk_form_assignments_user')
                ->references('id')->on('users')
                ->restrictOnDelete();
            $table->foreign('created_by', 'fk_form_assignments_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(['form_publication_id', 'user_id'], 'uq_form_assignments_publication_user');
            $table->index(['company_id', 'user_id'], 'idx_form_assignments_company_user');
            $table->index(['user_id', 'submitted_at'], 'idx_form_assignments_user_status');

            $table->comment('Asignaciones opcionales de publicaciones a usuarios autenticados.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_assignments');
    }
};
