<?php

namespace App\Enums;

enum AuditAction: string
{
    case Login = 'login';
    case LoginFallido = 'login_fallido';
    case Logout = 'logout';
    case CambioPassword = 'cambio_password';
    case CambioEmpresaActiva = 'cambio_empresa_activa';
    case MembresiaCreada = 'membresia_creada';
    case MembresiaActualizada = 'membresia_actualizada';
    case MembresiaEliminada = 'membresia_eliminada';
    case SesionRevocada = 'sesion_revocada';
}
