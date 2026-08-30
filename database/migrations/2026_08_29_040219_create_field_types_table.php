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
        Schema::create('field_types', function (Blueprint $table) {
            $table->smallIncrements('id');
            $table->string('code', 50);
            $table->string('name', 100);
            $table->boolean('has_options')->default(false);
            $table->boolean('is_container')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('created_by', 'fk_field_types_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_field_types_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique('code', 'uq_field_types_code');
            $table->index('is_active', 'idx_field_types_is_active');

            $table->comment('Catalogo extensible de tipos de campo disponibles para el constructor.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('field_types');
    }
};
