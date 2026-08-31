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
        Schema::create('form_invitations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('form_publication_id');
            $table->unsignedBigInteger('form_assignment_id');
            $table->uuid('guest_respondent_id');
            $table->string('channel', 20);
            $table->string('recipient', 255)->nullable();
            $table->char('token_hash', 64);
            $table->string('status', 20)->default('pending');
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('sent_at')->nullable();
            $table->dateTime('opened_at')->nullable();
            $table->dateTime('used_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('company_id', 'fk_form_invitations_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('form_publication_id', 'fk_form_invitations_publication')
                ->references('id')->on('form_publications')
                ->cascadeOnDelete();
            $table->foreign('form_assignment_id', 'fk_form_invitations_assignment')
                ->references('id')->on('form_assignments')
                ->cascadeOnDelete();
            $table->foreign('guest_respondent_id', 'fk_form_invitations_guest_respondent')
                ->references('id')->on('guest_respondents')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_form_invitations_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique('uuid', 'uq_form_invitations_uuid');
            $table->unique('token_hash', 'uq_form_invitations_token_hash');
            $table->index(['form_publication_id', 'status'], 'idx_form_invitations_publication_status');
            $table->index('form_assignment_id', 'idx_form_invitations_assignment');

            $table->comment('Invitaciones con link personalizado por invitado; nunca se guarda el token en texto plano, solo su hash.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_invitations');
    }
};
