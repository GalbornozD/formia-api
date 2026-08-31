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
        Schema::create('form_assignment_sources', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_assignment_id');
            $table->unsignedBigInteger('form_publication_audience_source_id');
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('form_assignment_id', 'fk_fas_assignment')
                ->references('id')->on('form_assignments')
                ->cascadeOnDelete();
            $table->foreign('form_publication_audience_source_id', 'fk_fas_source')
                ->references('id')->on('form_publication_audience_sources')
                ->cascadeOnDelete();

            $table->unique(['form_assignment_id', 'form_publication_audience_source_id'], 'uq_fas_assignment_source');

            $table->comment('Traza qué fuentes (listas/selección específica) contribuyeron a cada asignación — un destinatario puede venir de varias listas.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_assignment_sources');
    }
};
