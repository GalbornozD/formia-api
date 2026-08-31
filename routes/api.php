<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CompanyBrandingController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanySettingsController;
use App\Http\Controllers\DistributionLists\DistributionListController;
use App\Http\Controllers\DistributionLists\DistributionListMemberController;
use App\Http\Controllers\EmpresaTipoFormularioController;
use App\Http\Controllers\EmpresaUsuarioController;
use App\Http\Controllers\FieldTypeController;
use App\Http\Controllers\FormFieldController;
use App\Http\Controllers\FormFieldOptionController;
use App\Http\Controllers\FormResponses\AdminFormPublicationController;
use App\Http\Controllers\FormResponses\FormAssignmentController;
use App\Http\Controllers\FormResponses\FormInvitationController;
use App\Http\Controllers\FormResponses\MyFormController;
use App\Http\Controllers\FormResponses\MyFormResponseController;
use App\Http\Controllers\FormResponses\PublicationAudienceController;
use App\Http\Controllers\FormResponses\PublicFormController;
use App\Http\Controllers\FormResponses\PublicFormResponseController;
use App\Http\Controllers\FormResponses\PublicInvitationController;
use App\Http\Controllers\FormTypeVersionController;
use App\Http\Controllers\GuestRespondents\GuestRespondentController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SessionController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:public-forms')->prefix('/public')->group(function () {
    Route::get('/forms/{publicationUuid}', [PublicFormController::class, 'show']);
    Route::post('/forms/{publicationUuid}/responses', [PublicFormResponseController::class, 'store']);
    Route::get('/responses/{responseUuid}', [PublicFormResponseController::class, 'show']);
    Route::patch('/responses/{responseUuid}', [PublicFormResponseController::class, 'update']);
    Route::post('/responses/{responseUuid}/submit', [PublicFormResponseController::class, 'submit']);

    // Invitados con link personalizado (`/f/{uuid}/invite/{token}` en el
    // frontend) — identidad resuelta 100% desde el hash del token, nunca
    // desde IDs recibidos en la URL.
    Route::get('/invitations/{token}', [PublicInvitationController::class, 'show']);
    Route::post('/invitations/{token}/responses', [PublicInvitationController::class, 'store']);
});

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:password-reset');

Route::post('/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:password-reset');

