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
        Schema::create('roles', function (Blueprint $table) {
            $table->tinyIncrements('id');
            $table->string('name', 50);
            $table->string('description', 255)->nullable();
            $table->boolean('status')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
            // Las FK created_by/updated_by -> users se agregan en la migración de
            // `users`, porque users todavía no existe en este punto.
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->unique('name', 'uq_roles_name');

            $table->comment('Catálogo global de roles. El rol se asigna directo a users.role_id.');
        });

        // Catálogo fijo: rol único por usuario (nunca por empresa). Por ahora
        // solo master y administrador tienen lógica de autorización real;
        // supervisor/viewer quedan reservados para más adelante.
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'master', 'description' => 'Acceso total al sistema, sin restricción de empresa'],
            ['id' => 2, 'name' => 'administrador', 'description' => 'Administra usuarios y configuración dentro de su empresa'],
            ['id' => 3, 'name' => 'supervisor', 'description' => 'Reservado para uso futuro'],
            ['id' => 4, 'name' => 'viewer', 'description' => 'Reservado para uso futuro'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
