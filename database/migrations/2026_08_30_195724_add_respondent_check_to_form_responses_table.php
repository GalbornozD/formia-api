<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Intencionalmente no-op: se había planeado un CHECK de exclusividad
     * respondent_type/user_id/guest_respondent_id, pero MySQL 8 rechaza un
     * CHECK sobre una columna que también tiene una FK con ON DELETE SET
     * NULL (error 3823) — user_id y guest_respondent_id usan nullOnDelete a
     * propósito, para no perder la respuesta histórica si se borra el
     * usuario/invitado. La regla ya se cumple en FormResponseService y se
     * valida ahí, no a nivel de BD.
     */
    public function up(): void {}

    /**
     * Reverse the migrations.
     */
    public function down(): void {}
};
