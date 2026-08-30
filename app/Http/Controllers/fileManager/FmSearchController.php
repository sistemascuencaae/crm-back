<?php

namespace App\Http\Controllers\fileManager;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\fileManager\FmPermisosHelper;
use App\Http\Resources\fileManager\FmQueryHelper;
use App\Http\Resources\RespuestaApi;
use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmCarpeta;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Búsqueda global del File Manager.
 *
 * GET /crm/file-manager/search?q=...&tipo=...&extension=...&desde=...&hasta=...
 *                            &tamano_min=...&tamano_max=...&tag_id=...&carpeta_id_scope=...
 */
class FmSearchController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function search(Request $request)
    {
        $log = new Funciones();

        $validator = Validator::make($request->all(), [
            'q'                => 'nullable|string|max:255',
            'tipo'             => 'nullable|in:carpeta,archivo,ambos',
            'extension'        => 'nullable|string|max:20',
            'desde'            => 'nullable|date',
            'hasta'            => 'nullable|date',
            'tamano_min'       => 'nullable|integer|min:0',
            'tamano_max'       => 'nullable|integer|min:0',
            'tag_id'           => 'nullable|integer',
            'carpeta_id_scope' => 'nullable|integer',
            'limit'            => 'nullable|integer|min:1|max:500',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->messages()));
        }

        try {
            $userId = Auth::id();
            $q = trim((string) $request->input('q', ''));
            $tipo = $request->input('tipo', 'ambos');
            $extension = $request->input('extension');
            $desde = $request->input('desde');
            $hasta = $request->input('hasta');
            $tamanoMin = $request->input('tamano_min');
            $tamanoMax = $request->input('tamano_max');
            $tagId = $request->input('tag_id');
            $scopeId = $request->input('carpeta_id_scope');
            $limit = (int) $request->input('limit', 100);

            // Si scope, pre-calcular el prefix de materialized_path del subárbol
            $scopePathPrefix = null;
            $scopeIdsCarpetas = null;
            if ($scopeId) {
                $scope = FmCarpeta::find($scopeId);
                if (!$scope) {
                    return response()->json(RespuestaApi::returnResultado('error', 'Carpeta de scope no encontrada', null));
                }
                $scopePathPrefix = $scope->materialized_path . $scope->id . '/';
                $scopeIdsCarpetas = FmCarpeta::where(function ($q) use ($scopePathPrefix, $scopeId) {
                    $q->where('materialized_path', 'LIKE', $scopePathPrefix . '%')
                      ->orWhere('id', $scopeId);
                })->pluck('id')->toArray();
            }

            $esAdmin = FmPermisosHelper::esAdmin($userId);
            $idsCarpetasVisibles = $esAdmin ? null : FmPermisosHelper::carpetasVisibles($userId)->all();

            // --- Carpetas ---
            $carpetas = collect();
            if ($tipo === 'carpeta' || $tipo === 'ambos') {
                $queryC = FmCarpeta::query()->where('id', '!=', 1); // excluir raíz

                if ($q !== '') {
                    $queryC->where('nombre', 'ILIKE', '%' . FmQueryHelper::escaparLike($q) . '%');
                }
                if ($desde) $queryC->where('created_at', '>=', $desde);
                if ($hasta) $queryC->where('created_at', '<=', $hasta);

                if (!$esAdmin) {
                    if (empty($idsCarpetasVisibles)) {
                        $queryC->whereRaw('1=0');
                    } else {
                        $queryC->whereIn('id', $idsCarpetasVisibles);
                    }
                }
                if ($scopeIdsCarpetas !== null) {
                    $queryC->whereIn('id', $scopeIdsCarpetas);
                }

                $carpetas = $queryC->orderBy('nombre')->limit($limit)->get();
            }

            // --- Archivos ---
            $archivos = collect();
            if ($tipo === 'archivo' || $tipo === 'ambos') {
                $queryA = FmArchivo::query()
                    ->where('es_version_actual', true);

                if ($q !== '') {
                    $queryA->where('nombre', 'ILIKE', '%' . FmQueryHelper::escaparLike($q) . '%');
                }
                if ($extension) {
                    $queryA->where('extension', strtolower($extension));
                }
                if ($desde) $queryA->where('created_at', '>=', $desde);
                if ($hasta) $queryA->where('created_at', '<=', $hasta);
                if ($tamanoMin !== null) $queryA->where('tamano_bytes', '>=', (int) $tamanoMin);
                if ($tamanoMax !== null) $queryA->where('tamano_bytes', '<=', (int) $tamanoMax);
                if ($tagId) {
                    $queryA->whereExists(function ($sub) use ($tagId) {
                        $sub->select(DB::raw(1))
                            ->from('crm.fm_archivo_tag')
                            ->whereColumn('archivo_id', 'crm.fm_archivo.id')
                            ->where('tag_id', (int) $tagId);
                    });
                }
                if ($scopeIdsCarpetas !== null) {
                    $queryA->whereIn('carpeta_id', $scopeIdsCarpetas);
                }

                if (!$esAdmin) {
                    $queryA->where(function ($w) use ($idsCarpetasVisibles, $userId) {
                        if (!empty($idsCarpetasVisibles)) {
                            $w->whereIn('carpeta_id', $idsCarpetasVisibles);
                        } else {
                            $w->whereRaw('1=0');
                        }
                        $w->orWhereExists(function ($sub) use ($userId) {
                            $sub->select(DB::raw(1))
                                ->from('crm.fm_archivo_usuario')
                                ->whereColumn('archivo_id', 'crm.fm_archivo.id')
                                ->where('user_id', $userId)
                                ->where('puede_ver', true);
                        });
                    });
                }

                $archivos = $queryA->with('tags')->orderBy('nombre')->limit($limit)->get();
            }

            // Inyectar permisos efectivos del usuario sobre cada item
            // (mismo patrón que contents() — frontend usa los flags para
            // mostrar/ocultar acciones).
            $permisosLote = FmPermisosHelper::calcularPermisosEnLote($carpetas, $archivos, $userId);
            foreach ($carpetas as $c) {
                $c->mis_permisos = $permisosLote['carpetas'][$c->id] ?? [];
            }
            foreach ($archivos as $a) {
                $a->mis_permisos = $permisosLote['archivos'][$a->id] ?? [];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'OK', [
                'carpetas' => $carpetas,
                'archivos' => $archivos,
                'total'    => $carpetas->count() + $archivos->count(),
            ]));
        } catch (Exception $e) {
            $log->logError(self::class, 'Error en búsqueda global', $e);
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), null));
        }
    }
}
