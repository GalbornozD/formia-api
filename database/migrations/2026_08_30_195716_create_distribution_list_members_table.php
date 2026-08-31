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
        Schema::create('distribution_list_members', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('distribution_list_id');
            $table->string('member_type', 10);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->uuid('guest_respondent_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->unsignedBigInteger('created_by')->nullable();

            $table->foreign('distribution_list_id', 'fk_dlm_list')
                ->references('id')->on('distribution_lists')
                ->cascadeOnDelete();
            $table->foreign('user_id', 'fk_dlm_user')
                ->references('id')->on('users')
                ->cascadeOnDelete();
            $table->foreign('guest_respondent_id', 'fk_dlm_guest_respondent')
                ->references('id')->on('guest_respondents')
                ->cascadeOnDelete();
            $table->foreign('created_by', 'fk_dlm_created_by')
                ->references('id')->on('users')
                ->nullOnDelete();

            // Los NULL no colisionan entre sí en MySQL: cada índice único solo
            // restringe duplicados dentro de su propio tipo de miembro.
            $table->unique(['distribution_list_id', 'user_id'], 'uq_dlm_list_user');
            $table->unique(['distribution_list_id', 'guest_respondent_id'], 'uq_dlm_list_guest');
            $table->index(['distribution_list_id', 'member_type'], 'idx_dlm_list_type');

            $table->comment('Miembros de una lista de distribución: exactamente un usuario o un invitado por fila, nunca ambos.');
        });

        // SQLite (usado en tests, DB_CONNECTION=sqlite en phpunit.xml) no
        // soporta ALTER TABLE ... ADD CONSTRAINT: el CHECK solo se agrega
        // contra MySQL, que es el motor real de esta tabla.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(<<<'SQL'
                ALTER TABLE distribution_list_members
                ADD CONSTRAINT chk_dlm_member_exclusive CHECK (
                    (member_type = 'user' AND user_id IS NOT NULL AND guest_respondent_id IS NULL)
                    OR (member_type = 'guest' AND guest_respondent_id IS NOT NULL AND user_id IS NULL)
                )
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('distribution_list_members');
    }
};
