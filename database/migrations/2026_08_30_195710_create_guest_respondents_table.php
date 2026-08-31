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
        Schema::create('guest_respondents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->string('name', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->char('identity_hash', 64)->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('company_id', 'fk_guest_respondents_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();

            $table->unique(['company_id', 'identity_hash'], 'uq_guest_respondents_company_identity');
            $table->index(['company_id', 'email'], 'idx_guest_respondents_company_email');

            $table->comment('Respondentes externos identificados por empresa, sin crear usuarios.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('guest_respondents');
    }
};
