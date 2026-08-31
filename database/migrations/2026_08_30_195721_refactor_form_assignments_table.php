<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Cada paso está guardado con un chequeo de existencia: MySQL no revierte
     * DDL si una sentencia posterior de la misma migración falla, así que
     * esta migración debe poder resumirse desde cualquier punto intermedio.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('form_assignments', 'uuid')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->uuid('uuid')->nullable()->after('id');
            });
        }
        if (! Schema::hasColumn('form_assignments', 'respondent_type')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->string('respondent_type', 32)->nullable()->after('user_id');
            });
        }
        if (! Schema::hasColumn('form_assignments', 'guest_respondent_id')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->uuid('guest_respondent_id')->nullable()->after('user_id');
            });
        }
        if (! Schema::hasColumn('form_assignments', 'status')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->string('status', 20)->nullable()->default('pending')->after('submitted_at');
            });
        }
        if (! Schema::hasColumn('form_assignments', 'form_publication_audience_id')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->unsignedBigInteger('form_publication_audience_id')->nullable()->after('status');
            });
        }

        // whereNull('uuid'): permite resumir el backfill si un intento
        // anterior ya alcanzó a completar parte de las filas.
        DB::table('form_assignments')->whereNull('uuid')->orderBy('id')->chunkById(200, function ($assignments) {
            foreach ($assignments as $assignment) {
                $status = match (true) {
                    $assignment->submitted_at !== null => 'submitted',
                    $assignment->started_at !== null => 'started',
                    default => 'pending',
                };

                DB::table('form_assignments')
                    ->where('id', $assignment->id)
                    ->update([
                        'uuid' => (string) Str::uuid(),
                        'respondent_type' => 'user',
                        'status' => $status,
                    ]);
            }
        });

        Schema::table('form_assignments', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->change();
            $table->string('respondent_type', 32)->nullable(false)->change();
            $table->string('status', 20)->nullable(false)->default('pending')->change();
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        if (! Schema::hasIndex('form_assignments', 'uq_form_assignments_uuid')) {
            Schema::table('form_assignments', fn (Blueprint $table) => $table->unique('uuid', 'uq_form_assignments_uuid'));
        }
        if (! Schema::hasIndex('form_assignments', 'uq_form_assignments_publication_guest')) {
            Schema::table('form_assignments', fn (Blueprint $table) => $table->unique(
                ['form_publication_id', 'guest_respondent_id'],
                'uq_form_assignments_publication_guest',
            ));
        }
        if (! Schema::hasIndex('form_assignments', 'idx_form_assignments_publication_status')) {
            Schema::table('form_assignments', fn (Blueprint $table) => $table->index(
                ['form_publication_id', 'status'],
                'idx_form_assignments_publication_status',
            ));
        }
        if (! Schema::hasIndex('form_assignments', 'idx_form_assignments_company_guest')) {
            Schema::table('form_assignments', fn (Blueprint $table) => $table->index(
                ['company_id', 'guest_respondent_id'],
                'idx_form_assignments_company_guest',
            ));
        }
        if (! Schema::hasIndex('form_assignments', 'idx_form_assignments_status_submitted')) {
            Schema::table('form_assignments', fn (Blueprint $table) => $table->index(
                ['status', 'submitted_at'],
                'idx_form_assignments_status_submitted',
            ));
        }

        if (! Schema::hasIndex('form_assignments', 'fk_form_assignments_guest_respondent')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->foreign('guest_respondent_id', 'fk_form_assignments_guest_respondent')
                    ->references('id')->on('guest_respondents')
                    ->nullOnDelete();
            });
        }
        if (! Schema::hasIndex('form_assignments', 'fk_form_assignments_audience')) {
            Schema::table('form_assignments', function (Blueprint $table) {
                $table->foreign('form_publication_audience_id', 'fk_form_assignments_audience')
                    ->references('id')->on('form_publication_audiences')
                    ->nullOnDelete();
            });
        }

        // No se agrega un CHECK de exclusividad aquí: MySQL 8 rechaza un
        // CHECK sobre una columna que también tiene una FK con ON DELETE
        // SET NULL (error 3823) — guest_respondent_id usa nullOnDelete a
        // propósito, para no perder la asignación histórica si se borra el
        // invitado. La exclusividad user/guest se valida en el modelo/servicios.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_assignments', function (Blueprint $table) {
            $table->dropForeign('fk_form_assignments_guest_respondent');
            $table->dropForeign('fk_form_assignments_audience');
            $table->dropUnique('uq_form_assignments_uuid');
            $table->dropUnique('uq_form_assignments_publication_guest');
            $table->dropIndex('idx_form_assignments_publication_status');
            $table->dropIndex('idx_form_assignments_company_guest');
            $table->dropIndex('idx_form_assignments_status_submitted');
            $table->dropColumn(['uuid', 'respondent_type', 'guest_respondent_id', 'status', 'form_publication_audience_id']);
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });
    }
};
