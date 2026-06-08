<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmArbolHelper;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\fileManager\FmStorageHelper;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmCarpeta;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * Operaciones en lote sobre múltiples carpetas y/o archivos del File Manager.
 * Cada endpoint recibe `archivo_ids[]` y `carpeta_ids[]` y procesa ambas
 * colecciones dentro de una sola transacción cuando aplica.
 */
class FmBulkController extends Controller
{
    /**
     * Raíz absoluta protegida: no puede eliminarse, moverse ni descargarse como item.
     */
    private const RAIZ_ID = 1;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ------------------------------------------------------------------------
    // Bulk delete (soft delete recursivo)
    // ------------------------------------------------------------------------

    public function bulkDelete(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'archivo_ids'   => 'nullable|array',
            'archivo_ids.*' => 'integer',
            'carpeta_ids'   => 'nullable|array',
            'carpeta_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        $archivoIds = array_values(array_unique(array_filter((array) $request->input('archivo_ids', []))));
        $carpetaIds = array_values(array_unique(array_filter((array) $request->input('carpeta_ids', []))));

        if (empty($archivoIds) && empty($carpetaIds)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionaron items para eliminar', null));
        }

        try {
            $resumen = DB::transaction(function () use ($archivoIds, $carpetaIds) {
                $contadorArchivos = 0;
                $contadorCarpetas = 0;
                $omitidosPorPermiso = 0;

                // ----- Carpetas (recursivo) -----
                foreach ($carpetaIds as $id) {
                    $id = (int) $id;
                    if ($id === self::RAIZ_ID) {
                        throw new Exception('No se puede eliminar la carpeta raíz');
                    }

                    $carpeta = FmCarpeta::find($id);
                    if (!$carpeta) continue;

                    // Validar permiso (saltar si no autorizado)
                    if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'carpeta', $id)) {
                        $omitidosPorPermiso++;
                        continue;
                    }

                    $antes = $carpeta->toArray();

                    $ids = FmArbolHelper::descendientesDe($id)->pluck('id')->toArray();
                    $ids[] = $id;

                    FmArchivo::whereIn('carpeta_id', $ids)->delete();
                    FmCarpeta::whereIn('id', $ids)->delete();

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_ELIMINAR,
                        FmAuditHelper::ENTIDAD_CARPETA,
                        $id,
                        $antes,
                        null
                    );
                    $contadorCarpetas++;
                }

                // ----- Archivos sueltos -----
                foreach ($archivoIds as $id) {
                    $id = (int) $id;
                    $archivo = FmArchivo::find($id);
                    if (!$archivo) continue;

                    // Validar permiso (saltar si no autorizado)
                    if (!FmPermisosHelper::puedeRealizarAccion('eliminar', 'archivo', $id)) {
                        $omitidosPorPermiso++;
                        continue;
                    }

                    $antes = $archivo->toArray();

                    // Soft delete del linaje completo (versión actual + históricas)
                    $rootId = $archivo->archivo_padre_id ?? $archivo->id;
                    FmArchivo::withTrashed()
                        ->where(function ($q) use ($rootId) {
                            $q->where('id', $rootId)->orWhere('archivo_padre_id', $rootId);
                        })
                        ->delete();

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_ELIMINAR,
                        FmAuditHelper::ENTIDAD_ARCHIVO,
                        $id,
                        $antes,
                        null
                    );
                    $contadorArchivos++;
                }

