<?php

namespace App\Http\Resources\fileManager;

use App\Models\fileManager\FmArchivo;
use App\Models\fileManager\FmArchivoUsuario;
use App\Models\fileManager\FmCarpeta;
use App\Models\fileManager\FmCarpetaUsuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Helper de permisos del File Manager.
 *
 * Modelo HÍBRIDO computado (sin propagación):
 * - Permiso a carpeta = ver toda su jerarquía (descendientes heredan).
 * - Permiso a archivo individual = ver ese archivo + permiso de paso a
 *   las carpetas ancestro (sólo para navegar hasta él).
 * - Admin global (usu_tipo = 3, SUPER USUARIO) bypassa todo.
 *
 * Cada chequeo consulta las tablas fm_carpeta_usuario / fm_archivo_usuario
 * en tiempo real; no hay denormalización.
 */
class FmPermisosHelper
{
    // Mapeo acción → columna en fm_carpeta_usuario
    public const ACCIONES_CARPETA = [
        'ver'                  => 'puede_ver',
        'descargar'            => 'puede_descargar',
        'subir_archivos'       => 'puede_subir_archivos',
        'crear_subcarpetas'    => 'puede_crear_subcarpetas',
        'renombrar'            => 'puede_renombrar',
        'eliminar'             => 'puede_eliminar',
        'mover'                => 'puede_mover',
        'gestionar_permisos'   => 'puede_gestionar_permisos',
    ];

    // Mapeo acción → columna en fm_archivo_usuario
    public const ACCIONES_ARCHIVO = [
        'ver'                  => 'puede_ver',
        'descargar'            => 'puede_descargar',
        'renombrar'            => 'puede_renombrar',
        'editar_contenido'     => 'puede_editar_contenido',
        'eliminar'             => 'puede_eliminar',
        'mover'                => 'puede_mover',
        'gestionar_permisos'   => 'puede_gestionar_permisos',
    ];

    /**
     * Cuando un permiso de archivo se hereda desde la carpeta padre, se
     * consulta esta columna equivalente en fm_carpeta_usuario.
     */
    private const ARCHIVO_A_CARPETA = [
        'ver'                  => 'puede_ver',
        'descargar'            => 'puede_descargar',
        'renombrar'            => 'puede_renombrar',
        'editar_contenido'     => 'puede_renombrar', // editar contenido cae bajo renombrar de la carpeta
        'eliminar'             => 'puede_eliminar',
        'mover'                => 'puede_mover',
        'gestionar_permisos'   => 'puede_gestionar_permisos',
    ];

    // ------------------------------------------------------------------------
    // API pública
    // ------------------------------------------------------------------------

    public static function esAdmin(?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;
        $user = DB::table('crm.users')->where('id', $userId)->first();
        // Solo SUPER USUARIO (usu_tipo = 3) bypassa los permisos del File Manager.
        return $user && (int) ($user->usu_tipo ?? 0) === 3;
    }

    public static function puedeRealizarAccion(
        string $accion,
        string $entidadTipo,
        int $entidadId,
        ?int $userId = null
    ): bool {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;
        if (self::esAdmin($userId)) return true;

        return $entidadTipo === 'carpeta'
            ? self::tienePermisoCarpeta($entidadId, $accion, $userId)
            : self::tienePermisoArchivo($entidadId, $accion, $userId);
    }

    /**
     * Builder de archivos visibles en una carpeta dada para un usuario.
     */
    public static function archivosVisiblesEnCarpeta(int $carpetaId, ?int $userId = null): Builder
    {
        $userId = $userId ?? Auth::id();

        $query = FmArchivo::where('carpeta_id', $carpetaId)
            ->where('es_version_actual', true);

        if (self::esAdmin($userId)) return $query;

        $alcanzables = self::carpetasAlcanzables($userId);

        if ($alcanzables->contains($carpetaId)) {
            // La carpeta está dentro del alcance del usuario → todos los archivos visibles
            return $query;
        }

        // Carpeta NO alcanzable: sólo archivos con permiso directo
        return $query->whereExists(function ($sub) use ($userId) {
            $sub->select(DB::raw(1))
                ->from('crm.fm_archivo_usuario')
                ->whereColumn('archivo_id', 'crm.fm_archivo.id')
                ->where('user_id', $userId)
                ->where('puede_ver', true);
        });
    }