Route::middleware(['auth:sanctum', 'sesion.vigente'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Establecen la empresa activa en sesión — por eso van SIN el middleware
    // empresa.activa (ese exige que la empresa ya esté resuelta).
    Route::post('/seleccionar-empresa', [AuthController::class, 'seleccionarEmpresa']);
    Route::post('/cambiar-empresa', [AuthController::class, 'seleccionarEmpresa']);

    // Catálogo de roles asignables por el usuario autenticado (combo de
    // rol en el alta/edición de usuarios) — no depende de empresa activa,
    // el rol es global.
    Route::get('/roles', [RoleController::class, 'index']);

    // Autogestión de dispositivos/sesiones — alcance por usuario, no por
    // empresa activa, por eso van fuera del grupo empresa.activa.
    Route::get('/sesiones', [SessionController::class, 'index']);
    Route::get('/sesiones/todas', [SessionController::class, 'todas']);
    Route::delete('/sesiones/{sesion}', [SessionController::class, 'destroy']);

    // Rutas de negocio: exigen que ya haya una empresa activa válida antes de
    // llegar al controlador (master queda exento, ver ResolveEmpresaActiva).
    Route::middleware('empresa.activa')->group(function () {
        Route::get('/me/forms', [MyFormController::class, 'index']);
        Route::get('/me/form-publications/{publicationUuid}', [MyFormController::class, 'show']);
        Route::post('/me/form-publications/{publicationUuid}/responses', [MyFormResponseController::class, 'store']);
        Route::get('/me/form-responses/{responseUuid}', [MyFormResponseController::class, 'show']);
        Route::patch('/me/form-responses/{responseUuid}', [MyFormResponseController::class, 'update']);
        Route::post('/me/form-responses/{responseUuid}/submit', [MyFormResponseController::class, 'submit']);

        Route::get('/field-types', [FieldTypeController::class, 'index'])
            ->name('field-types.index');

        // Configuración de la empresa activa (nunca recibe un company_id
        // desde el cliente: siempre se resuelve vía EmpresaContext).
        Route::prefix('/company')->group(function () {
            Route::get('/', [CompanyController::class, 'show']);
            Route::get('/context', [CompanyController::class, 'context']);

            Route::get('/branding', [CompanyBrandingController::class, 'show']);
            Route::put('/branding', [CompanyBrandingController::class, 'update']);
            Route::post('/branding/logo', [CompanyBrandingController::class, 'uploadLogo']);
            Route::delete('/branding/logo', [CompanyBrandingController::class, 'deleteLogo']);
            Route::post('/branding/logo-compact', [CompanyBrandingController::class, 'uploadLogoCompact']);
            Route::delete('/branding/logo-compact', [CompanyBrandingController::class, 'deleteLogoCompact']);
            Route::post('/branding/logo-dark', [CompanyBrandingController::class, 'uploadLogoDark']);
            Route::delete('/branding/logo-dark', [CompanyBrandingController::class, 'deleteLogoDark']);
            Route::post('/branding/favicon', [CompanyBrandingController::class, 'uploadFavicon']);
            Route::delete('/branding/favicon', [CompanyBrandingController::class, 'deleteFavicon']);

            Route::get('/settings', [CompanySettingsController::class, 'show']);
            Route::put('/settings', [CompanySettingsController::class, 'update']);
        });

        Route::apiResource('empresas.usuarios', EmpresaUsuarioController::class)
            ->parameters(['empresas' => 'empresa', 'usuarios' => 'usuario'])
            ->only(['index', 'store', 'update', 'destroy']);
        Route::get('/empresas/{empresa}/usuarios/buscar', [EmpresaUsuarioController::class, 'search']);

        Route::apiResource('empresas.tipos-formulario', EmpresaTipoFormularioController::class)
            ->parameters(['empresas' => 'empresa', 'tipos-formulario' => 'tipoFormulario'])
            ->only(['index', 'store', 'update', 'destroy']);

        Route::apiResource('empresas.distribution-lists', DistributionListController::class)
            ->parameters(['empresas' => 'empresa', 'distribution-lists' => 'distributionList']);

        Route::prefix('/empresas/{empresa}/distribution-lists/{distributionList}')
            ->scopeBindings()
            ->group(function () {
                Route::get('/members', [DistributionListMemberController::class, 'index']);
                Route::post('/members', [DistributionListMemberController::class, 'store']);
                Route::delete('/members', [DistributionListMemberController::class, 'destroy']);
            });

        Route::apiResource('empresas.guest-respondents', GuestRespondentController::class)
            ->parameters(['empresas' => 'empresa', 'guest-respondents' => 'guestRespondent'])
            ->only(['index', 'store', 'show', 'update', 'destroy']);

        Route::prefix('/empresas/{empresa}/tipos-formulario/{formType}')
            ->scopeBindings()
            ->group(function () {
                Route::get('/publicaciones', [AdminFormPublicationController::class, 'index']);
                Route::post('/publicaciones', [AdminFormPublicationController::class, 'store']);
                Route::get('/publicaciones/{publication:uuid}', [AdminFormPublicationController::class, 'show']);
                Route::put('/publicaciones/{publication:uuid}', [AdminFormPublicationController::class, 'update']);

                Route::get('/publicaciones/{publication:uuid}/asignaciones', [FormAssignmentController::class, 'index']);
                Route::post('/publicaciones/{publication:uuid}/asignaciones', [FormAssignmentController::class, 'store']);

                Route::get('/publicaciones/{publication:uuid}/audiencia', [PublicationAudienceController::class, 'show']);
                Route::post('/publicaciones/{publication:uuid}/audiencia', [PublicationAudienceController::class, 'store']);
                Route::post('/publicaciones/{publication:uuid}/audiencia/sincronizar', [PublicationAudienceController::class, 'sync']);
                Route::post('/publicaciones/{publication:uuid}/audiencia/previsualizar', [PublicationAudienceController::class, 'preview']);

                Route::get('/publicaciones/{publication:uuid}/invitaciones', [FormInvitationController::class, 'index']);
                Route::post('/publicaciones/{publication:uuid}/invitaciones/{invitation:uuid}/regenerar', [FormInvitationController::class, 'regenerate']);
                Route::post('/publicaciones/{publication:uuid}/invitaciones/{invitation:uuid}/cancelar', [FormInvitationController::class, 'cancel']);

                Route::get('/versiones', [FormTypeVersionController::class, 'index']);
                Route::post('/versiones', [FormTypeVersionController::class, 'store']);
                Route::get('/versiones/{version}/constructor', [FormTypeVersionController::class, 'builder']);
                Route::put('/versiones/{version}/constructor', [FormTypeVersionController::class, 'saveBuilder']);
                Route::post('/versiones/{version}/publicar', [FormTypeVersionController::class, 'publish']);
                Route::get('/versiones/{version}', [FormTypeVersionController::class, 'show']);

                Route::get('/versiones/{version}/campos', [FormFieldController::class, 'index']);
                Route::post('/versiones/{version}/campos', [FormFieldController::class, 'store']);
                Route::put('/versiones/{version}/campos/orden', [FormFieldController::class, 'reorder']);
                Route::get('/versiones/{version}/campos/{field}', [FormFieldController::class, 'show']);
                Route::put('/versiones/{version}/campos/{field}', [FormFieldController::class, 'update']);
                Route::delete('/versiones/{version}/campos/{field}', [FormFieldController::class, 'destroy']);
                Route::post('/versiones/{version}/campos/{field}/duplicar', [FormFieldController::class, 'duplicate']);

                Route::post('/versiones/{version}/campos/{field}/opciones', [FormFieldOptionController::class, 'store']);
                Route::put('/versiones/{version}/campos/{field}/opciones/orden', [FormFieldOptionController::class, 'reorder']);
                Route::put('/versiones/{version}/campos/{field}/opciones/{option}', [FormFieldOptionController::class, 'update']);
                Route::delete('/versiones/{version}/campos/{field}/opciones/{option}', [FormFieldOptionController::class, 'destroy']);
            });
    });
});
