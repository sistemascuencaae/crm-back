<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmArbolHelper;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\fileManager\FmStorageHelper;
use App\Models\fileManager\FmCarpetaUsuario;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmCarpeta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FmCarpetaController extends Controller
{
    /**
     * ID de la carpeta raíz especial (seedeada). No se puede renombrar, mover ni eliminar.
     */
    private const RAIZ_ID = 1;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    /**
     * ¿El usuario actual puede acceder/navegar a esta carpeta?
     *
     * Cubre 3 casos (modelo "carpetas de paso"):
     *  - es admin global (bypass total), o
     *  - tiene puede_ver directo o heredado en la carpeta, o
     *  - la carpeta es "de paso": algún descendiente (carpeta o archivo) tiene
     *    permiso directo, así que el usuario debe poder atravesar esta para llegar.
     *
     * En el último caso, el listado interno (subcarpetasVisibles/archivosVisiblesEnCarpeta)
     * filtra automáticamente para mostrar SOLO los items con permiso directo.
     */
    private function puedeAccederCarpeta(int $carpetaId): bool
    {
        if (FmPermisosHelper::esAdmin()) {
            return true;
        }
        return FmPermisosHelper::carpetasVisibles()->contains($carpetaId);
    }

    // ------------------------------------------------------------------------
    // Navegación
    // ------------------------------------------------------------------------

    /**
     * GET /roots
     * Devuelve el contenido de la carpeta raíz (hijos directos + archivos en raíz).
     */
    public function roots()
    {
        return $this->contents(self::RAIZ_ID);
    }

    /**
     * GET /folder/{id}/contents
     * Devuelve {folder, breadcrumb, subcarpetas, archivos} de la carpeta indicada.
     */
    public function contents($id)
    {
        $log = new Funciones();
        try {
            $carpetaId = (int) $id;

            $carpeta = FmCarpeta::find($carpetaId);
            if (!$carpeta) {
                return response()->json(RespuestaApi::returnResultado('error', 'Carpeta no encontrada', null));
            }

            // La raíz id=1 es accesible para todos (sin contenido sensible directamente);
            // los hijos ya se filtran por permisos. Para otras carpetas, además del
            // permiso directo/heredado, se permite la entrada si es "carpeta de paso"
            // (el usuario tiene permiso a algún descendiente); el listado interno
            // mostrará solo los items con permiso directo.
            if ($carpetaId !== self::RAIZ_ID && !$this->puedeAccederCarpeta($carpetaId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene acceso a esta carpeta', null));
            }

            $breadcrumb = FmArbolHelper::construirBreadcrumb($carpetaId);

            $subcarpetas = FmPermisosHelper::subcarpetasVisibles($carpetaId)
                ->orderBy('nombre')
                ->get();

            $archivos = FmPermisosHelper::archivosVisiblesEnCarpeta($carpetaId)
                ->with('tags')
                ->orderBy('nombre')
                ->get();

            $data = [
                'folder'      => $carpeta,
                'breadcrumb'  => $breadcrumb,
                'subcarpetas' => $subcarpetas,
                'archivos'    => $archivos,
            ];

            $log->logInfo(self::class, "contents($carpetaId): " . count($subcarpetas) . " subcarpetas, " . count($archivos) . " archivos");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar contenido de carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * GET /folder/{id}/breadcrumb
     */
    public function breadcrumb($id)
    {
        $log = new Funciones();
        try {
            $data = FmArbolHelper::construirBreadcrumb((int) $id);
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $data));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al construir breadcrumb ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * GET /folder/tree
     * Devuelve todas las carpetas (planas). El front arma el árbol via parent_id.
     */
    public function tree()
    {
        $log = new Funciones();
        try {
            if (FmPermisosHelper::esAdmin()) {
                $data = FmCarpeta::orderBy('nivel')->orderBy('nombre')->get();
            } else {
                $visibles = FmPermisosHelper::carpetasVisibles();
                // Incluir siempre la raíz id=1 para que el árbol siempre tenga un nodo padre
                $visibles = $visibles->push(self::RAIZ_ID)->unique();
                $data = FmCarpeta::whereIn('id', $visibles->all())
                    ->orderBy('nivel')->orderBy('nombre')->get();
            }
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $data));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar árbol', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * GET /papelera
     * Lista los items soft-deleted visibles para el usuario actual.
     * Admin global ve todo; otros usuarios sólo lo que ellos crearon.
     */
    public function papelera()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            $esAdmin = FmPermisosHelper::esAdmin($userId);

            if ($esAdmin) {
                $carpetas = FmCarpeta::onlyTrashed()
                    ->orderBy('deleted_at', 'desc')
                    ->get();
                $archivos = FmArchivo::onlyTrashed()
                    ->where('es_version_actual', true)
                    ->orderBy('deleted_at', 'desc')
                    ->get();
            } else {
                $carpetas = FmCarpeta::onlyTrashed()
                    ->where('creado_por', $userId)
                    ->orderBy('deleted_at', 'desc')
                    ->get();
                $archivos = FmArchivo::onlyTrashed()
                    ->where('es_version_actual', true)
                    ->where('creado_por', $userId)
                    ->orderBy('deleted_at', 'desc')
                    ->get();
            }

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'carpetas' => $carpetas,
                'archivos' => $archivos,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar papelera', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * GET /folder/{id}
     * Metadata de una carpeta.
     */
    public function show($id)
    {
        $log = new Funciones();
        try {
            $carpeta = FmCarpeta::find($id);
            if (!$carpeta) {
                return response()->json(RespuestaApi::returnResultado('error', 'Carpeta no encontrada', null));
            }
            if ((int) $id !== self::RAIZ_ID && !$this->puedeAccederCarpeta((int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene acceso a esta carpeta', null));
            }
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $carpeta));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al obtener carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // CRUD
    // ------------------------------------------------------------------------

    /**
     * POST /folder
     * Crea una carpeta. Si parent_id no se especifica, se usa la raíz (id=1).
     */
    public function create(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:255',
            'parent_id'   => 'nullable|integer',
            'descripcion' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $parentIdValidacion = (int) ($request->input('parent_id') ?? self::RAIZ_ID);
            // Para crear directamente en la raíz, cualquier usuario autenticado puede
            // (porque la raíz no es "una carpeta del usuario", es el contenedor global).
            // Para crear en otra carpeta, validar permiso.
            if ($parentIdValidacion !== self::RAIZ_ID &&
                !FmPermisosHelper::puedeRealizarAccion('crear_subcarpetas', 'carpeta', $parentIdValidacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para crear subcarpetas en esta ubicación', null));
            }

            $carpeta = DB::transaction(function () use ($request) {
                $parentId = $request->input('parent_id') ?? self::RAIZ_ID;
                $parent = FmCarpeta::find($parentId);
                if (!$parent) {
                    throw new Exception('Carpeta padre no encontrada');
                }

                $nombre = trim($request->input('nombre'));

                $existe = FmCarpeta::where('parent_id', $parentId)
                    ->where('nombre', $nombre)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($existe) {
                    throw new Exception('Ya existe una carpeta con ese nombre en esta ubicación');
                }

                $paths = FmArbolHelper::calcularPathParaNuevaCarpeta($parent);

                $nueva = FmCarpeta::create([
                    'nombre'            => $nombre,
                    'parent_id'         => $parentId,
                    'materialized_path' => $paths['materialized_path'],
                    'nivel'             => $paths['nivel'],
                    'creado_por'        => Auth::id(),
                    'descripcion'       => $request->input('descripcion'),
                ]);

                // Creator gets admin: el creador recibe TODOS los permisos sobre la carpeta nueva
                FmCarpetaUsuario::create([
                    'carpeta_id'               => $nueva->id,
                    'user_id'                  => Auth::id(),
                    'puede_ver'                => true,
                    'puede_descargar'          => true,
                    'puede_subir_archivos'     => true,
                    'puede_crear_subcarpetas'  => true,
                    'puede_renombrar'          => true,
                    'puede_eliminar'           => true,
                    'puede_mover'              => true,
                    'puede_gestionar_permisos' => true,
                    'otorgado_por'             => Auth::id(),
                ]);

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_CREAR_CARPETA,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    $nueva->id,
                    null,
                    $nueva->toArray()
                );

                return $nueva;
            });

            $log->logInfo(self::class, 'Carpeta creada #' . $carpeta->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta creada', $carpeta));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al crear carpeta', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * PUT /folder/{id}/rename
     */
    public function rename($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            if (!FmPermisosHelper::puedeRealizarAccion('renombrar', 'carpeta', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para renombrar esta carpeta', null));
            }

            $carpeta = DB::transaction(function () use ($id, $request) {
                $carpeta = FmCarpeta::find($id);
                if (!$carpeta) {
                    throw new Exception('Carpeta no encontrada');
                }
                if ((int) $carpeta->id === self::RAIZ_ID) {
                    throw new Exception('No se puede renombrar la carpeta raíz');
                }

                $nombre = trim($request->input('nombre'));

                $existe = FmCarpeta::where('parent_id', $carpeta->parent_id)
                    ->where('nombre', $nombre)
                    ->where('id', '!=', $id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($existe) {
                    throw new Exception('Ya existe una carpeta con ese nombre en esta ubicación');
                }

                $antes = $carpeta->toArray();
                $carpeta->update(['nombre' => $nombre]);

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_RENOMBRAR,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    $carpeta->id,
                    $antes,
                    $carpeta->fresh()->toArray()
                );

                return $carpeta->fresh();
            });

            $log->logInfo(self::class, 'Carpeta renombrada #' . $carpeta->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta renombrada', $carpeta));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al renombrar carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * PUT /folder/{id}/move
     * Mueve la carpeta al nuevo parent. nuevo_parent_id puede ser null (raíz absoluta)
     * pero en este modelo la raíz absoluta es la id=1, así que típicamente se mueve a la raíz pasando 1.
     */
    public function move($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nuevo_parent_id' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $idInt = (int) $id;
            if (!FmPermisosHelper::puedeRealizarAccion('mover', 'carpeta', $idInt)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover esta carpeta', null));
            }
            $nuevoParentIdValidacion = $request->input('nuevo_parent_id');
            if ($nuevoParentIdValidacion !== null && (int) $nuevoParentIdValidacion !== self::RAIZ_ID &&
                !FmPermisosHelper::puedeRealizarAccion('crear_subcarpetas', 'carpeta', (int) $nuevoParentIdValidacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover a la carpeta destino', null));
            }

            $carpeta = DB::transaction(function () use ($id, $request) {
                $carpeta = FmCarpeta::find($id);
                if (!$carpeta) {
                    throw new Exception('Carpeta no encontrada');
                }
                if ((int) $carpeta->id === self::RAIZ_ID) {
                    throw new Exception('No se puede mover la carpeta raíz');
                }

                $nuevoParentId = $request->input('nuevo_parent_id');

                if (!FmArbolHelper::validarNoCiclo((int) $id, $nuevoParentId !== null ? (int) $nuevoParentId : null)) {
                    throw new Exception('No se puede mover una carpeta dentro de sí misma o de sus descendientes');
                }

                $nuevoParent = $nuevoParentId !== null ? FmCarpeta::find($nuevoParentId) : null;

                if ($nuevoParentId !== null && !$nuevoParent) {
                    throw new Exception('Carpeta destino no encontrada');
                }

                // Validar nombre único en destino
                $existe = FmCarpeta::where('parent_id', $nuevoParentId)
                    ->where('nombre', $carpeta->nombre)
                    ->where('id', '!=', $id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($existe) {
                    throw new Exception('Ya existe una carpeta con ese nombre en el destino');
                }

                $antes = $carpeta->toArray();
                FmArbolHelper::recalcularPathSubarbol($carpeta, $nuevoParent);

                $carpeta->refresh();

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_MOVER,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    $carpeta->id,
                    $antes,
                    $carpeta->toArray()
                );

                return $carpeta;
            });

            $log->logInfo(self::class, 'Carpeta movida #' . $carpeta->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta movida', $carpeta));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al mover carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /folder/{id}
     * Soft delete recursivo: la carpeta + todas sus descendientes + todos los archivos hijos.
     */
    public function delete($id)
    {
        $log = new Funciones();

        try {
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'carpeta', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para eliminar esta carpeta', null));
            }

            DB::transaction(function () use ($id) {
                $carpeta = FmCarpeta::find($id);
                if (!$carpeta) {
                    throw new Exception('Carpeta no encontrada');
                }
                if ((int) $carpeta->id === self::RAIZ_ID) {
                    throw new Exception('No se puede eliminar la carpeta raíz');
                }

                $antes = $carpeta->toArray();

                // IDs de toda la subcarpeta incluyendo la carpeta misma.
                $ids = FmArbolHelper::descendientesDe((int) $id)->pluck('id')->toArray();
                $ids[] = (int) $id;

                // Soft delete en bulk.
                FmArchivo::whereIn('carpeta_id', $ids)->delete();
                FmCarpeta::whereIn('id', $ids)->delete();

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_ELIMINAR,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    (int) $id,
                    $antes,
                    null
                );
            });

            $log->logInfo(self::class, 'Carpeta eliminada #' . $id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta eliminada', null));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al eliminar carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * POST /folder/{id}/restore
     * Restaura una carpeta soft-deleted, junto con todos sus descendientes
     * y archivos que se marcaron al mismo tiempo.
     */
    public function restore($id)
    {
        $log = new Funciones();

        try {
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'carpeta', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para restaurar esta carpeta', null));
            }

            $carpeta = DB::transaction(function () use ($id) {
                $carpeta = FmCarpeta::withTrashed()->find($id);
                if (!$carpeta) {
                    throw new Exception('Carpeta no encontrada');
                }
                if (!$carpeta->trashed()) {
                    throw new Exception('La carpeta no está en la papelera');
                }

                // Auto-restaura cadena ancestral si está eliminada
                $ancestrosARestaurar = [];
                $cursorParent = $carpeta->parent_id !== null
                    ? FmCarpeta::withTrashed()->find($carpeta->parent_id)
                    : null;
                while ($cursorParent && $cursorParent->trashed()) {
                    $ancestrosARestaurar[] = $cursorParent;
                    if ($cursorParent->parent_id === null) {
                        break;
                    }
                    $cursorParent = FmCarpeta::withTrashed()->find($cursorParent->parent_id);
                }
                foreach (array_reverse($ancestrosARestaurar) as $a) {
                    $a->restore();
                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_RESTAURAR,
                        FmAuditHelper::ENTIDAD_CARPETA,
                        $a->id,
                        null,
                        $a->fresh()->toArray()
                    );
                }

                $prefijo = $carpeta->materialized_path . $carpeta->id . '/';

                // Descendientes trashed del subárbol
                $descendientesIds = FmCarpeta::onlyTrashed()
                    ->where('materialized_path', 'LIKE', $prefijo . '%')
                    ->pluck('id')
                    ->toArray();

                $todosIds = array_merge([(int) $id], $descendientesIds);

                FmCarpeta::withTrashed()->whereIn('id', $todosIds)->restore();
                FmArchivo::withTrashed()->whereIn('carpeta_id', $todosIds)->restore();

                $carpeta = $carpeta->fresh();

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_RESTAURAR,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    $carpeta->id,
                    null,
                    $carpeta->toArray()
                );

                return $carpeta;
            });

            $log->logInfo(self::class, 'Carpeta restaurada #' . $carpeta->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta restaurada', $carpeta));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al restaurar carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /folder/{id}/permanente
     * Borra definitivamente la carpeta y todo su subárbol (carpetas + archivos
     * descendientes, incluyendo binarios en disco). Requiere que esté soft-deleted.
     */
    public function deletePermanente($id)
    {
        $log = new Funciones();
        try {
            if ((int) $id === self::RAIZ_ID) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se puede purgar la carpeta raíz', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'carpeta', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para purgar esta carpeta', null));
            }

            DB::transaction(function () use ($id) {
                $carpeta = FmCarpeta::withTrashed()->find($id);
                if (!$carpeta) {
                    throw new Exception('Carpeta no encontrada');
                }
                if (!$carpeta->trashed()) {
                    throw new Exception('La carpeta debe estar en la papelera antes de purgarla');
                }

                $prefijo = $carpeta->materialized_path . $carpeta->id . '/';
                $descendientesIds = FmCarpeta::withTrashed()
                    ->where('materialized_path', 'LIKE', $prefijo . '%')
                    ->pluck('id')
                    ->toArray();
                $todosIds = array_merge([(int) $id], $descendientesIds);

                // Recoger archivos para borrar físicos antes de purgar registros
                $archivos = FmArchivo::withTrashed()
                    ->whereIn('carpeta_id', $todosIds)
                    ->get(['id', 'disk', 'ruta_fisica']);

                $archivosSnapshot = $archivos->map->only(['id', 'disk', 'ruta_fisica'])->toArray();
                $carpetaAntes = $carpeta->toArray();

                // ON DELETE CASCADE en fm_archivo_usuario y fm_archivo_tag.
                // ON DELETE RESTRICT en fm_archivo.carpeta_id → borrar archivos antes que carpetas.
                FmArchivo::withTrashed()->whereIn('carpeta_id', $todosIds)->forceDelete();
                FmCarpeta::withTrashed()->whereIn('id', $todosIds)->forceDelete();

                // Binarios físicos
                foreach ($archivos as $a) {
                    FmStorageHelper::delete($a->disk, $a->ruta_fisica);
                }

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_PURGAR,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    (int) $id,
                    [
                        'carpeta'  => $carpetaAntes,
                        'archivos' => $archivosSnapshot,
                    ],
                    null
                );
            });

            $log->logInfo(self::class, 'Carpeta purgada #' . $id);
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta eliminada permanentemente', null));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al purgar carpeta ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /papelera/vaciar
     * Purga (delete permanente) todos los items en papelera visibles para
     * el usuario actual. Admin global vacía todo; no-admin sólo lo suyo.
     */
    public function vaciarPapelera()
    {
        $log = new Funciones();
        try {
            $userId = Auth::id();
            $esAdmin = FmPermisosHelper::esAdmin($userId);

            $resultado = DB::transaction(function () use ($userId, $esAdmin) {
                $queryCarpetas = FmCarpeta::onlyTrashed()->where('id', '!=', self::RAIZ_ID);
                $queryArchivos = FmArchivo::onlyTrashed();
                if (!$esAdmin) {
                    $queryCarpetas->where('creado_por', $userId);
                    $queryArchivos->where('creado_por', $userId);
                }

                $archivos = $queryArchivos->get(['id', 'disk', 'ruta_fisica']);
                $carpetaIds = $queryCarpetas->pluck('id')->toArray();
                $archivoIds = $archivos->pluck('id')->toArray();

                // Para que el FK ON DELETE RESTRICT en fm_archivo.carpeta_id no truene,
                // borramos primero todos los archivos del set, luego carpetas (en orden
                // de hoja → raíz vía nivel descendente).
                if (!empty($archivoIds)) {
                    FmArchivo::withTrashed()->whereIn('id', $archivoIds)->forceDelete();
                }
                if (!empty($carpetaIds)) {
                    FmCarpeta::withTrashed()
                        ->whereIn('id', $carpetaIds)
                        ->orderBy('nivel', 'desc')
                        ->forceDelete();
                }

                foreach ($archivos as $a) {
                    FmStorageHelper::delete($a->disk, $a->ruta_fisica);
                }

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_PURGAR,
                    FmAuditHelper::ENTIDAD_CARPETA,
                    0,
                    [
                        'scope'           => $esAdmin ? 'global' : 'propio',
                        'carpetas_count'  => count($carpetaIds),
                        'archivos_count'  => count($archivoIds),
                    ],
                    null
                );

                return [
                    'carpetas' => count($carpetaIds),
                    'archivos' => count($archivoIds),
                ];
            });

            return response()->json(RespuestaApi::returnResultado(
                'success',
                'Papelera vaciada (' . $resultado['carpetas'] . ' carpetas, ' . $resultado['archivos'] . ' archivos)',
                $resultado
            ));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al vaciar papelera', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}
