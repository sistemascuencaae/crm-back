<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/** Lo común de las papeleras del módulo. */
class PapeleraPaginaWeb
{
    /**
     * A cada fila de una papelera le agrega eliminado_por con el nombre de quien la eliminó ("Usuario #<id>" si no se encuentra).
     * Los nombres salen de crm.users (la tabla del login del CRM), en una sola consulta para toda la lista.
     */
    public static function agregarEliminadoPor(array $filas, string $columna): array
    {
        $ids = array_values(array_unique(array_filter(array_map(fn ($f) => (int) $f->$columna, $filas))));

        $nombres = [];
        if ($ids) {
            foreach (DB::select('SELECT id, name, surname FROM crm.users WHERE id = ANY (?::integer[])', ['{' . implode(',', $ids) . '}']) as $u) {
                $nombres[(int) $u->id] = trim(trim((string) $u->name) . ' ' . trim((string) $u->surname));
            }
        }

        foreach ($filas as $fila) {
            $id                  = (int) $fila->$columna;
            $fila->eliminado_por = ($nombres[$id] ?? '') !== '' ? $nombres[$id] : 'Usuario #' . $id;
        }

        return $filas;
    }
}
