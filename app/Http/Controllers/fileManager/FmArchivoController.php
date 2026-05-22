<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmArbolHelper;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\fileManager\FmStorageHelper;
use App\Models\fileManager\FmArchivoUsuario;
use App\Models\fileManager\FmCarpetaUsuario;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmCarpeta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FmArchivoController extends Controller
{
    /**
     * Extensiones bloqueadas por seguridad (defensa mínima).
     */
    private const EXTENSIONES_BLOQUEADAS = ['php', 'phtml', 'phar', 'htaccess', 'exe', 'bat', 'cmd', 'sh'];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ------------------------------------------------------------------------
    // Upload
    // ------------------------------------------------------------------------

    /**
     * POST /file/upload
     * Multipart: carpeta_id + archivos[].
     * Sube uno o varios archivos a la carpeta indicada.
     */
    public function upload(Request $request)
    {
        $log = new Funciones();

        $maxMb = (int) env('FM_MAX_UPLOAD_MB', 100);
        $maxKb = $maxMb * 1024;

        $validator = Validator::make($request->all(), [
            'carpeta_id' => 'required|integer',
            'archivos'   => 'required|array|min:1',
            'archivos.*' => 'required|file|max:' . $maxKb,
        ], [
            'archivos.required' => 'Debe adjuntar al menos un archivo',
            'archivos.*.max'    => "Cada archivo debe pesar menos de {$maxMb}MB",
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $carpetaIdValidacion = (int) $request->input('carpeta_id');
            // La raíz id=1 es de uso común: cualquier usuario autenticado puede subir ahí.
            // Para otras carpetas, validar permiso.
            if ($carpetaIdValidacion !== 1 &&
                !FmPermisosHelper::puedeRealizarAccion('subir_archivos', 'carpeta', $carpetaIdValidacion)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para subir archivos en esta carpeta', null));
            }

            // Políticas de colisión por archivo. Frontend las setea tras preguntar al
            // usuario en el diálogo de conflicto. Claves: nombre original del archivo
            // (tal como vino en archivos[]). Valores permitidos: 'reemplazar' | 'mantener_ambos' | 'omitir'.
            $politicasRaw = $request->input('politicas');
            $politicas = [];
            if (is_string($politicasRaw)) {
                $decoded = json_decode($politicasRaw, true);
                if (is_array($decoded)) {
                    $politicas = $decoded;
                }
            } elseif (is_array($politicasRaw)) {
                $politicas = $politicasRaw;
            }

            $resultado = DB::transaction(function () use ($request, $log, $politicas) {
                $carpetaId = (int) $request->input('carpeta_id');

                $carpeta = FmCarpeta::find($carpetaId);
                if (!$carpeta) {
                    throw new Exception('Carpeta destino no encontrada');
                }

                $archivosGuardados = [];
                $rutasFisicasGuardadas = [];
                $omitidos = [];

                foreach ($request->file('archivos') as $archivoSubido) {
                    $nombreOriginal = $archivoSubido->getClientOriginalName();
                    $extension = strtolower($archivoSubido->getClientOriginalExtension());

                    if (in_array($extension, self::EXTENSIONES_BLOQUEADAS, true)) {
                        throw new Exception("Extensión no permitida: .{$extension}");
                    }

                    // Versionado: si ya existe un archivo con ese nombre en la carpeta,
                    // aplicar la política decidida por el usuario (default: reemplazar).
                    $existente = FmArchivo::where('carpeta_id', $carpetaId)
                        ->where('nombre', $nombreOriginal)
                        ->where('es_version_actual', true)
                        ->whereNull('deleted_at')
                        ->first();

                    $esNuevaVersion = false;
                    $versionNum = 1;
                    $archivoPadreId = null;
                    $nombreFinal = $nombreOriginal;

                    if ($existente) {
                        $politica = $politicas[$nombreOriginal] ?? 'reemplazar';

                        if ($politica === 'omitir') {
                            $omitidos[] = $nombreOriginal;
                            continue;
                        }

                        if ($politica === 'mantener_ambos') {
                            $nombreFinal = $this->generarNombreDisponible($carpetaId, $nombreOriginal);
                            // Tratar como archivo nuevo (no es versión de nada)
                        } else { // 'reemplazar' (default)
                            if (!FmPermisosHelper::puedeRealizarAccion('editar_contenido', 'archivo', (int) $existente->id)) {
                                throw new Exception("No tiene permiso para crear nuevas versiones de '{$nombreOriginal}'");
                            }
                            $esNuevaVersion = true;
                            $versionNum = $existente->version + 1;
                            $archivoPadreId = $existente->archivo_padre_id ?? $existente->id;
                            $existente->update(['es_version_actual' => false]);
                        }
                    }

                    // Crear el registro inicial con metadata mínima (necesitamos id para el path físico)
                    $archivo = FmArchivo::create([
                        'carpeta_id'         => $carpetaId,
                        'nombre'             => $nombreFinal,
                        'extension'          => $extension !== '' ? $extension : null,
                        'mime_type'          => $archivoSubido->getClientMimeType() ?: null,
                        'tamano_bytes'       => (int) $archivoSubido->getSize(),
                        'ruta_fisica'        => 'pending',
                        'disk'               => FmStorageHelper::diskName(),
                        'version'            => $versionNum,
                        'archivo_padre_id'   => $archivoPadreId,
                        'es_version_actual'  => true,
                        'creado_por'         => Auth::id(),
                    ]);

                    // Guardar físico ahora que tenemos el id
                    $meta = FmStorageHelper::store($archivoSubido, $archivo->id);
                    $rutasFisicasGuardadas[] = ['disk' => $meta['disk'], 'ruta' => $meta['ruta_fisica']];

                    // Actualizar el registro con los metadatos definitivos
                    $archivo->update([
                        'ruta_fisica' => $meta['ruta_fisica'],
                        'disk'        => $meta['disk'],
                        'mime_type'   => $meta['mime_type'],
                        'tamano_bytes'=> $meta['tamano_bytes'],
                        'extension'   => $meta['extension'],
                        'hash_sha256' => $meta['hash_sha256'],
                    ]);

                    // Creator gets admin: el creador del archivo recibe todos los permisos
                    // (sólo si es archivo nuevo — para nuevas versiones se hereda del padre)
                    if (!$esNuevaVersion) {
                        FmArchivoUsuario::create([
                            'archivo_id'               => $archivo->id,
                            'user_id'                  => Auth::id(),
                            'puede_ver'                => true,
                            'puede_descargar'          => true,
                            'puede_renombrar'          => true,
                            'puede_editar_contenido'   => true,
                            'puede_eliminar'           => true,
                            'puede_mover'              => true,
                            'puede_gestionar_permisos' => true,
                            'otorgado_por'             => Auth::id(),
                        ]);
                    } else {
                        // Copiar permisos directos del archivo padre a la nueva versión
                        FmArchivoUsuario::where('archivo_id', $archivoPadreId)
                            ->get()
                            ->each(function ($p) use ($archivo) {
                                FmArchivoUsuario::create([
                                    'archivo_id'               => $archivo->id,
                                    'user_id'                  => $p->user_id,
                                    'puede_ver'                => $p->puede_ver,
                                    'puede_descargar'          => $p->puede_descargar,
                                    'puede_renombrar'          => $p->puede_renombrar,
                                    'puede_editar_contenido'   => $p->puede_editar_contenido,
                                    'puede_eliminar'           => $p->puede_eliminar,
                                    'puede_mover'              => $p->puede_mover,
                                    'puede_gestionar_permisos' => $p->puede_gestionar_permisos,
                                    'otorgado_por'             => Auth::id(),
                                ]);
                            });
                    }

                    FmAuditHelper::registrar(
                        $esNuevaVersion ? FmAuditHelper::ACCION_NUEVA_VERSION : FmAuditHelper::ACCION_UPLOAD,
                        FmAuditHelper::ENTIDAD_ARCHIVO,
                        $archivo->id,
                        null,
                        $archivo->fresh()->toArray()
                    );

                    $archivosGuardados[] = $archivo->fresh();
                }

                return ['archivos' => $archivosGuardados, 'rutas_fisicas' => $rutasFisicasGuardadas, 'omitidos' => $omitidos];
            });

            $log->logInfo(self::class, count($resultado['archivos']) . ' archivo(s) subido(s)');
            $msg = 'Archivos subidos (' . count($resultado['archivos']) . ')';
            if (!empty($resultado['omitidos'])) {
                $msg .= ' — omitidos: ' . implode(', ', $resultado['omitidos']);
            }
            return response()->json(RespuestaApi::returnResultado('success', $msg, [
                'archivos' => $resultado['archivos'],
                'omitidos' => $resultado['omitidos'],
            ]));
        } catch (Exception $e) {
            // En el catch, la transacción ya hizo rollback de los registros DB.
            // Pero los archivos físicos podrían haber quedado huérfanos antes de fallar.
            // No tenemos acceso a $rutasFisicasGuardadas fuera de la transacción.
            // El comando 'fm:limpiar-huerfanos' se encargará de eso en mantenimiento (F6).
            $log->logError(self::class, 'Error al subir archivos', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Download / Preview
    // ------------------------------------------------------------------------

    /**
     * GET /file/{id}/download
     */
    public function download($id)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($id);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }

            if (!FmPermisosHelper::puedeRealizarAccion('descargar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para descargar este archivo', null));
            }

            if (!FmStorageHelper::exists($archivo->disk, $archivo->ruta_fisica)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo físico no disponible', null));
            }

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_DOWNLOAD,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivo->id,
                null,
                ['nombre' => $archivo->nombre]
            );

            return FmStorageHelper::download($archivo->disk, $archivo->ruta_fisica, $archivo->nombre);
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al descargar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    /**
     * GET /file/{id}/preview
     * Stream inline (para iframe/img/video).
     */
    public function preview($id)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($id);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }

            if (!FmPermisosHelper::puedeRealizarAccion('descargar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para previsualizar este archivo', null));
            }

            if (!FmStorageHelper::exists($archivo->disk, $archivo->ruta_fisica)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo físico no disponible', null));
            }

            return FmStorageHelper::streamPreview(
                $archivo->disk,
                $archivo->ruta_fisica,
                $archivo->mime_type,
                $archivo->nombre
            );
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al previsualizar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Rename / Delete / Restore / Show
    // ------------------------------------------------------------------------

    /**
     * PUT /file/{id}/rename
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
            if (!FmPermisosHelper::puedeRealizarAccion('renombrar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para renombrar este archivo', null));
            }

            $archivo = DB::transaction(function () use ($id, $request) {
                $archivo = FmArchivo::find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }

                $nombre = trim($request->input('nombre'));

                // Unique entre archivos de la misma carpeta + versión actual
                $existe = FmArchivo::where('carpeta_id', $archivo->carpeta_id)
                    ->where('nombre', $nombre)
                    ->where('es_version_actual', true)
                    ->where('id', '!=', $id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($existe) {
                    throw new Exception('Ya existe un archivo con ese nombre en esta carpeta');
                }

                $antes = $archivo->toArray();

                // Recalcular extensión si cambió
                $info = pathinfo($nombre);
                $nuevaExt = isset($info['extension']) ? strtolower($info['extension']) : null;

                $archivo->update([
                    'nombre'    => $nombre,
                    'extension' => $nuevaExt,
                ]);

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_RENOMBRAR,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    $archivo->id,
                    $antes,
                    $archivo->fresh()->toArray()
                );

                return $archivo->fresh();
            });

            $log->logInfo(self::class, 'Archivo renombrado #' . $archivo->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Archivo renombrado', $archivo));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al renombrar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /file/{id}
     * Soft delete. El archivo físico se conserva hasta que el comando
     * fm:purgar-papelera lo elimine pasados N días.
     */
    public function delete($id)
    {
        $log = new Funciones();
        try {
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para eliminar este archivo', null));
            }

            DB::transaction(function () use ($id) {
                $archivo = FmArchivo::find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }

                $antes = $archivo->toArray();

                // Soft delete de todo el linaje (versión actual + históricas)
                $idsLinaje = $this->idsLinaje($archivo, true);
                FmArchivo::withTrashed()->whereIn('id', $idsLinaje)->delete();

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_ELIMINAR,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    (int) $id,
                    array_merge($antes, ['versiones_eliminadas' => count($idsLinaje)]),
                    null
                );
            });

            $log->logInfo(self::class, 'Archivo eliminado #' . $id . ' (con linaje)');
            return response()->json(RespuestaApi::returnResultado('success', 'Archivo eliminado', null));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al eliminar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * POST /file/{id}/restore
     */
    public function restore($id)
    {
        $log = new Funciones();
        try {
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para restaurar este archivo', null));
            }

            $archivo = DB::transaction(function () use ($id) {
                $archivo = FmArchivo::withTrashed()->find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }
                if (!$archivo->trashed()) {
                    throw new Exception('El archivo no está en la papelera');
                }

                // Auto-restaura la cadena de ancestros eliminados, así el archivo
                // siempre vuelve a quedar "alcanzable" sin requerir pasos previos del usuario.
                $carpeta = FmCarpeta::withTrashed()->find($archivo->carpeta_id);
                if (!$carpeta) {
                    throw new Exception('La carpeta destino no existe.');
                }

                $ancestrosARestaurar = [];
                $cursor = $carpeta;
                while ($cursor && $cursor->trashed()) {
                    $ancestrosARestaurar[] = $cursor;
                    if ($cursor->parent_id === null) {
                        break;
                    }
                    $cursor = FmCarpeta::withTrashed()->find($cursor->parent_id);
                }
                // Restaurar de raíz hacia hoja
                foreach (array_reverse($ancestrosARestaurar) as $c) {
                    $c->restore();
                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_RESTAURAR,
                        FmAuditHelper::ENTIDAD_CARPETA,
                        $c->id,
                        null,
                        $c->fresh()->toArray()
                    );
                }

                // Restaurar todo el linaje (versión actual + históricas)
                $idsLinaje = $this->idsLinaje($archivo, true);
                FmArchivo::withTrashed()->whereIn('id', $idsLinaje)->restore();

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_RESTAURAR,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    $archivo->id,
                    null,
                    array_merge($archivo->fresh()->toArray(), ['versiones_restauradas' => count($idsLinaje)])
                );

                return $archivo->fresh();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Archivo restaurado', $archivo));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al restaurar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /file/{id}/permanente
     * Borra definitivamente el archivo (registro DB + binario en disco).
     * Requiere que el archivo esté soft-deleted (en papelera).
     */
    public function deletePermanente($id)
    {
        $log = new Funciones();
        try {
            if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para purgar este archivo', null));
            }

            DB::transaction(function () use ($id) {
                $archivo = FmArchivo::withTrashed()->find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }
                if (!$archivo->trashed()) {
                    throw new Exception('El archivo debe estar en la papelera antes de purgarlo');
                }

                $antes = $archivo->toArray();

                // Purgar todo el linaje (versión actual + históricas) + binarios.
                $idsLinaje = $this->idsLinaje($archivo, true);
                $versiones = FmArchivo::withTrashed()
                    ->whereIn('id', $idsLinaje)
                    ->get(['id', 'disk', 'ruta_fisica']);

                // FK cascades borran permisos y pivot de tags (fm_archivo_usuario, fm_archivo_tag).
                FmArchivo::withTrashed()->whereIn('id', $idsLinaje)->forceDelete();

                foreach ($versiones as $v) {
                    FmStorageHelper::delete($v->disk, $v->ruta_fisica);
                }

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_PURGAR,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    (int) $id,
                    array_merge($antes, ['versiones_purgadas' => count($idsLinaje)]),
                    null
                );
            });

            $log->logInfo(self::class, 'Archivo purgado #' . $id);
            return response()->json(RespuestaApi::returnResultado('success', 'Archivo eliminado permanentemente', null));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al purgar archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * GET /file/{id}
     */
    public function show($id)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($id);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('ver', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene acceso a este archivo', null));
            }
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $archivo));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al obtener archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Move
    // ------------------------------------------------------------------------

    /**
     * PUT /file/{id}/move
     * Mueve el archivo a otra carpeta.
     */
    public function move($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nuevo_carpeta_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            if (!FmPermisosHelper::puedeRealizarAccion('mover', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover este archivo', null));
            }
            $destinoIdVal = (int) $request->input('nuevo_carpeta_id');
            if ($destinoIdVal !== 1 &&
                !FmPermisosHelper::puedeRealizarAccion('subir_archivos', 'carpeta', $destinoIdVal)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover a la carpeta destino', null));
            }

            $archivo = DB::transaction(function () use ($id, $request) {
                $archivo = FmArchivo::find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }

                $nuevoCarpetaId = (int) $request->input('nuevo_carpeta_id');

                $destino = FmCarpeta::find($nuevoCarpetaId);
                if (!$destino) {
                    throw new Exception('Carpeta destino no encontrada');
                }

                if ($archivo->carpeta_id === $nuevoCarpetaId) {
                    return $archivo;
                }

                // Nombre único en destino
                $existe = FmArchivo::where('carpeta_id', $nuevoCarpetaId)
                    ->where('nombre', $archivo->nombre)
                    ->where('es_version_actual', true)
                    ->where('id', '!=', $id)
                    ->whereNull('deleted_at')
                    ->exists();
                if ($existe) {
                    throw new Exception("Ya existe un archivo con el nombre '{$archivo->nombre}' en la carpeta destino");
                }

                $antes = $archivo->toArray();
                $archivo->update(['carpeta_id' => $nuevoCarpetaId]);

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_MOVER,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    $archivo->id,
                    $antes,
                    $archivo->fresh()->toArray()
                );

                return $archivo->fresh();
            });

            $log->logInfo(self::class, 'Archivo movido #' . $archivo->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Archivo movido', $archivo));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al mover archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Upload de carpeta jerárquica (drag&drop desde PC)
    // ------------------------------------------------------------------------

    /**
     * POST /file/upload-folder
     * Recibe FormData con:
     *   - carpeta_id (base destino)
     *   - paths[]:    array de strings con ruta relativa al destino + nombre archivo
     *                 (ej: "Sistemas/Software/archivo1.pdf")
     *   - archivos[]: array de UploadedFile en el MISMO orden que paths[]
     *
     * Crea las carpetas espejo (reusando si ya existen por nombre+parent) y sube
     * los archivos en la carpeta correspondiente.
     */
    public function uploadFolder(Request $request)
    {
        $log = new Funciones();

        $maxMb = (int) env('FM_MAX_UPLOAD_MB', 100);
        $maxKb = $maxMb * 1024;

        $validator = Validator::make($request->all(), [
            'carpeta_id' => 'required|integer',
            'paths'      => 'required|array|min:1',
            'paths.*'    => 'required|string',
            'archivos'   => 'required|array|min:1',
            'archivos.*' => 'required|file|max:' . $maxKb,
        ], [
            'archivos.*.max' => "Cada archivo debe pesar menos de {$maxMb}MB",
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        $paths = $request->input('paths');
        $archivos = $request->file('archivos');

        if (count($paths) !== count($archivos)) {
            return response()->json(RespuestaApi::returnResultado(
                'error',
                'paths[] y archivos[] deben tener la misma longitud',
                null
            ));
        }

        try {
            $carpetaBaseIdVal = (int) $request->input('carpeta_id');
            if ($carpetaBaseIdVal !== 1 &&
                !FmPermisosHelper::puedeRealizarAccion('subir_archivos', 'carpeta', $carpetaBaseIdVal)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para subir en la carpeta base', null));
            }

            $resultado = DB::transaction(function () use ($request, $paths, $archivos) {
                $carpetaBaseId = (int) $request->input('carpeta_id');
                $carpetaBase = FmCarpeta::find($carpetaBaseId);
                if (!$carpetaBase) {
                    throw new Exception('Carpeta base destino no encontrada');
                }

                $archivosGuardados = [];
                $cacheCarpetas = []; // key = "parent_id|nombre" -> id (creadas o reusadas en esta transacción)

                foreach ($paths as $i => $pathCompleto) {
                    $partes = array_values(array_filter(explode('/', $pathCompleto), fn ($p) => $p !== ''));
                    if (empty($partes)) {
                        throw new Exception("Path inválido: '{$pathCompleto}'");
                    }

                    $nombreArchivo = array_pop($partes);
                    $extension = strtolower(pathinfo($nombreArchivo, PATHINFO_EXTENSION));
                    if (in_array($extension, self::EXTENSIONES_BLOQUEADAS, true)) {
                        throw new Exception("Extensión no permitida: .{$extension}");
                    }

                    // Resolver jerarquía de carpetas creando o reusando
                    $parentId = $carpetaBaseId;
                    foreach ($partes as $nombreCarpeta) {
                        $nombreCarpeta = trim($nombreCarpeta);
                        if ($nombreCarpeta === '') continue;

                        $cacheKey = $parentId . '|' . $nombreCarpeta;
                        if (isset($cacheCarpetas[$cacheKey])) {
                            $parentId = $cacheCarpetas[$cacheKey];
                            continue;
                        }

                        $existente = FmCarpeta::where('parent_id', $parentId)
                            ->where('nombre', $nombreCarpeta)
                            ->whereNull('deleted_at')
                            ->first();

                        if ($existente) {
                            $cacheCarpetas[$cacheKey] = $existente->id;
                            $parentId = $existente->id;
                            continue;
                        }

                        // Crear carpeta nueva
                        $parent = FmCarpeta::find($parentId);
                        $paths = FmArbolHelper::calcularPathParaNuevaCarpeta($parent);

                        $nueva = FmCarpeta::create([
                            'nombre'            => $nombreCarpeta,
                            'parent_id'         => $parentId,
                            'materialized_path' => $paths['materialized_path'],
                            'nivel'             => $paths['nivel'],
                            'creado_por'        => Auth::id(),
                        ]);

                        FmAuditHelper::registrar(
                            FmAuditHelper::ACCION_CREAR_CARPETA,
                            FmAuditHelper::ENTIDAD_CARPETA,
                            $nueva->id,
                            null,
                            $nueva->toArray()
                        );

                        $cacheCarpetas[$cacheKey] = $nueva->id;
                        $parentId = $nueva->id;
                    }

                    // Subir archivo en la carpeta final ($parentId)
                    $archivoSubido = $archivos[$i];

                    // Detectar conflicto: si ya existe archivo con el mismo nombre en la carpeta final
                    $existeArchivo = FmArchivo::where('carpeta_id', $parentId)
                        ->where('nombre', $nombreArchivo)
                        ->where('es_version_actual', true)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($existeArchivo) {
                        throw new Exception("Ya existe un archivo con el nombre '{$nombreArchivo}' en la ubicación '{$pathCompleto}'");
                    }

                    $archivo = FmArchivo::create([
                        'carpeta_id'        => $parentId,
                        'nombre'            => $nombreArchivo,
                        'extension'         => $extension !== '' ? $extension : null,
                        'mime_type'         => $archivoSubido->getClientMimeType() ?: null,
                        'tamano_bytes'      => (int) $archivoSubido->getSize(),
                        'ruta_fisica'       => 'pending',
                        'disk'              => FmStorageHelper::diskName(),
                        'version'           => 1,
                        'es_version_actual' => true,
                        'creado_por'        => Auth::id(),
                    ]);

                    $meta = FmStorageHelper::store($archivoSubido, $archivo->id);

                    $archivo->update([
                        'ruta_fisica'  => $meta['ruta_fisica'],
                        'disk'         => $meta['disk'],
                        'mime_type'    => $meta['mime_type'],
                        'tamano_bytes' => $meta['tamano_bytes'],
                        'extension'    => $meta['extension'],
                        'hash_sha256'  => $meta['hash_sha256'],
                    ]);

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_UPLOAD,
                        FmAuditHelper::ENTIDAD_ARCHIVO,
                        $archivo->id,
                        null,
                        $archivo->fresh()->toArray()
                    );

                    $archivosGuardados[] = $archivo->fresh();
                }

                return [
                    'archivos'           => $archivosGuardados,
                    'carpetas_creadas'   => count($cacheCarpetas),
                    'archivos_subidos'   => count($archivosGuardados),
                ];
            });

            $log->logInfo(self::class, "upload-folder: {$resultado['carpetas_creadas']} carpeta(s) + {$resultado['archivos_subidos']} archivo(s)");
            return response()->json(RespuestaApi::returnResultado('success', 'Carpeta subida con éxito', $resultado['archivos']));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al subir carpeta jerárquica', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Versionado
    // ------------------------------------------------------------------------

    /**
     * Genera el siguiente nombre disponible "nombre (N).ext" cuando el original
     * colide con un archivo existente en la carpeta. Política "mantener ambos".
     */
    private function generarNombreDisponible(int $carpetaId, string $nombreOriginal): string
    {
        $info = pathinfo($nombreOriginal);
        $base = $info['filename'] ?? $nombreOriginal;
        $ext = isset($info['extension']) ? '.' . $info['extension'] : '';

        $intento = 1;
        do {
            $candidato = $base . ' (' . $intento . ')' . $ext;
            $existe = FmArchivo::where('carpeta_id', $carpetaId)
                ->where('nombre', $candidato)
                ->where('es_version_actual', true)
                ->whereNull('deleted_at')
                ->exists();
            $intento++;
        } while ($existe && $intento < 1000);

        return $candidato;
    }

    /**
     * POST /file/check-colisiones
     * Recibe {carpeta_id, nombres[]} y devuelve los que ya existen en la carpeta.
     * Lo usa el frontend antes de subir para mostrar diálogo de conflicto.
     */
    public function checkColisiones(Request $request)
    {
        $log = new Funciones();
        try {
            $validator = Validator::make($request->all(), [
                'carpeta_id' => 'required|integer',
                'nombres'    => 'required|array',
                'nombres.*'  => 'string',
            ]);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
            }

            $carpetaId = (int) $request->input('carpeta_id');
            $nombres = $request->input('nombres');

            $colisiones = FmArchivo::where('carpeta_id', $carpetaId)
                ->whereIn('nombre', $nombres)
                ->where('es_version_actual', true)
                ->whereNull('deleted_at')
                ->pluck('nombre')
                ->toArray();

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'colisiones' => $colisiones,
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en checkColisiones', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * Devuelve los IDs de todas las versiones (actual + históricas) pertenecientes
     * al mismo linaje del archivo $id, sin importar si $id es la raíz o no.
     * Si $incluirTrashed=true incluye también versiones soft-deleted (necesario
     * para operaciones de papelera).
     */
    private function idsLinaje(FmArchivo $archivo, bool $incluirTrashed = false): array
    {
        $rootId = $archivo->archivo_padre_id ?? $archivo->id;
        $query = $incluirTrashed ? FmArchivo::withTrashed() : FmArchivo::query();
        return $query->where(function ($q) use ($rootId) {
            $q->where('id', $rootId)->orWhere('archivo_padre_id', $rootId);
        })->pluck('id')->toArray();
    }

    /**
     * GET /file/{id}/versiones
     * Lista todas las versiones (actual + históricas) del linaje del archivo.
     */
    public function versiones($id)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($id);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('ver', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para ver este archivo', null));
            }

            $ids = $this->idsLinaje($archivo);
            $versiones = FmArchivo::whereIn('id', $ids)
                ->orderBy('version', 'desc')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'OK', $versiones));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar versiones de archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * GET /file/{id}/versiones/{versionId}/download
     * Descarga una versión histórica concreta del linaje del archivo.
     */
    public function descargarVersion($id, $versionId)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($id);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('descargar', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para descargar este archivo', null));
            }

            $idsLinaje = $this->idsLinaje($archivo);
            if (!in_array((int) $versionId, $idsLinaje, true)) {
                return response()->json(RespuestaApi::returnResultado('error', 'La versión no pertenece a este archivo', null));
            }

            $version = FmArchivo::find($versionId);
            if (!$version) {
                return response()->json(RespuestaApi::returnResultado('error', 'Versión no encontrada', null));
            }
            if (!FmStorageHelper::exists($version->disk, $version->ruta_fisica)) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo físico no disponible', null));
            }

            $nombreDescarga = pathinfo($version->nombre, PATHINFO_FILENAME)
                . '_v' . $version->version
                . ($version->extension ? '.' . $version->extension : '');

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_DOWNLOAD,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $version->id,
                null,
                ['nombre' => $nombreDescarga, 'es_version' => true]
            );

            return FmStorageHelper::download($version->disk, $version->ruta_fisica, $nombreDescarga);
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al descargar versión ' . $versionId . ' de archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * POST /file/{id}/versiones/{versionId}/restaurar
     * Promueve una versión histórica a versión actual. La actual pasa a ser histórica.
     */
    public function restaurarVersion($id, $versionId)
    {
        $log = new Funciones();
        try {
            if (!FmPermisosHelper::puedeRealizarAccion('editar_contenido', 'archivo', (int) $id)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para restaurar versiones de este archivo', null));
            }

            $resultado = DB::transaction(function () use ($id, $versionId) {
                $archivo = FmArchivo::find($id);
                if (!$archivo) {
                    throw new Exception('Archivo no encontrado');
                }

                $idsLinaje = $this->idsLinaje($archivo);
                if (!in_array((int) $versionId, $idsLinaje, true)) {
                    throw new Exception('La versión no pertenece a este archivo');
                }
                if ((int) $versionId === (int) $id) {
                    throw new Exception('La versión ya es la actual');
                }

                $nuevaActual = FmArchivo::find($versionId);
                if (!$nuevaActual) {
                    throw new Exception('Versión no encontrada');
                }

                FmArchivo::whereIn('id', $idsLinaje)->update(['es_version_actual' => false]);
                $nuevaActual->update(['es_version_actual' => true]);

                FmAuditHelper::registrar(
                    FmAuditHelper::ACCION_NUEVA_VERSION,
                    FmAuditHelper::ENTIDAD_ARCHIVO,
                    $nuevaActual->id,
                    ['version_previa_id' => $archivo->id, 'version_previa' => $archivo->version],
                    ['promovida_a_actual' => true, 'version' => $nuevaActual->version]
                );

                return $nuevaActual->fresh();
            });

            $log->logInfo(self::class, "Versión {$versionId} promovida a actual (linaje archivo #{$id})");
            return response()->json(RespuestaApi::returnResultado('success', 'Versión restaurada como actual', $resultado));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al restaurar versión ' . $versionId . ' de archivo ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}
