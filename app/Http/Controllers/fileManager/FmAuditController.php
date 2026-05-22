<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmAuditLog;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Vista de auditoría del File Manager.
 *
 * GET /crm/file-manager/auditoria/recientes
 *     ?limit=200&accion=upload&entidad_tipo=archivo&user_id=42
 *     &desde=2026-05-01&hasta=2026-05-20
 *
 * Solo admin global (usu_tipo_analista = 1) puede consultar.
 */
class FmAuditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function recientes(Request $request)
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            if (!FmPermisosHelper::esAdmin($userId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Solo administradores pueden ver la auditoría', null));
            }

            $validator = Validator::make($request->all(), [
                'limit'        => 'nullable|integer|min:1|max:500',
                'accion'       => 'nullable|string|max:50',
                'entidad_tipo' => 'nullable|in:carpeta,archivo',
                'user_id'      => 'nullable|integer',
                'desde'        => 'nullable|date',
                'hasta'        => 'nullable|date',
            ]);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
            }

            $limit = (int) $request->input('limit', 100);

            // JOIN a users para mostrar el nombre del usuario en la UI.
            $query = FmAuditLog::query()
                ->leftJoin('crm.users', 'crm.users.id', '=', 'crm.fm_audit_log.user_id')
                ->select([
                    'crm.fm_audit_log.*',
                    DB::raw("COALESCE(crm.users.name || ' ' || crm.users.surname, crm.users.name, 'Usuario #' || crm.fm_audit_log.user_id) AS usuario_nombre"),
                ])
                ->orderBy('crm.fm_audit_log.created_at', 'desc');

            if ($request->filled('accion')) {
                $query->where('crm.fm_audit_log.accion', $request->input('accion'));
            }
            if ($request->filled('entidad_tipo')) {
                $query->where('crm.fm_audit_log.entidad_tipo', $request->input('entidad_tipo'));
            }
            if ($request->filled('user_id')) {
                $query->where('crm.fm_audit_log.user_id', (int) $request->input('user_id'));
            }
            if ($request->filled('desde')) {
                $query->where('crm.fm_audit_log.created_at', '>=', $request->input('desde'));
            }
            if ($request->filled('hasta')) {
                $query->where('crm.fm_audit_log.created_at', '<=', $request->input('hasta'));
            }

            $items = $query->limit($limit)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'OK', $items));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en auditoría/recientes', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}