    /**
     * Builder de subcarpetas visibles dentro de una carpeta padre, para un usuario.
     */
    public static function subcarpetasVisibles(int $parentId, ?int $userId = null): Builder
    {
        $userId = $userId ?? Auth::id();

        $query = FmCarpeta::where('parent_id', $parentId);

        if (self::esAdmin($userId)) return $query;

        $alcanzables = self::carpetasAlcanzables($userId);

        if ($alcanzables->contains($parentId)) {
            return $query;
        }

        // Parent NO alcanzable: subcarpetas visibles si:
        //   1) están entre las alcanzables (permiso directo), o
        //   2) son carpetas de paso (ancestros de archivos con permiso o de carpetas con permiso)
        $depaso = self::carpetasDePaso($userId);
        $idsVisibles = $alcanzables->merge($depaso)->unique()->values();

        if ($idsVisibles->isEmpty()) {
            // Sin permisos = sin visibilidad
            return $query->whereRaw('1=0');
        }

        return $query->whereIn('id', $idsVisibles->all());
    }

    /**
     * Devuelve la colección de IDs de carpetas visibles globalmente por un usuario.
     * Útil para árbol lateral (sidebar) y modal de mover.
     */
    public static function carpetasVisibles(?int $userId = null): Collection
    {
        $userId = $userId ?? Auth::id();
        if (self::esAdmin($userId)) {
            return FmCarpeta::pluck('id');
        }
        $alcanzables = self::carpetasAlcanzables($userId);
        $depaso = self::carpetasDePaso($userId);
        return $alcanzables->merge($depaso)->unique()->values();
    }

    // ------------------------------------------------------------------------
    // Cálculo en lote (para inyectar permisos en respuestas paginadas)
    // ------------------------------------------------------------------------

    /**
     * Computa los booleans efectivos de cada acción para los items dados,
     * con sólo 2 queries iniciales (cargar todos los registros del usuario
     * en fm_carpeta_usuario y fm_archivo_usuario), luego cálculo en memoria.
     *
     * @param Collection $subcarpetas  Collection<FmCarpeta>
     * @param Collection $archivos     Collection<FmArchivo>
     * @return array{carpetas: array<int, array<string,bool>>, archivos: array<int, array<string,bool>>}
     */
    public static function calcularPermisosEnLote(
        Collection $subcarpetas,
        Collection $archivos,
        ?int $userId = null
    ): array {
        $userId = $userId ?? Auth::id();
        $esAdmin = self::esAdmin($userId);

        $resultadoCarpetas = [];
        $resultadoArchivos = [];

        // Admin global: todos los flags en true.
        if ($esAdmin) {
            foreach ($subcarpetas as $c) {
                $resultadoCarpetas[$c->id] = array_fill_keys(array_values(self::ACCIONES_CARPETA), true);
            }
            foreach ($archivos as $a) {
                $resultadoArchivos[$a->id] = array_fill_keys(array_values(self::ACCIONES_ARCHIVO), true);
            }
            return ['carpetas' => $resultadoCarpetas, 'archivos' => $resultadoArchivos];
        }

        // Carga TODO el cuadro de permisos del usuario (2 queries).
        $permCarpeta = FmCarpetaUsuario::where('user_id', $userId)
            ->get()
            ->keyBy('carpeta_id'); // [carpeta_id => row]

        $permArchivo = FmArchivoUsuario::where('user_id', $userId)
            ->get()
            ->keyBy('archivo_id');

        // --- Carpetas: directo OR herencia desde ancestros ---
        foreach ($subcarpetas as $c) {
            $ancestros = self::parsearIdsDesdePath($c->materialized_path);
            $cadena = array_merge([$c->id], $ancestros);

            $flags = [];
            foreach (self::ACCIONES_CARPETA as $accion => $columna) {
                $flags[$columna] = self::orEnCadena($permCarpeta, $cadena, $columna);
            }
            $resultadoCarpetas[$c->id] = $flags;
        }

        // --- Archivos: directo OR herencia desde carpeta padre + ancestros ---
        foreach ($archivos as $a) {
            $carpetaPadre = $a->carpeta_id;
            // Obtener path de la carpeta padre (puede no estar en $subcarpetas: ej. archivo a 2 niveles)
            $carpetaObj = FmCarpeta::find($carpetaPadre);
            $cadenaCarpetas = [];
            if ($carpetaObj) {
                $cadenaCarpetas = self::parsearIdsDesdePath($carpetaObj->materialized_path);
                $cadenaCarpetas[] = $carpetaObj->id;
            }

            $directo = $permArchivo->get($a->id);

            $flags = [];
            foreach (self::ACCIONES_ARCHIVO as $accion => $columna) {
                $columnaCarpeta = self::ARCHIVO_A_CARPETA[$accion] ?? null;
                $efectivo =
                    ($directo && (bool) $directo->{$columna})
                    || ($columnaCarpeta && self::orEnCadena($permCarpeta, $cadenaCarpetas, $columnaCarpeta));
                $flags[$columna] = $efectivo;
            }
            $resultadoArchivos[$a->id] = $flags;
        }

        return ['carpetas' => $resultadoCarpetas, 'archivos' => $resultadoArchivos];
    }

