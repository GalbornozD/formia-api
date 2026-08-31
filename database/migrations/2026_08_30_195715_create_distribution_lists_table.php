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
        Schema::create('distribution_lists', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid');
            $table->unsignedBigInteger('company_id');
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->foreign('company_id', 'fk_distribution_lists_company')
                ->references('id')->on('companies')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_distribution_lists_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_distribution_lists_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique('uuid', 'uq_distribution_lists_uuid');
            $table->unique(['company_id', 'name'], 'uq_distribution_lists_company_name');
            $table->index(['company_id', 'status'], 'idx_distribution_lists_company_status');

            $table->comment('Listas de distribución reutilizables para asignar publicaciones a grupos de usuarios y/o invitados.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_lists');
    }
};