                return [
                    'archivos_eliminados'   => $contadorArchivos,
                    'carpetas_eliminadas'   => $contadorCarpetas,
                    'omitidos_por_permiso'  => $omitidosPorPermiso,
                ];
            });

            $log->logInfo(self::class, "bulkDelete: {$resumen['carpetas_eliminadas']} carpeta(s), {$resumen['archivos_eliminados']} archivo(s), {$resumen['omitidos_por_permiso']} omitido(s) por permiso");
            $msg = 'Items eliminados';
            if ($resumen['omitidos_por_permiso'] > 0) {
                $msg .= " ({$resumen['omitidos_por_permiso']} omitido(s) por falta de permiso)";
            }
            return response()->json(RespuestaApi::returnResultado('success', $msg, $resumen));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en bulkDelete', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Bulk move
    // ------------------------------------------------------------------------

    public function bulkMove(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nuevo_parent_id' => 'required|integer',
            'archivo_ids'     => 'nullable|array',
            'archivo_ids.*'   => 'integer',
            'carpeta_ids'     => 'nullable|array',
            'carpeta_ids.*'   => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        $nuevoParentId = (int) $request->input('nuevo_parent_id');
        $archivoIds = array_values(array_unique(array_filter((array) $request->input('archivo_ids', []))));
        $carpetaIds = array_values(array_unique(array_filter((array) $request->input('carpeta_ids', []))));

        if (empty($archivoIds) && empty($carpetaIds)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionaron items para mover', null));
        }

        try {
            // ----- Validar permiso sobre el destino (una sola vez) -----
            // La raíz id=1 es de uso común y no requiere validación de destino.
            if ($nuevoParentId !== self::RAIZ_ID) {
                $necesitaSubir = !empty($archivoIds);
                $necesitaCrearSub = !empty($carpetaIds);
                if ($necesitaSubir && !FmPermisosHelper::puedeRealizarAccion('subir_archivos', 'carpeta', $nuevoParentId)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover archivos a la carpeta destino', null));
                }
                if ($necesitaCrearSub && !FmPermisosHelper::puedeRealizarAccion('crear_subcarpetas', 'carpeta', $nuevoParentId)) {
                    return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para mover carpetas a la carpeta destino', null));
                }
            }

            $resumen = DB::transaction(function () use ($archivoIds, $carpetaIds, $nuevoParentId) {
                $destino = FmCarpeta::find($nuevoParentId);
                if (!$destino) {
                    throw new Exception('Carpeta destino no encontrada');
                }

                $movidasCarpetas = 0;
                $movidosArchivos = 0;
                $omitidosPorPermiso = 0;

                // ----- Carpetas -----
                foreach ($carpetaIds as $id) {
                    $id = (int) $id;
                    if ($id === self::RAIZ_ID) {
                        throw new Exception('No se puede mover la carpeta raíz');
                    }

                    $carpeta = FmCarpeta::find($id);
                    if (!$carpeta) continue;

                    // Validar permiso de mover (saltar si no autorizado)
                    if (!FmPermisosHelper::puedeRealizarAccion('mover', 'carpeta', $id)) {
                        $omitidosPorPermiso++;
                        continue;
                    }

                    if ($carpeta->parent_id === $nuevoParentId) continue;

                    if (!FmArbolHelper::validarNoCiclo($id, $nuevoParentId)) {
                        throw new Exception("No se puede mover '{$carpeta->nombre}': el destino es la misma carpeta o un descendiente");
                    }

                    $existe = FmCarpeta::where('parent_id', $nuevoParentId)
                        ->where('nombre', $carpeta->nombre)
                        ->where('id', '!=', $id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($existe) {
                        throw new Exception("Ya existe una carpeta '{$carpeta->nombre}' en el destino");
                    }

                    $antes = $carpeta->toArray();
                    FmArbolHelper::recalcularPathSubarbol($carpeta, $destino);
                    $carpeta->refresh();

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_MOVER,
                        FmAuditHelper::ENTIDAD_CARPETA,
                        $id,
                        $antes,
                        $carpeta->toArray()
                    );
                    $movidasCarpetas++;
                }

                // ----- Archivos -----
                foreach ($archivoIds as $id) {
                    $id = (int) $id;
                    $archivo = FmArchivo::find($id);
                    if (!$archivo) continue;

                    // Validar permiso de mover (saltar si no autorizado)
                    if (!FmPermisosHelper::puedeRealizarAccion('mover', 'archivo', $id)) {
                        $omitidosPorPermiso++;
                        continue;
                    }

                    if ($archivo->carpeta_id === $nuevoParentId) continue;

                    $existe = FmArchivo::where('carpeta_id', $nuevoParentId)
                        ->where('nombre', $archivo->nombre)
                        ->where('es_version_actual', true)
                        ->where('id', '!=', $id)
                        ->whereNull('deleted_at')
                        ->exists();
                    if ($existe) {
                        throw new Exception("Ya existe un archivo '{$archivo->nombre}' en el destino");
                    }

                    $antes = $archivo->toArray();
                    $archivo->update(['carpeta_id' => $nuevoParentId]);

                    FmAuditHelper::registrar(
                        FmAuditHelper::ACCION_MOVER,
                        FmAuditHelper::ENTIDAD_ARCHIVO,
                        $id,
                        $antes,
                        $archivo->fresh()->toArray()
                    );
                    $movidosArchivos++;
                }

                return [
                    'archivos_movidos'      => $movidosArchivos,
                    'carpetas_movidas'      => $movidasCarpetas,
                    'omitidos_por_permiso'  => $omitidosPorPermiso,
                ];
            });

            $log->logInfo(self::class, "bulkMove: {$resumen['carpetas_movidas']} carpeta(s), {$resumen['archivos_movidos']} archivo(s), {$resumen['omitidos_por_permiso']} omitido(s) por permiso");
            $msg = 'Items movidos';
            if ($resumen['omitidos_por_permiso'] > 0) {
                $msg .= " ({$resumen['omitidos_por_permiso']} omitido(s) por falta de permiso)";
            }
            return response()->json(RespuestaApi::returnResultado('success', $msg, $resumen));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en bulkMove', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Bulk download (ZIP)
    // ------------------------------------------------------------------------

    public function bulkDownload(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'archivo_ids'   => 'nullable|array',
            'archivo_ids.*' => 'integer',
            'carpeta_ids'   => 'nullable|array',
            'carpeta_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        $archivoIds = array_values(array_unique(array_filter((array) $request->input('archivo_ids', []))));
        $carpetaIds = array_values(array_unique(array_filter((array) $request->input('carpeta_ids', []))));

        if (empty($archivoIds) && empty($carpetaIds)) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se proporcionaron items para descargar', null));
        }

        $tempLocalDir = null;
        try {
            $zipFilename = 'file-manager-' . date('Ymd-His') . '.zip';
            $zipRelative = 'tmp/' . Str::uuid()->toString() . '.zip';
            $zipFullPath = Storage::disk('local')->path($zipRelative);

            // Asegurar que el directorio exista
            @mkdir(dirname($zipFullPath), 0775, true);

            // Directorio local temporal para archivos del NAS (si aplica)
            $tempLocalDir = Storage::disk('local')->path('tmp/' . Str::uuid()->toString() . '/');
            @mkdir($tempLocalDir, 0775, true);

            $zip = new ZipArchive();
            if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('No se pudo crear el archivo ZIP');
            }

            $omitidosPorPermiso = 0;

            // Archivos sueltos en la raíz del ZIP
            foreach ($archivoIds as $id) {
                $id = (int) $id;
                $archivo = FmArchivo::find($id);
                if (!$archivo) continue;
                if (!FmPermisosHelper::puedeRealizarAccion('descargar', 'archivo', $id)) {
                    $omitidosPorPermiso++;
                    continue;
                }
                $this->agregarArchivoAlZip($zip, $archivo, $archivo->nombre, $tempLocalDir);
            }

            // Carpetas (con su subárbol). Validamos permiso de descargar sobre
            // la carpeta misma; `agregarCarpetaAlZip` filtra internamente por archivo
            // a través de `archivosVisiblesEnCarpeta` (que respeta permisos).
            foreach ($carpetaIds as $id) {
                $id = (int) $id;
                $carpeta = FmCarpeta::find($id);
                if (!$carpeta) continue;
                if (!FmPermisosHelper::puedeRealizarAccion('descargar', 'carpeta', $id)) {
                    $omitidosPorPermiso++;
                    continue;
                }
                $this->agregarCarpetaAlZip($zip, $carpeta, $carpeta->nombre, $tempLocalDir);
            }

            $zip->close();

            // Audit
            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_DOWNLOAD,
                'bulk',
                0,
                null,
                ['archivos' => $archivoIds, 'carpetas' => $carpetaIds]
            );

            $log->logInfo(self::class, "bulkDownload: ZIP generado con " . count($archivoIds) . " archivo(s) y " . count($carpetaIds) . " carpeta(s)");

            return response()
                ->download($zipFullPath, $zipFilename)
                ->deleteFileAfterSend(true);
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en bulkDownload', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        } finally {
            // Limpiar directorio temporal de archivos NAS descargados
            if ($tempLocalDir && is_dir($tempLocalDir)) {
                $this->borrarDirRecursivo($tempLocalDir);
            }
        }
    }

    /**
     * Agrega un archivo al ZIP en la ruta interna `$pathEnZip`. Para archivos
     * almacenados en NAS, los descarga primero a $tempLocalDir.
     */
    private function agregarArchivoAlZip(ZipArchive $zip, FmArchivo $archivo, string $pathEnZip, string $tempLocalDir): void
    {
        if (!FmStorageHelper::exists($archivo->disk, $archivo->ruta_fisica)) {
            // Si el archivo físico no existe, lo saltamos silenciosamente (no rompemos el ZIP)
            return;
        }

        if ($archivo->disk === 'local') {
            $rutaLocal = Storage::disk('local')->path($archivo->ruta_fisica);
            $zip->addFile($rutaLocal, $pathEnZip);
        } else {
            // Archivo en NAS (FTP): descargar a tempLocalDir y luego agregar
            $contenido = Storage::disk($archivo->disk)->get($archivo->ruta_fisica);
            $rutaTmp = $tempLocalDir . $archivo->id . '-' . basename($archivo->ruta_fisica);
            file_put_contents($rutaTmp, $contenido);
            $zip->addFile($rutaTmp, $pathEnZip);
        }
    }

    /**
     * Agrega una carpeta y todo su contenido al ZIP, respetando jerarquía.
     */
    private function agregarCarpetaAlZip(ZipArchive $zip, FmCarpeta $carpeta, string $pathEnZip, string $tempLocalDir): void
    {
        // Crear entrada de carpeta vacía (necesario para que ZIP refleje la jerarquía)
        $zip->addEmptyDir($pathEnZip);

        // Archivos directos en esta carpeta
        $archivos = FmArchivo::where('carpeta_id', $carpeta->id)
            ->where('es_version_actual', true)
            ->get();
        foreach ($archivos as $archivo) {
            $this->agregarArchivoAlZip($zip, $archivo, $pathEnZip . '/' . $archivo->nombre, $tempLocalDir);
        }

        // Subcarpetas recursivamente
        $subcarpetas = FmCarpeta::where('parent_id', $carpeta->id)->get();
        foreach ($subcarpetas as $sub) {
            $this->agregarCarpetaAlZip($zip, $sub, $pathEnZip . '/' . $sub->nombre, $tempLocalDir);
        }
    }

    /**
     * Borra un directorio y todo su contenido. Sólo para tmp del bulkDownload.
     */
    private function borrarDirRecursivo(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = @scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->borrarDirRecursivo($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
