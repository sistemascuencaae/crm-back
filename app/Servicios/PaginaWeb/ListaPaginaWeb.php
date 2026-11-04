<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Http\Request;

/**
 * Lo común de las listas paginadas del módulo (regla del equipo: toda lista sin tope va paginada). Las funciones de la
 * base reciben, al final, (busqueda, pagina, tamanio) y devuelven sus columnas más total.
 */
class ListaPaginaWeb
{
    /** [busqueda, pagina, tamanio] de la query (?busqueda=&pagina=1&tamanio=20); la base corrige página y tamaño fuera de rango */
    public static function paginacion(Request $request): array
    {
        return [
            trim((string) $request->input('busqueda', '')),
            (int) $request->input('pagina', 1),
            (int) $request->input('tamanio', 20),
        ];
    }

    /** Filas de una función paginada → {registros, total}; el total no se repite en cada registro */
    public static function pagina(array $filas): array
    {
        $total = (int) ($filas[0]->total ?? 0);

        foreach ($filas as $fila) {
            unset($fila->total);
        }

        return ['registros' => $filas, 'total' => $total];
    }
}