    /**
     * Computa los 8 booleans efectivos de una sola carpeta (directo + herencia).
     * Útil para anotar la carpeta_actual del response de contents().
     */
    public static function calcularPermisosCarpeta(int $carpetaId, ?int $userId = null): array
    {
        $userId = $userId ?? Auth::id();
        if (self::esAdmin($userId)) {
            return array_fill_keys(array_values(self::ACCIONES_CARPETA), true);
        }

        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) {
            return array_fill_keys(array_values(self::ACCIONES_CARPETA), false);
        }

        $cadena = array_merge([$carpetaId], self::parsearIdsDesdePath($carpeta->materialized_path));

        $perm = FmCarpetaUsuario::where('user_id', $userId)
            ->whereIn('carpeta_id', $cadena)
            ->get()
            ->keyBy('carpeta_id');

        $flags = [];
        foreach (self::ACCIONES_CARPETA as $accion => $columna) {
            $flags[$columna] = self::orEnCadena($perm, $cadena, $columna);
        }
        return $flags;
    }

    /**
     * True si alguna fila de $cuadro indexado por id, dentro de $cadena de
     * ids, tiene la $columna en true.
     */
    private static function orEnCadena($cuadro, array $cadena, string $columna): bool
    {
        foreach ($cadena as $cid) {
            $row = $cuadro->get($cid);
            if ($row && (bool) $row->{$columna}) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------------------
    // Internos: cálculo de permisos puntuales
    // ------------------------------------------------------------------------

    private static function tienePermisoCarpeta(int $carpetaId, string $accion, int $userId): bool
    {
        $columna = self::ACCIONES_CARPETA[$accion] ?? null;
        if (!$columna) return false;

        // Permiso directo en la carpeta
        $directo = FmCarpetaUsuario::where('carpeta_id', $carpetaId)
            ->where('user_id', $userId)
            ->where($columna, true)
            ->exists();
        if ($directo) return true;

        // Permiso heredado de cualquier ancestro
        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) return false;

        $ancestros = self::parsearIdsDesdePath($carpeta->materialized_path);
        if (empty($ancestros)) return false;

        return FmCarpetaUsuario::whereIn('carpeta_id', $ancestros)
            ->where('user_id', $userId)
            ->where($columna, true)
            ->exists();
    }

    private static function tienePermisoArchivo(int $archivoId, string $accion, int $userId): bool
    {
        $columna = self::ACCIONES_ARCHIVO[$accion] ?? null;
        if (!$columna) return false;

        // Permiso directo al archivo
        $directo = FmArchivoUsuario::where('archivo_id', $archivoId)
            ->where('user_id', $userId)
            ->where($columna, true)
            ->exists();
        if ($directo) return true;

        // Permiso heredado de la carpeta del archivo o de cualquier ancestro
        $archivo = FmArchivo::find($archivoId);
        if (!$archivo) return false;

        $columnaCarpeta = self::ARCHIVO_A_CARPETA[$accion] ?? null;
        if (!$columnaCarpeta) return false;

        $carpetaPadre = FmCarpeta::find($archivo->carpeta_id);
        if (!$carpetaPadre) return false;

        $idsAncestros = self::parsearIdsDesdePath($carpetaPadre->materialized_path);
        $idsAncestros[] = $carpetaPadre->id;

        return FmCarpetaUsuario::whereIn('carpeta_id', $idsAncestros)
            ->where('user_id', $userId)
            ->where($columnaCarpeta, true)
            ->exists();
    }

    // ------------------------------------------------------------------------
    // Internos: cálculo de carpetas alcanzables y de paso
    // ------------------------------------------------------------------------

    /**
     * Carpetas a las que el usuario tiene "vista completa": las que tienen
     * permiso directo (puede_ver=true), MÁS todos sus descendientes.
     */
    private static function carpetasAlcanzables(int $userId): Collection
    {
        $directaIds = FmCarpetaUsuario::where('user_id', $userId)
            ->where('puede_ver', true)
            ->pluck('carpeta_id');

        if ($directaIds->isEmpty()) return collect();

        $directas = FmCarpeta::whereIn('id', $directaIds)->get();

        $alcanzables = collect();
        foreach ($directas as $c) {
            $alcanzables->push($c->id);
            $prefijo = $c->materialized_path . $c->id . '/';
            $descendientes = FmCarpeta::where('materialized_path', 'LIKE', $prefijo . '%')->pluck('id');
            $alcanzables = $alcanzables->merge($descendientes);
        }

        return $alcanzables->unique()->values();
    }

    /**
     * Carpetas de paso: aquellas que NO son alcanzables, pero el usuario
     * debe poder VERLAS (solo verlas, no su contenido completo) para llegar
     * a un archivo o carpeta hijo al que sí tiene permiso directo.
     */
    private static function carpetasDePaso(int $userId): Collection
    {
        // Archivos con permiso directo
        $archivoIds = FmArchivoUsuario::where('user_id', $userId)
            ->where('puede_ver', true)
            ->pluck('archivo_id');

        $carpetasIdsDePaso = collect();

        if ($archivoIds->isNotEmpty()) {
            $carpetasDeArchivos = FmArchivo::whereIn('id', $archivoIds)
                ->pluck('carpeta_id')->unique();
            foreach ($carpetasDeArchivos as $cid) {
                $carpetasIdsDePaso = $carpetasIdsDePaso->merge(self::ancestrosIncluyente((int) $cid));
            }
        }

        // Carpetas con permiso directo: incluir también sus ancestros como "de paso"
        // (porque el usuario necesita poder llegar hasta esa carpeta)
        $carpetaIdsDirectas = FmCarpetaUsuario::where('user_id', $userId)
            ->where('puede_ver', true)
            ->pluck('carpeta_id');

        foreach ($carpetaIdsDirectas as $cid) {
            $carpeta = FmCarpeta::find($cid);
            if (!$carpeta) continue;
            $carpetasIdsDePaso = $carpetasIdsDePaso->merge(
                self::parsearIdsDesdePath($carpeta->materialized_path)
            );
        }

        return $carpetasIdsDePaso->unique()->values();
    }

    /**
     * Devuelve los IDs de [ancestros + la carpeta misma].
     */
    private static function ancestrosIncluyente(int $carpetaId): array
    {
        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) return [];
        $ids = self::parsearIdsDesdePath($carpeta->materialized_path);
        $ids[] = $carpeta->id;
        return $ids;
    }

    /**
     * Parsea "/1/47/103/" → [1, 47, 103].
     */
    private static function parsearIdsDesdePath(string $path): array
    {
        $limpio = trim($path, '/');
        if ($limpio === '') return [];
        return array_map('intval', explode('/', $limpio));
    }
}
