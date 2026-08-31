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
        Schema::create('form_response_answers', function (Blueprint $table) {
            $table->id();
            $table->uuid('form_response_id');
            $table->unsignedBigInteger('form_field_id');
            $table->json('value_json')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('form_response_id', 'fk_form_response_answers_response')
                ->references('id')->on('form_responses')
                ->cascadeOnDelete();
            $table->foreign('form_field_id', 'fk_form_response_answers_field')
                ->references('id')->on('form_fields')
                ->restrictOnDelete();

            $table->unique(['form_response_id', 'form_field_id'], 'uq_form_response_answers_response_field');
            $table->index('form_field_id', 'idx_form_response_answers_field');

            $table->comment('Valores JSON de cada respuesta, enlazados a campos versionados.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_response_answers');
    }
};
