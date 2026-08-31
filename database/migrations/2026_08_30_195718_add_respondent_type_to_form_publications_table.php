<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Idempotente: un intento anterior de esta migración puede haber
        // agregado la columna vía DDL (MySQL no revierte DDL si una
        // sentencia posterior falla) sin llegar a quedar registrada como
        // ejecutada.
        if (! Schema::hasColumn('form_publications', 'respondent_type')) {
            Schema::table('form_publications', function (Blueprint $table) {
                $table->string('respondent_type', 32)->nullable()->after('guest_mode');
            });
        }

        // Backfill: access_mode/guest_mode quedan deprecadas en favor de un
        // único respondent_type explícito (anonymous|guest|user). 'both' es
        // el caso ambiguo: si ya tiene asignaciones se asume 'user' (esa era
        // la intención real), si no se decide por guest_mode.
        DB::table('form_publications')->where('access_mode', 'authenticated')
            ->update(['respondent_type' => 'user']);

        DB::table('form_publications')->where('access_mode', 'guest')
            ->where('guest_mode', 'anonymous')
            ->update(['respondent_type' => 'anonymous']);

        DB::table('form_publications')->where('access_mode', 'guest')
            ->whereIn('guest_mode', ['identified', 'both'])
            ->update(['respondent_type' => 'guest']);

        DB::table('form_publications')->where('access_mode', 'both')
            ->orderBy('id')
            ->chunkById(200, function ($publications) {
                foreach ($publications as $publication) {
                    $hasAssignments = DB::table('form_assignments')
                        ->where('form_publication_id', $publication->id)
                        ->exists();

                    $respondentType = match (true) {
                        $hasAssignments => 'user',
                        $publication->guest_mode === 'anonymous' => 'anonymous',
                        default => 'guest',
                    };

                    DB::table('form_publications')
                        ->where('id', $publication->id)
                        ->update(['respondent_type' => $respondentType]);
                }
            });

        // Cualquier fila remanente sin mapear (dato inesperado) cae en 'guest'
        // por ser la opción menos permisiva que 'anonymous' y no requerir
        // autenticación como 'user'.
        DB::table('form_publications')->whereNull('respondent_type')
            ->update(['respondent_type' => 'guest']);

        Schema::table('form_publications', function (Blueprint $table) {
            $table->string('respondent_type', 32)->nullable(false)->change();

            // access_mode/guest_mode quedan deprecadas (reemplazadas por
            // respondent_type) pero no se dropean — cambio no destructivo.
            // Se vuelven nullable porque el código nuevo ya no las escribe.
            $table->string('access_mode', 32)->nullable()->change();
            $table->string('guest_mode', 32)->nullable()->default(null)->change();
        });

        if (! Schema::hasIndex('form_publications', 'idx_form_publications_respondent_type')) {
            Schema::table('form_publications', function (Blueprint $table) {
                $table->index(['company_id', 'respondent_type'], 'idx_form_publications_respondent_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_publications', function (Blueprint $table) {
            $table->dropIndex('idx_form_publications_respondent_type');
            $table->dropColumn('respondent_type');
        });
    }
};
