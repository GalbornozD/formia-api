<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_type_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_type_id');
            $table->unsignedInteger('version');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('published_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('form_type_id', 'fk_form_type_versions_form_type')
                ->references('id')->on('form_types')
                ->restrictOnDelete();
            $table->foreign('created_by', 'fk_form_type_versions_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();
            $table->foreign('updated_by', 'fk_form_type_versions_updated_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            $table->unique(['form_type_id', 'version'], 'uq_form_type_versions_form_version');
            $table->index('form_type_id', 'idx_form_type_versions_form');
            $table->index(
                ['form_type_id', 'is_published', 'is_active', 'version'],
                'idx_form_type_versions_publication'
            );

            $table->comment('Versiones historicas e inmutables al publicarse de cada formulario.');
        });

        DB::table('form_types')
            ->select(['id', 'created_by', 'updated_by', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, static function (Collection $formTypes): void {
                $versions = $formTypes->map(static fn (object $formType): array => [
                    'form_type_id' => $formType->id,
                    'version' => 1,
                    'is_published' => false,
                    'is_active' => true,
                    'published_at' => null,
                    'created_by' => $formType->created_by,
                    'updated_by' => $formType->updated_by ?? $formType->created_by,
                    'created_at' => $formType->created_at,
                    'updated_at' => $formType->updated_at,
                ])->all();

                DB::table('form_type_versions')->insert($versions);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_type_versions');
    }
};
