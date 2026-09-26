<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmAuditHelper;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmTag;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class FmTagController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // ------------------------------------------------------------------------
    // CRUD de tags
    // ------------------------------------------------------------------------

    public function index()
    {
        $log = new Funciones();
        try {
            $data = FmTag::orderBy('nombre')->get();
            return response()->json(RespuestaApi::returnResultado('success', 'OK', $data));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al listar tags', $e);
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function store(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'color'  => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $nombre = trim($request->input('nombre'));

            $existe = FmTag::where('nombre', $nombre)->exists();
            if ($existe) {
                return response()->json(RespuestaApi::returnResultado('error', "Ya existe un tag con el nombre '{$nombre}'", null));
            }

            $tag = FmTag::create([
                'nombre'     => $nombre,
                'color'      => $request->input('color'),
                'creado_por' => Auth::id(),
            ]);

            $log->logInfo(self::class, 'Tag creado #' . $tag->id);
            return response()->json(RespuestaApi::returnResultado('success', 'Tag creado', $tag));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al crear tag', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function update($id, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'color'  => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $tag = FmTag::find($id);
            if (!$tag) {
                return response()->json(RespuestaApi::returnResultado('error', 'Tag no encontrado', null));
            }

            $nombre = trim($request->input('nombre'));

            $existe = FmTag::where('nombre', $nombre)->where('id', '!=', $id)->exists();
            if ($existe) {
                return response()->json(RespuestaApi::returnResultado('error', "Ya existe un tag con el nombre '{$nombre}'", null));
            }

            $tag->update([
                'nombre' => $nombre,
                'color'  => $request->input('color'),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Tag actualizado', $tag->fresh()));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al actualizar tag ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    public function delete($id)
    {
        $log = new Funciones();
        try {
            $tag = FmTag::find($id);
            if (!$tag) {
                return response()->json(RespuestaApi::returnResultado('error', 'Tag no encontrado', null));
            }
            // La FK de fm_archivo_tag está ON DELETE CASCADE, así que las asignaciones
            // se borran automáticamente al eliminar el tag.
            $tag->delete();
            return response()->json(RespuestaApi::returnResultado('success', 'Tag eliminado', null));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al eliminar tag ' . $id, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    // ------------------------------------------------------------------------
    // Asignación archivo ↔ tags
    // ------------------------------------------------------------------------

    /**
     * POST /file/{archivoId}/tags
     * Body: { tag_ids: number[] }
     * Hace sync (reemplaza la lista completa de tags del archivo).
     */
    public function asignarTags($archivoId, Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'tag_ids'   => 'present|array',
            'tag_ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $archivo = FmArchivo::find($archivoId);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('renombrar', 'archivo', (int) $archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para modificar tags de este archivo', null));
            }

            $tagIds = array_values(array_unique(array_map('intval', (array) $request->input('tag_ids', []))));

            DB::transaction(function () use ($archivo, $tagIds) {
                $archivo->tags()->sync($tagIds);
            });

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_ASIGNAR_TAG,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivo->id,
                null,
                ['tag_ids' => $tagIds]
            );

            $log->logInfo(self::class, "Tags asignados al archivo #{$archivo->id}: " . count($tagIds));
            return response()->json(RespuestaApi::returnResultado('success', 'Tags asignados', $archivo->fresh(['tags'])));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error al asignar tags al archivo ' . $archivoId, $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }

    /**
     * DELETE /file/{archivoId}/tags/{tagId}
     * Quita la asignación de un tag específico.
     */
    public function quitarTag($archivoId, $tagId)
    {
        $log = new Funciones();
        try {
            $archivo = FmArchivo::find($archivoId);
            if (!$archivo) {
                return response()->json(RespuestaApi::returnResultado('error', 'Archivo no encontrado', null));
            }
            if (!FmPermisosHelper::puedeRealizarAccion('renombrar', 'archivo', (int) $archivoId)) {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene permiso para modificar tags de este archivo', null));
            }

            $archivo->tags()->detach($tagId);

            FmAuditHelper::registrar(
                FmAuditHelper::ACCION_QUITAR_TAG,
                FmAuditHelper::ENTIDAD_ARCHIVO,
                $archivo->id,
                null,
                ['tag_id_removido' => (int) $tagId]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Tag removido', $archivo->fresh(['tags'])));
        } catch (Exception $e) {
            $log->logError(self::class, "Error al quitar tag {$tagId} del archivo {$archivoId}", $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}
