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
        Schema::create('form_publication_audiences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('form_publication_id');
            $table->string('respondent_type', 32);
            $table->boolean('is_current')->default(true);
            $table->unsignedInteger('recipients_count')->default(0);
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->dateTime('resolved_at')->useCurrent();
            $table->dateTime('created_at')->useCurrent();

            $table->foreign('company_id', 'fk_fpa_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('form_publication_id', 'fk_fpa_publication')
                ->references('id')->on('form_publications')
                ->cascadeOnDelete();
            $table->foreign('resolved_by', 'fk_fpa_resolved_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique('uuid', 'uq_fpa_uuid');
            $table->index(['form_publication_id', 'is_current'], 'idx_fpa_publication_current');

            $table->comment('Snapshot histórico de cada resolución/materialización de audiencia de una publicación.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_publication_audiences');
    }
};
