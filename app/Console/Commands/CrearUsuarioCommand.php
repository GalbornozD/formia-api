<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\UsuarioService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

/**
 * El esquema de BD se gestiona fuera de Laravel (SQL directo — nunca
 * `php artisan migrate` contra la BD real, ver CLAUDE.md). Este comando es
 * la vía soportada para dar de alta usuarios: solo usa Eloquent contra las
 * tablas ya existentes, no depende del sistema de migraciones ni de seeders.
 */
class CrearUsuarioCommand extends Command
{
    protected $signature = 'usuario:crear
        {email : Email del usuario}
        {--nombre= : Nombre}
        {--apellido= : Apellido}
        {--password= : Si se omite, se genera una aleatoria segura y se muestra una sola vez}
        {--empresa-id= : ID de una empresa ya existente}
        {--empresa-razon-social= : Si la empresa no existe, créala con esta razón social}
        {--empresa-rut= : RUT de la nueva empresa (junto con --empresa-razon-social)}
        {--rol=administrador : master|administrador}';

    protected $description = 'Da de alta un usuario y su membresía en una empresa (alta manual)';

    public function handle(UsuarioService $usuarioService): int
    {
        $email = strtolower((string) $this->argument('email'));

        if (User::where('email', $email)->exists()) {
            $this->error("Ya existe un usuario con el email {$email}.");

            return self::FAILURE;
        }

        $nombre = $this->option('nombre');
        $apellido = $this->option('apellido');

        if ($nombre === null || $apellido === null) {
            $this->error('Debes indicar --nombre y --apellido.');

            return self::FAILURE;
        }

        $rolId = Role::idDesdeNombre((string) $this->option('rol'));

        if ($rolId === null || ! in_array($rolId, [Role::MASTER, Role::ADMINISTRADOR], true)) {
            $this->error('Rol inválido. Usa: master o administrador.');

            return self::FAILURE;
        }

        $empresa = $this->resolverEmpresa();

        if ($empresa === null) {
            return self::FAILURE;
        }

        [$password, $passwordGenerada] = $this->resolverPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        $companyUser = $usuarioService->crear($empresa, [
            'email' => $email,
            'first_name' => $nombre,
            'last_name' => $apellido,
            'password' => $password,
            'role_id' => $rolId,
        ]);

        $usuario = $companyUser->usuario;

        $this->info(sprintf(
            'Usuario creado: %s (id=%d) en empresa "%s" (id=%d) con rol %s.',
            $usuario->email,
            $usuario->id,
            $empresa->legal_name,
            $empresa->id,
            Role::nombreDe($rolId),
        ));

        if ($passwordGenerada !== null) {
            $this->warn("Password generada (se muestra una sola vez, guárdala ahora): {$passwordGenerada}");
        }

        return self::SUCCESS;
    }

    private function resolverEmpresa(): ?Company
    {
        $empresaId = $this->option('empresa-id');

        if ($empresaId !== null) {
            $empresa = Company::find($empresaId);

            if ($empresa === null) {
                $this->error('No existe una empresa con ese --empresa-id.');

                return null;
            }

            return $empresa;
        }

        $razonSocial = $this->option('empresa-razon-social');
        $rut = $this->option('empresa-rut');

        if ($razonSocial === null || $rut === null) {
            $this->error('Indica --empresa-id (empresa existente) o --empresa-razon-social junto con --empresa-rut (para crear una nueva).');

            return null;
        }

        return Company::firstOrCreate(
            ['rut' => $rut],
            ['uuid' => (string) Str::uuid(), 'legal_name' => $razonSocial, 'status' => true],
        );
    }

    /**
     * @return array{0: ?string, 1: ?string} [password a guardar, password generada (null si vino por --password)]
     */
    private function resolverPassword(): array
    {
        $password = $this->option('password');

        if ($password === null) {
            $generada = Str::password(20);

            return [$generada, $generada];
        }

        $validador = Validator::make(['password' => $password], ['password' => Password::defaults()]);

        if ($validador->fails()) {
            $this->error($validador->errors()->first('password'));

            return [null, null];
        }

        return [$password, null];
    }
}
