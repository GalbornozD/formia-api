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
    case ListaDistribucionCreada = 'lista_distribucion_creada';
    case ListaDistribucionActualizada = 'lista_distribucion_actualizada';
    case ListaDistribucionMiembroAgregado = 'lista_distribucion_miembro_agregado';
    case ListaDistribucionMiembroEliminado = 'lista_distribucion_miembro_eliminado';
    case PublicacionAudienciaAsignada = 'publicacion_audiencia_asignada';
    case InvitacionRegenerada = 'invitacion_regenerada';
    case InvitacionCancelada = 'invitacion_cancelada';
}
