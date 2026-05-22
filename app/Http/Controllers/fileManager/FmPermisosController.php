<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmArchivoUsuario;
use App\Models\fileManager\FmCarpeta;
use App\Models\fileManager\FmCarpetaUsuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FmPermisosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ------------------------------------------------------------------------
    // Permisos de carpeta
    // ------------------------------------------------------------------------

    /**
     * GET /folder/{id}/permisos
     * Devuelve { directos: [{user, ...permisos}], heredados: [{desde_carpeta_id, user, ...permisos}] }
     */
    public function indexCarpeta($id)
    {
        $log = new Funciones();
        try {
            $carpetaId = (int) $id;
            $carpeta = FmCarpeta::find($carpetaId);
            if (!$carpeta) {
                return response()->json(RespuestaApi::returnResultado('error', 'Carpeta no encontrada', null));
            }
            if (!$this->puedeGestionarCarpeta($carpetaId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar esta carpeta', null));
            }

            $directos = FmCarpetaUsuario::with('usuario')
                ->where('carpeta_id', $carpetaId)
                ->get();

            // Heredados: permisos en cualquier ancestro
            $idsAncestros = $this->idsAncestros($carpeta->materialized_path);
            $heredados = [];
            if (!empty($idsAncestros)) {
                $heredados = FmCarpetaUsuario::with(['usuario', 'carpeta'])
                    ->whereIn('carpeta_id', $idsAncestros)
                    ->get()
                    ->map(function ($p) {
                        $arr = $p->toArray();
                        $arr['desde_carpeta_id'] = $p->carpeta_id;
                        return $arr;
                    });
            }

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'directos'  => $directos,
                'heredados' => $heredados,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar permisos de carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * POST /folder/{id}/permisos
     * Asigna un usuario con un set de 8 booleans.
     */
    public function storeCarpeta($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'user_id'                  => 'required|integer',
            'puede_ver'                => 'sometimes|boolean',
            'puede_descargar'          => 'sometimes|boolean',
            'puede_subir_archivos'     => 'sometimes|boolean',
            'puede_crear_subcarpetas'  => 'sometimes|boolean',
            'puede_renombrar'          => 'sometimes|boolean',
            'puede_eliminar'           => 'sometimes|boolean',
            'puede_mover'              => 'sometimes|boolean',
            'puede_gestionar_permisos' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $carpetaId = (int) $id;
            if (!$this->puedeGestionarCarpeta($carpetaId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar esta carpeta', null));
            }

            $userId = (int) $request->input('user_id');
            $existe = FmCarpetaUsuario::where('carpeta_id', $carpetaId)
                ->where('user_id', $userId)
                ->exists();
            if ($existe) {
                return response()->json(RespuestaApi::returnResultado('error', 'El usuario ya tiene permiso asignado en esta carpeta', null));
            }

            $permiso = FmCarpetaUsuario::create(array_merge(
                $this->extraerBooleansCarpeta($request),
                [
                    'carpeta_id'   => $carpetaId,
                    'user_id'      => $userId,
                    'otorgado_por' => Auth::id(),
                ]
            ));

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_OTORGADO,
                FmAuditHelper::ENTIDAD_CARPETA,
                $carpetaId,
                null,
                $permiso->toArray()
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso otorgado', $permiso));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al otorgar permiso a carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * PUT /folder/{id}/permisos/{userId}
     */
    public function updateCarpeta($id, $userId, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'puede_ver'                => 'sometimes|boolean',
            'puede_descargar'          => 'sometimes|boolean',
            'puede_subir_archivos'     => 'sometimes|boolean',
            'puede_crear_subcarpetas'  => 'sometimes|boolean',
            'puede_renombrar'          => 'sometimes|boolean',
            'puede_eliminar'           => 'sometimes|boolean',
            'puede_mover'              => 'sometimes|boolean',
            'puede_gestionar_permisos' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $carpetaId = (int) $id;
            if (!$this->puedeGestionarCarpeta($carpetaId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar esta carpeta', null));
            }

            $permiso = FmCarpetaUsuario::where('carpeta_id', $carpetaId)
                ->where('user_id', (int) $userId)
                ->first();
            if (!$permiso) {
                return response()->json(RespuestaApi::returnResultado('error', 'Permiso no encontrado', null));
            }

            $antes = $permiso->toArray();
            $permiso->update($this->extraerBooleansCarpeta($request));

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_ACTUALIZADO,
                FmAuditHelper::ENTIDAD_CARPETA,
                $carpetaId,
                $antes,
                $permiso->fresh()->toArray()
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso actualizado', $permiso->fresh()));
        } catch (Exception $e) {
            $log->logError(self::class, "Error al actualizar permiso carpeta {$id}/user {$userId}", $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /folder/{id}/permisos/{userId}
     */
    public function destroyCarpeta($id, $userId)
    {
        $log = new Funciones();
        try {
            $carpetaId = (int) $id;
            if (!$this->puedeGestionarCarpeta($carpetaId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar esta carpeta', null));
            }

            $permiso = FmCarpetaUsuario::where('carpeta_id', $carpetaId)
                ->where('user_id', (int) $userId)
                ->first();
            if (!$permiso) {
                return response()->json(RespuestaApi::returnResultado('error', 'Permiso no encontrado', null));
            }

            $antes = $permiso->toArray();
            $permiso->delete();

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_REVOCADO,
                FmAuditHelper::ENTIDAD_CARPETA,
                $carpetaId,
                $antes,
                null
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso revocado', null));
        } catch (Exception $e) {
            $log->logError(self::class, "Error al revocar permiso carpeta {$id}/user {$userId}", $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Permisos de archivo
    // ------------------------------------------------------------------------

    /**
     * GET /file/{id}/permisos
     */
    public function indexArchivo($id)
    {
        $log = new Funciones();
        try {
            $archivoId = (int) $id;
            $archivo = FmArchivo::find($archivoId);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!$this->puedeGestionarArchivo($archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar este archivo', null));
            }

            $directos = FmArchivoUsuario::with('usuario')
                ->where('archivo_id', $archivoId)
                ->get();

            // Heredados: vienen de la carpeta padre + ancestros
            $carpetaPadre = FmCarpeta::find($archivo->carpeta_id);
            $heredados = [];
            if ($carpetaPadre) {
                $idsAncestros = $this->idsAncestros($carpetaPadre->materialized_path);
                $idsAncestros[] = $carpetaPadre->id;
                $heredados = FmCarpetaUsuario::with(['usuario', 'carpeta'])
                    ->whereIn('carpeta_id', $idsAncestros)
                    ->get()
                    ->map(function ($p) {
                        $arr = $p->toArray();
                        $arr['desde_carpeta_id'] = $p->carpeta_id;
                        return $arr;
                    });
            }

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'directos'  => $directos,
                'heredados' => $heredados,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar permisos de archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function storeArchivo($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'user_id'                  => 'required|integer',
            'puede_ver'                => 'sometimes|boolean',
            'puede_descargar'          => 'sometimes|boolean',
            'puede_renombrar'          => 'sometimes|boolean',
            'puede_editar_contenido'   => 'sometimes|boolean',
            'puede_eliminar'           => 'sometimes|boolean',
            'puede_mover'              => 'sometimes|boolean',
            'puede_gestionar_permisos' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $archivoId = (int) $id;
            if (!$this->puedeGestionarArchivo($archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar este archivo', null));
            }

            $userId = (int) $request->input('user_id');
            $existe = FmArchivoUsuario::where('archivo_id', $archivoId)
                ->where('user_id', $userId)
                ->exists();
            if ($existe) {
                return response()->json(RespuestaApi::returnResultado('error', 'El usuario ya tiene permiso asignado en este archivo', null));
            }

            $permiso = FmArchivoUsuario::create(array_merge(
                $this->extraerBooleansArchivo($request),
                [
                    'archivo_id'   => $archivoId,
                    'user_id'      => $userId,
                    'otorgado_por' => Auth::id(),
                ]
            ));

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_OTORGADO,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivoId,
                null,
                $permiso->toArray()
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso otorgado', $permiso));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al otorgar permiso a archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function updateArchivo($id, $userId, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'puede_ver'                => 'sometimes|boolean',
            'puede_descargar'          => 'sometimes|boolean',
            'puede_renombrar'          => 'sometimes|boolean',
            'puede_editar_contenido'   => 'sometimes|boolean',
            'puede_eliminar'           => 'sometimes|boolean',
            'puede_mover'              => 'sometimes|boolean',
            'puede_gestionar_permisos' => 'sometimes|boolean',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $archivoId = (int) $id;
            if (!$this->puedeGestionarArchivo($archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar este archivo', null));
            }

            $permiso = FmArchivoUsuario::where('archivo_id', $archivoId)
                ->where('user_id', (int) $userId)
                ->first();
            if (!$permiso) {
                return response()->json(RespuestaApi::returnResultado('error', 'Permiso no encontrado', null));
            }

            $antes = $permiso->toArray();
            $permiso->update($this->extraerBooleansArchivo($request));

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_ACTUALIZADO,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivoId,
                $antes,
                $permiso->fresh()->toArray()
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso actualizado', $permiso->fresh()));
        } catch (Exception $e) {
            $log->logError(self::class, "Error al actualizar permiso archivo {$id}/user {$userId}", $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function destroyArchivo($id, $userId)
    {
        $log = new Funciones();
        try {
            $archivoId = (int) $id;
            if (!$this->puedeGestionarArchivo($archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar este archivo', null));
            }

            $permiso = FmArchivoUsuario::where('archivo_id', $archivoId)
                ->where('user_id', (int) $userId)
                ->first();
            if (!$permiso) {
                return response()->json(RespuestaApi::returnResultado('error', 'Permiso no encontrado', null));
            }

            $antes = $permiso->toArray();
            $permiso->delete();

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_PERMISO_REVOCADO,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivoId,
                $antes,
                null
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Permiso revocado', null));
        } catch (Exception $e) {
            $log->logError(self::class, "Error al revocar permiso archivo {$id}/user {$userId}", $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Usuarios asignables (autocomplete)
    // ------------------------------------------------------------------------

    public function usuariosAsignables(Request $request)
    {
        $log = new Funciones();
        try {
            $q = trim((string) $request->input('q', ''));
            $query = DB::table('crm.users')
                ->select('id', 'usu_alias', 'name', 'surname')
                ->where('estado', 1)
                ->orderBy('usu_alias');

            if ($q !== '') {
                $query->where(function ($w) use ($q) {
                    $w->where('usu_alias', 'ILIKE', "%{$q}%")
                      ->orWhere('name', 'ILIKE', "%{$q}%")
                      ->orWhere('surname', 'ILIKE', "%{$q}%");
                });
            }

            $data = $query->limit(50)->get();
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $data));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en usuariosAsignables', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers privados
    // ------------------------------------------------------------------------

    private function puedeGestionarCarpeta(int $carpetaId): bool
    {
        return FmPermisosHelper::puedeRealizarAccion('gestionar_permisos', 'carpeta', $carpetaId);
    }

    private function puedeGestionarArchivo(int $archivoId): bool
    {
        return FmPermisosHelper::puedeRealizarAccion('gestionar_permisos', 'archivo', $archivoId);
    }

    private function extraerBooleansCarpeta(Request $request): array
    {
        return [
            'puede_ver'                => (bool) $request->input('puede_ver', false),
            'puede_descargar'          => (bool) $request->input('puede_descargar', false),
            'puede_subir_archivos'     => (bool) $request->input('puede_subir_archivos', false),
            'puede_crear_subcarpetas'  => (bool) $request->input('puede_crear_subcarpetas', false),
            'puede_renombrar'          => (bool) $request->input('puede_renombrar', false),
            'puede_eliminar'           => (bool) $request->input('puede_eliminar', false),
            'puede_mover'              => (bool) $request->input('puede_mover', false),
            'puede_gestionar_permisos' => (bool) $request->input('puede_gestionar_permisos', false),
        ];
    }

    private function extraerBooleansArchivo(Request $request): array
    {
        return [
            'puede_ver'                => (bool) $request->input('puede_ver', false),
            'puede_descargar'          => (bool) $request->input('puede_descargar', false),
            'puede_renombrar'          => (bool) $request->input('puede_renombrar', false),
            'puede_editar_contenido'   => (bool) $request->input('puede_editar_contenido', false),
            'puede_eliminar'           => (bool) $request->input('puede_eliminar', false),
            'puede_mover'              => (bool) $request->input('puede_mover', false),
            'puede_gestionar_permisos' => (bool) $request->input('puede_gestionar_permisos', false),
        ];
    }

    private function idsAncestros(string $materializedPath): array
    {
        $limpio = trim($materializedPath, '/');
        if ($limpio === '') return [];
        return array_map('intval', explode('/', $limpio));
    }
}
