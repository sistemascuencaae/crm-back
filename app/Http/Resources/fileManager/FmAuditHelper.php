<?php

namespace App\Http\Resources\fileManager;

use App\Models\fileManager\FmAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registrar acciones en el log de auditoría del File Manager (`crm.fm_audit_log`).
 *
 * Se invoca desde los controllers tras operaciones relevantes
 * (upload, download, rename, move, delete, restore, permiso_otorgado, etc.).
 */
class FmAuditHelper
{
    /**
     * Tipos de entidad permitidos.
     */
    public const ENTIDAD_CARPETA = 'carpeta';
    public const ENTIDAD_ARCHIVO = 'archivo';

    /**
     * Acciones estandarizadas (no es enum por compat con PHP 8.0).
     */
    public const ACCION_CREAR_CARPETA      = 'crear_carpeta';
    public const ACCION_RENOMBRAR          = 'renombrar';
    public const ACCION_MOVER              = 'mover';
    public const ACCION_ELIMINAR           = 'eliminar';
    public const ACCION_RESTAURAR          = 'restaurar';
    public const ACCION_PURGAR              = 'purgar_permanente';
    public const ACCION_UPLOAD             = 'upload';
    public const ACCION_DOWNLOAD           = 'download';
    public const ACCION_NUEVA_VERSION      = 'nueva_version';
    public const ACCION_PERMISO_OTORGADO   = 'permiso_otorgado';
    public const ACCION_PERMISO_REVOCADO   = 'permiso_revocado';
    public const ACCION_PERMISO_ACTUALIZADO = 'permiso_actualizado';
    public const ACCION_ASIGNAR_TAG        = 'asignar_tag';
    public const ACCION_QUITAR_TAG         = 'quitar_tag';

    /**
     * Registra una entrada en el audit log.
     *
     * @param string      $accion        Una de las constantes ACCION_*
     * @param string      $entidadTipo   ENTIDAD_CARPETA o ENTIDAD_ARCHIVO
     * @param int         $entidadId
     * @param array|null  $datosAntes    Snapshot previo (para updates/deletes)
     * @param array|null  $datosDespues  Snapshot nuevo (para creates/updates)
     */
    public static function registrar(
        string $accion,
        string $entidadTipo,
        int $entidadId,
        ?array $datosAntes = null,
        ?array $datosDespues = null
    ): void {
        date_default_timezone_set('America/Guayaquil');

        FmAuditLog::create([
            'user_id'       => Auth::id() ?? 0,
            'accion'        => $accion,
            'entidad_tipo'  => $entidadTipo,
            'entidad_id'    => $entidadId,
            'datos_antes'   => $datosAntes,
            'datos_despues' => $datosDespues,
            'ip_address'    => Request::ip(),
            'user_agent'    => Request::header('User-Agent'),
            'created_at'    => Carbon::now(),
        ]);
    }
}
