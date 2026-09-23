<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\fileManager\FmQueryHelper;
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

    /**
     * GET /usuarios-asignables?q=&pagina=&tamanio=
     * Autocomplete paginado. Se pide una fila de más que el tamaño de página
     * para saber si queda algo, en vez de un COUNT sobre todo lo que hace match.
     */
    public function usuariosAsignables(Request $request)
    {
        $log = new Funciones();
        try {
            $q       = trim((string) $request->query('q', ''));
            $pagina  = max((int) $request->query('pagina', 1), 1);
            // Tope para que nadie pida 10.000 filas cambiando el query string.
            $tamanio = min(max((int) $request->query('tamanio', 15), 1), 50);

            // Con menos de 2 caracteres el ILIKE calza con casi todo y no acota
            // nada: mejor no ir a la base.
            if (mb_strlen($q) < 4) {
                return response()->json(RespuestaApi::returnResultado('success', 'Término muy corto', [
                    'registros' => [],
                    'hay_mas'   => false,
                ]));
            }

            $qEscapado = FmQueryHelper::escaparLike($q);

            $query = DB::table('crm.users')
                ->select('id', 'usu_alias', 'name', 'surname')
                ->where('estado', 1)
                ->where(function ($w) use ($qEscapado) {
                    $w->where('usu_alias', 'ILIKE', "%{$qEscapado}%")
                      ->orWhere('name', 'ILIKE', "%{$qEscapado}%")
                      ->orWhere('surname', 'ILIKE', "%{$qEscapado}%");
                })
                // Orden estable: sin un desempate por id, dos usuarios con el
                // mismo alias pueden cambiar de posición entre páginas y
                // repetirse o perderse.
                ->orderBy('usu_alias')
                ->orderBy('id');

            $filas = $query
                ->offset(($pagina - 1) * $tamanio)
                ->limit($tamanio + 1)
                ->get();

            $hayMas = $filas->count() > $tamanio;
            if ($hayMas) {
                $filas = $filas->take($tamanio);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'registros' => $filas->values(),
                'hay_mas'   => $hayMas,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en usuariosAsignables', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }


    /**
     * GET /usuarios-por-departamento?dep_id=&q=&pagina=&tamanio=
     *
     * Lista los usuarios activos de un departamento, paginado. Sirve al modal
     * de permisos como filtro: el departamento NO otorga permisos, solo acota
     * la lista de personas a las que asignárselos una por una.
     *
     * Cada fila trae `ya_asignado` con el nombre de la columna si ya tiene
     * permiso directo sobre la entidad, para avisar antes de reasignar.
     */
    public function usuariosPorDepartamento(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'dep_id'       => 'required|integer',
            'entidad_tipo' => 'required|in:carpeta,archivo',
            'entidad_id'   => 'required|integer',
            'q'            => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $depId       = (int) $request->query('dep_id');
            $entidadTipo = (string) $request->query('entidad_tipo');
            $entidadId   = (int) $request->query('entidad_id');
            $q           = trim((string) $request->query('q', ''));
            $pagina      = max((int) $request->query('pagina', 1), 1);
            $tamanio     = min(max((int) $request->query('tamanio', 20), 1), 50);

            $query = DB::table('crm.users')
                ->select('id', 'usu_alias', 'name', 'surname')
                ->where('estado', 1)
                ->where('dep_id', $depId)
                ->orderBy('surname')
                ->orderBy('name')
                // Desempate estable: sin él, dos homónimos pueden cambiar de
                // posición entre páginas y repetirse o perderse.
                ->orderBy('id');

            if ($q !== '') {
                $qEscapado = FmQueryHelper::escaparLike($q);
                $query->where(function ($w) use ($qEscapado) {
                    $w->where('usu_alias', 'ILIKE', "%{$qEscapado}%")
                      ->orWhere('name', 'ILIKE', "%{$qEscapado}%")
                      ->orWhere('surname', 'ILIKE', "%{$qEscapado}%");
                });
            }

            // Una fila de más para saber si hay página siguiente, sin COUNT.
            $filas = $query
                ->offset(($pagina - 1) * $tamanio)
                ->limit($tamanio + 1)
                ->get();

            $hayMas = $filas->count() > $tamanio;
            if ($hayMas) {
                $filas = $filas->take($tamanio);
            }

            // Permisos directos ya existentes, solo para los ids de esta página.
            $ids = $filas->pluck('id')->all();
            $yaAsignados = [];
            if (!empty($ids)) {
                $yaAsignados = $entidadTipo === 'carpeta'
                    ? FmCarpetaUsuario::where('carpeta_id', $entidadId)->whereIn('user_id', $ids)->get()->keyBy('user_id')
                    : FmArchivoUsuario::where('archivo_id', $entidadId)->whereIn('user_id', $ids)->get()->keyBy('user_id');
            }

            $registros = $filas->map(function ($u) use ($yaAsignados) {
                // Booleanos del permiso directo existente, o null si no tiene.
                // El frontend lo convierte a preset con inferirPreset*().
                $u->permiso_actual = $yaAsignados[$u->id] ?? null;
                return $u;
            })->values();

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'registros' => $registros,
                'hay_mas'   => $hayMas,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en usuariosPorDepartamento', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * POST /permisos/lote
     * Asigna el mismo preset a varios usuarios de una vez sobre una carpeta o
     * un archivo. Alternativa a N llamadas a storeCarpeta/storeArchivo cuando
     * se eligen varias personas desde el filtro por departamento.
     *
     * Los que ya tienen permiso directo se ACTUALIZAN al preset nuevo; se
     * informa cuántos fueron para que el frontend pueda avisar.
     */
    public function storeLote(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'entidad_tipo' => 'required|in:carpeta,archivo',
            'entidad_id'   => 'required|integer',
            'user_ids'     => 'required|array|min:1|max:200',
            'user_ids.*'   => 'integer',
        ], [
            'user_ids.max' => 'No se pueden asignar más de 200 usuarios a la vez',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $entidadTipo = (string) $request->input('entidad_tipo');
            $entidadId   = (int) $request->input('entidad_id');
            $userIds     = array_values(array_unique(array_map('intval', $request->input('user_ids'))));

            $puede = $entidadTipo === 'carpeta'
                ? $this->puedeGestionarCarpeta($entidadId)
                : $this->puedeGestionarArchivo($entidadId);
            if (!$puede) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para gestionar este elemento', null));
            }

            $booleans = $entidadTipo === 'carpeta'
                ? $this->extraerBooleansCarpeta($request)
                : $this->extraerBooleansArchivo($request);

            $resultado = DB::transaction(function () use ($entidadTipo, $entidadId, $userIds, $booleans) {
                $creados = 0;
                $actualizados = 0;

                foreach ($userIds as $userId) {
                    if ($entidadTipo === 'carpeta') {
                        $existente = FmCarpetaUsuario::where('carpeta_id', $entidadId)
                            ->where('user_id', $userId)->first();
                    } else {
                        $existente = FmArchivoUsuario::where('archivo_id', $entidadId)
                            ->where('user_id', $userId)->first();
                    }

                    if ($existente) {
                        $antes = $existente->toArray();
                        $existente->update($booleans);
                        $actualizados++;
                        FmAuditHelper::registrar(
                            FmAuditHelper::ACCION_PERMISO_ACTUALIZADO,
                            $entidadTipo === 'carpeta' ? FmAuditHelper::ENTIDAD_CARPETA : FmAuditHelper::ENTIDAD_ARCHIVO,
                            $entidadId,
                            $antes,
                            $existente->fresh()->toArray()
                        );
                        continue;
                    }

                    $datos = array_merge($booleans, [
                        'user_id'      => $userId,
                        'otorgado_por' => Auth::id(),
                    ]);
                    $datos[$entidadTipo === 'carpeta' ? 'carpeta_id' : 'archivo_id'] = $entidadId;

                    $permiso = $entidadTipo === 'carpeta'
                        ? FmCarpetaUsuario::create($datos)
                        : FmArchivoUsuario::create($datos);
                    $creados++;

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_PERMISO_OTORGADO,
                        $entidadTipo === 'carpeta' ? FmAuditHelper::ENTIDAD_CARPETA : FmAuditHelper::ENTIDAD_ARCHIVO,
                        $entidadId,
                        null,
                        $permiso->toArray()
                    );
                }

                return ['creados' => $creados, 'actualizados' => $actualizados];
            });

            $msg = "Permisos asignados: {$resultado['creados']}";
            if ($resultado['actualizados'] > 0) {
                $msg .= " — actualizados: {$resultado['actualizados']}";
            }

            $log->logInfo(self::class, $msg);
            return response()->json(RespuestaApi::returnResultado('success', $msg, $resultado));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en storeLote', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
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
