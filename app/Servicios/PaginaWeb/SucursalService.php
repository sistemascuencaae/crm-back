<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquestador de la página "Nuestras tiendas".
 * No valida nada: llama a las funciones ecommerce.fn_sucursal_* y devuelve su respuesta.
 * Cada escritura exitosa limpia la caché del listado público.
 */
class SucursalService
{
    public const CACHE_PUBLICAS = 'pagina_web.sucursales.publicas';
    private const CACHE_MINUTOS = 10;

    // -----------------------------------------------------------------
    // Ciudades
    // -----------------------------------------------------------------

    public function crearCiudad(array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_ciudad_crear(?::jsonb) AS resultado',
            [$this->json($datos)]
        );
    }

    public function modificarCiudad(int $sciId, array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_ciudad_modificar(?::integer, ?::jsonb) AS resultado',
            [$sciId, $this->json($datos)]
        );
    }

    public function eliminarCiudad(int $sciId): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_ciudad_eliminar(?::integer) AS resultado',
            [$sciId]
        );
    }

    public function ordenarCiudades(array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_ciudad_ordenar(?::jsonb) AS resultado',
            [$this->json($datos)]
        );
    }

    // -----------------------------------------------------------------
    // Sucursales
    // -----------------------------------------------------------------

    public function crear(array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_crear(?::jsonb) AS resultado',
            [$this->json($datos)]
        );
    }

    public function modificar(int $sucId, array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_modificar(?::integer, ?::jsonb) AS resultado',
            [$sucId, $this->json($datos)]
        );
    }

    public function eliminar(int $sucId): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_eliminar(?::integer) AS resultado',
            [$sucId]
        );
    }

    public function ordenar(array $datos): array
    {
        return $this->escribir(
            'SELECT ecommerce.fn_sucursal_ordenar(?::jsonb) AS resultado',
            [$this->json($datos)]
        );
    }

    // -----------------------------------------------------------------
    // Lectura
    // -----------------------------------------------------------------

    /** CRM: todas las ciudades y sucursales, incluidas las inactivas. */
    public function listarAdmin(): array
    {
        return $this->llamar('SELECT ecommerce.fn_sucursal_listar_admin() AS resultado');
    }

    /** Tienda: solo lo activo. Se guarda en caché únicamente si la función respondió ok. */
    public function listarPublicas(): array
    {
        $guardado = Cache::get(self::CACHE_PUBLICAS);

        if ($guardado !== null) {
            return $guardado;
        }

        $resultado = $this->llamar('SELECT ecommerce.fn_sucursal_listar_publicas() AS resultado');

        if (!empty($resultado['ok'])) {
            Cache::put(self::CACHE_PUBLICAS, $resultado, now()->addMinutes(self::CACHE_MINUTOS));
        }

        return $resultado;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** Llama a una función que modifica datos y, si salió bien, limpia la caché pública. */
    private function escribir(string $sql, array $parametros): array
    {
        $resultado = $this->llamar($sql, $parametros);

        if (!empty($resultado['ok'])) {
            Cache::forget(self::CACHE_PUBLICAS);
        }

        return $resultado;
    }

    private function llamar(string $sql, array $parametros = []): array
    {
        $fila = DB::selectOne($sql, $parametros);

        return json_decode($fila->resultado, true);
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}