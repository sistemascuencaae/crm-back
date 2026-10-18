<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Header de la tienda (CRM): su configuración (barra superior, crédito y frases del buscador)
 * y los enlaces del menú. Llama a las funciones ecommerce.fn_encabezado_*.
 * Cada cambio borra la caché de lo que ve la tienda.
 */
class EncabezadoService
{
    /** Configuración y enlaces (también los ocultos), para la pantalla del CRM. */
    public function buscar(): array
    {
        $fila = DB::selectOne('SELECT * FROM ecommerce.fn_encabezado_buscar()');

        return [
            'config'  => $fila ? json_decode($fila->enc_config) : new \stdClass(),
            'enlaces' => DB::select('SELECT * FROM ecommerce.fn_encabezado_enlace_listar()'),
        ];
    }

    /** $datos: {config, usu_id} */
    public function modificar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_modificar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    // ---------- Enlaces del menú ----------

    /** $datos: {een_texto, een_enlace, een_icono, een_activo, een_nueva_pestana, usu_id}. Devuelve su id. */
    public function crearEnlace(array $datos): int
    {
        $eenId = DB::selectOne('SELECT ecommerce.fn_encabezado_enlace_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        EncabezadoPiePublicoService::limpiarCache();

        return $eenId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificarEnlace(int $eenId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_modificar(?, ?::jsonb)', [$eenId, $this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    public function eliminarEnlace(int $eenId): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_eliminar(?)', [$eenId]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** $datos: {ids: [een_id…] (todos, en el nuevo orden), usu_id} */
    public function ordenarEnlaces(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_ordenar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}