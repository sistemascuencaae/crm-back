<?php

namespace App\Http\Resources\fileManager;

use App\Models\fileManager\FmCarpeta;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones sobre el árbol de carpetas del File Manager.
 *
 * Usa la columna denormalizada `materialized_path` para navegar el árbol
 * en una sola query (sin recursividad SQL). El path NO incluye la carpeta
 * misma: una carpeta con id=47 y parent_id=1 tiene path = "/1/".
 */
class FmArbolHelper
{
    /**
     * Devuelve todas las carpetas descendientes (no incluye la carpeta misma).
     */
    public static function descendientesDe(int $carpetaId): Collection
    {
        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) {
            return new Collection();
        }

        $prefijo = $carpeta->materialized_path . $carpeta->id . '/';

        return FmCarpeta::where('materialized_path', 'LIKE', $prefijo . '%')->get();
    }

    /**
     * Devuelve los ancestros de la carpeta en orden de raíz → carpeta padre directo.
     * No incluye la carpeta misma.
     */
    public static function ancestrosDe(int $carpetaId): Collection
    {
        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) {
            return new Collection();
        }

        $idsAncestros = self::parsearIdsDesdePath($carpeta->materialized_path);
        if (empty($idsAncestros)) {
            return new Collection();
        }

        $porId = FmCarpeta::whereIn('id', $idsAncestros)->get()->keyBy('id');

        // Reordenar siguiendo el orden del path (de raíz hacia carpeta)
        $ordenados = new Collection();
        foreach ($idsAncestros as $id) {
            if ($porId->has($id)) {
                $ordenados->push($porId->get($id));
            }
        }
        return $ordenados;
    }

    /**
     * Construye el breadcrumb [{id, nombre}, ...] desde la raíz hasta la carpeta
     * (la carpeta misma sí se incluye al final).
     */
    public static function construirBreadcrumb(int $carpetaId): array
    {
        $carpeta = FmCarpeta::find($carpetaId);
        if (!$carpeta) {
            return [];
        }

        $breadcrumb = self::ancestrosDe($carpetaId)
            ->map(fn ($c) => ['id' => $c->id, 'nombre' => $c->nombre])
            ->values()
            ->toArray();

        $breadcrumb[] = ['id' => $carpeta->id, 'nombre' => $carpeta->nombre];
        return $breadcrumb;
    }

    /**
     * Verifica que mover `carpetaId` a `nuevoParentId` no cree un ciclo.
     * Retorna true si el movimiento es válido (no crea ciclo).
     */
    public static function validarNoCiclo(int $carpetaId, ?int $nuevoParentId): bool
    {
        // Mover a la raíz absoluta (parent NULL) nunca crea ciclo.
        if ($nuevoParentId === null) {
            return true;
        }

        // No se puede mover una carpeta a sí misma.
        if ($carpetaId === $nuevoParentId) {
            return false;
        }

        $nuevoParent = FmCarpeta::find($nuevoParentId);
        if (!$nuevoParent) {
            return false;
        }

        // El nuevo parent no puede ser un descendiente de la carpeta movida.
        return !self::descendientesDe($carpetaId)->contains('id', $nuevoParentId);
    }

    /**
     * Recalcula `materialized_path` y `nivel` de la carpeta dada y de todos
     * sus descendientes tras un cambio de parent. Actualiza también el
     * `parent_id` de la propia carpeta.
     *
     * Asume que ya se validó con `validarNoCiclo` y debe ejecutarse dentro
     * de una transacción del controller.
     */
    public static function recalcularPathSubarbol(FmCarpeta $carpeta, ?FmCarpeta $nuevoParent): void
    {
        $prefijoViejo = $carpeta->materialized_path . $carpeta->id . '/';

        if ($nuevoParent === null) {
            // Mover a raíz absoluta: path queda como '/' y nivel queda como 0.
            $nuevoPath = '/';
            $nuevoNivel = 0;
        } else {
            $nuevoPath = $nuevoParent->materialized_path . $nuevoParent->id . '/';
            $nuevoNivel = $nuevoParent->nivel + 1;
        }

        $prefijoNuevo = $nuevoPath . $carpeta->id . '/';
        $deltaNivel = $nuevoNivel - $carpeta->nivel;

        // Actualizar descendientes (path + nivel) en una sola query.
        // REPLACE es seguro porque cada id aparece a lo más una vez en el
        // materialized_path, así que sólo reemplaza el prefijo correcto.
        // (El SUBSTRING anterior fallaba con NULL cuando un descendiente
        //  tenía materialized_path exactamente igual al prefijo viejo.)
        DB::statement(
            'UPDATE crm.fm_carpeta
             SET materialized_path = REPLACE(materialized_path, ?, ?),
                 nivel = nivel + ?,
                 updated_at = NOW()
             WHERE materialized_path LIKE ?',
            [$prefijoViejo, $prefijoNuevo, $deltaNivel, $prefijoViejo . '%']
        );

        // Actualizar la carpeta misma.
        $carpeta->update([
            'parent_id' => $nuevoParent?->id,
            'materialized_path' => $nuevoPath,
            'nivel' => $nuevoNivel,
        ]);
    }

    /**
     * Calcula el materialized_path y nivel para una carpeta nueva dada su parent.
     * Útil al crear: el controller pregunta los valores y los pasa a Eloquent.
     */
    public static function calcularPathParaNuevaCarpeta(?FmCarpeta $parent): array
    {
        if ($parent === null) {
            return ['materialized_path' => '/', 'nivel' => 0];
        }
        return [
            'materialized_path' => $parent->materialized_path . $parent->id . '/',
            'nivel' => $parent->nivel + 1,
        ];
    }

    /**
     * Extrae los IDs de ancestros desde un materialized_path.
     * Ej: "/1/47/" → [1, 47]; "/" → [].
     */
    private static function parsearIdsDesdePath(string $path): array
    {
        $limpio = trim($path, '/');
        if ($limpio === '') {
            return [];
        }
        return array_map('intval', explode('/', $limpio));
    }
}
