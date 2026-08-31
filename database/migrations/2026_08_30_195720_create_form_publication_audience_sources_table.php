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
        if (Schema::hasTable('form_publication_audience_sources')) {
            return;
        }

        Schema::create('form_publication_audience_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_publication_audience_id');
            $table->string('source_type', 20);
            $table->unsignedBigInteger('distribution_list_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('guest_respondent_id')->nullable();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('form_publication_audience_id', 'fk_fpas_audience')
                ->references('id')->on('form_publication_audiences')
                ->cascadeOnDelete();
            $table->foreign('distribution_list_id', 'fk_fpas_distribution_list')
                ->references('id')->on('distribution_lists')
                ->nullOnDelete();
            $table->foreign('user_id', 'fk_fpas_user')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('guest_respondent_id', 'fk_fpas_guest_respondent')
                ->references('id')->on('guest_respondents')
                ->nullOnDelete();

            $table->index(['form_publication_audience_id', 'source_type'], 'idx_fpas_audience_type');

            $table->comment('Receta de selección de una audiencia: qué lista/usuario/invitado (o "todos") se eligió al publicar.');
        });

        // No se agrega un CHECK de "forma" aquí: MySQL 8 rechaza un CHECK
        // sobre una columna que también tiene una FK con ON DELETE SET NULL
        // (error 3823) — distribution_list_id/user_id/guest_respondent_id
        // usan nullOnDelete precisamente para preservar el historial de
        // audiencia aunque se borre la lista/usuario/invitado. La forma
        // correcta por source_type queda validada en PublicationAudienceService.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_publication_audience_sources');
    }
};
