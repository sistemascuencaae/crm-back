<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Enlaces elegidos (un destino { tipo, id | valor } en lugar de una dirección escrita): la misma regla para headers,
 * footers y secciones de páginas. Las reglas viven en las funciones ecommerce.fn_enlace_* (enlaces_destinos.sql).
 */
class EnlacesService
{
    /**
     * Las direcciones de una lista de destinos, en el mismo orden, con UNA sola consulta. null = no lleva a ningún lado
     * (sin destino, o el destino está inactivo, en la papelera o ya no existe): la tienda esconde ese enlace.
     *
     * @param array<int, mixed> $destinos
     * @return array<int, string|null>
     */
    public function rutas(array $destinos): array
    {
        if (!$destinos) {
            return [];
        }
        $filas = DB::select(
            'SELECT n, ecommerce.fn_enlace_ruta(d) AS ruta FROM jsonb_array_elements(?::jsonb) WITH ORDINALITY AS a(d, n) ORDER BY n',
            [json_encode(array_values(array_map(fn($d) => $d ?: null, $destinos)))]
        );

        return array_map(fn($f) => $f->ruta, $filas);
    }

    /**
     * Para el CRM: cada destino con su nombre, su dirección de hoy y si sale en la tienda.
     *
     * @param array<int, mixed> $destinos
     * @return array<int, array{etiqueta: string|null, ruta: string|null, disponible: bool}>
     */
    public function describir(array $destinos): array
    {
        if (!$destinos) {
            return [];
        }
        $filas = DB::select(
            'SELECT n, ecommerce.fn_enlace_etiqueta(d) AS etiqueta, ecommerce.fn_enlace_ruta(d) AS ruta
               FROM jsonb_array_elements(?::jsonb) WITH ORDINALITY AS a(d, n) ORDER BY n',
            [json_encode(array_values(array_map(fn($d) => $d ?: null, $destinos)))]
        );

        return array_map(fn($f) => ['etiqueta' => $f->etiqueta, 'ruta' => $f->ruta, 'disponible' => $f->ruta !== null], $filas);
    }

    /**
     * Lo que se puede elegir en el selector de enlace (salvo productos, categorías y marcas, que el CRM ya pide a su
     * catálogo): páginas, documentos, carpetas de nivel 1, pantallas de la tienda y grupos de productos.
     */
    public function catalogo(): array
    {
        return [
            'paginas' => DB::select(
                'SELECT p.pag_id AS id, p.pag_nombre AS nombre, p.pag_ruta AS ruta, p.pag_activo AS activo, p.pag_inicio AS inicio
                   FROM ecommerce.paginas p WHERE p.pag_eliminado_en IS NULL
                  ORDER BY p.pag_inicio DESC, lower(p.pag_nombre)'),
            'documentos' => DB::select(
                'SELECT d.doc_id AS id, d.doc_titulo AS nombre, d.doc_ruta AS ruta, d.doc_activo AS activo, a.ruta AS carpeta
                   FROM ecommerce.documentos d
                   LEFT JOIN ecommerce.fn_documento_carpetas_arbol() a ON a.dca_id = d.dca_id
                  WHERE d.doc_eliminado_en IS NULL
                  ORDER BY a.camino NULLS LAST, d.doc_orden, d.doc_id'),
            'carpetas' => DB::select(
                'SELECT c.dca_id AS id, c.dca_nombre AS nombre, c.dca_ruta AS ruta
                   FROM ecommerce.documentos_carpetas c
                  WHERE c.dca_padre_id IS NULL AND c.dca_eliminado_en IS NULL
                  ORDER BY c.dca_orden, c.dca_id'),
            'pantallas' => DB::select('SELECT clave, nombre, ruta FROM ecommerce.fn_enlace_pantallas()'),
            'grupos' => DB::select(
                'SELECT g.gpr_id AS id, g.gpr_nombre AS nombre, g.gpr_activo AS activo
                   FROM ecommerce.grupos_productos g WHERE g.gpr_eliminado_en IS NULL ORDER BY lower(g.gpr_nombre)'),
        ];
    }
}
