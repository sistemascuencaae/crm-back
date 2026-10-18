<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Footer de la tienda (CRM): su configuración (suscripción, descripción, redes y barra inferior),
 * sus columnas y los enlaces de cada columna. Llama a las funciones ecommerce.fn_pie_*.
 * Cada cambio borra la caché de lo que ve la tienda.
 */
class PiePaginaService
{
    /** Configuración y columnas con sus enlaces (también los ocultos), para la pantalla del CRM. */
    public function buscar(): array
    {
        $fila     = DB::selectOne('SELECT * FROM ecommerce.fn_pie_pagina_buscar()');
        $columnas = DB::select('SELECT * FROM ecommerce.fn_pie_columna_listar()');
        $enlaces  = DB::select('SELECT * FROM ecommerce.fn_pie_enlace_listar()');

        // Cada columna lleva sus enlaces
        foreach ($columnas as $columna) {
            $columna->enlaces = [];
            foreach ($enlaces as $enlace) {
                if ($enlace->pco_id === $columna->pco_id) {
                    $columna->enlaces[] = $enlace;
                }
            }
        }

        return [
            'config'   => $fila ? json_decode($fila->pie_config) : new \stdClass(),
            'columnas' => $columnas,
        ];
    }

    /** $datos: {config, usu_id} */
    public function modificar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pie_pagina_modificar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    // ---------- Columnas ----------

    /** $datos: {pco_titulo, pco_activo, usu_id}. Devuelve su id. */
    public function crearColumna(array $datos): int
    {
        $pcoId = DB::selectOne('SELECT ecommerce.fn_pie_columna_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        EncabezadoPiePublicoService::limpiarCache();

        return $pcoId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificarColumna(int $pcoId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pie_columna_modificar(?, ?::jsonb)', [$pcoId, $this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** También elimina los enlaces de la columna. */
    public function eliminarColumna(int $pcoId): void
    {
        DB::statement('SELECT ecommerce.fn_pie_columna_eliminar(?)', [$pcoId]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** $datos: {ids: [pco_id…] (todas, en el nuevo orden), usu_id} */
    public function ordenarColumnas(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pie_columna_ordenar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    // ---------- Enlaces de una columna ----------

    /** $datos: {pco_id, pen_texto, pen_enlace, pen_icono, pen_activo, pen_nueva_pestana, usu_id}. Devuelve su id. */
    public function crearEnlace(array $datos): int
    {
        $penId = DB::selectOne('SELECT ecommerce.fn_pie_enlace_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        EncabezadoPiePublicoService::limpiarCache();

        return $penId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificarEnlace(int $penId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pie_enlace_modificar(?, ?::jsonb)', [$penId, $this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    public function eliminarEnlace(int $penId): void
    {
        DB::statement('SELECT ecommerce.fn_pie_enlace_eliminar(?)', [$penId]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** $datos: {pco_id, ids: [pen_id…] (todos los de esa columna, en el nuevo orden), usu_id} */
    public function ordenarEnlaces(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pie_enlace_ordenar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}