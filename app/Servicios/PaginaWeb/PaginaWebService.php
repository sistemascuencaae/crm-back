<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PaginaWebService
{
    // Clave de la caché de lo que ve la tienda
    public const CACHE_SECCIONES_PUBLICAS = 'pagina_web.secciones_publicas';

    // ---------- Secciones ----------

    public function buscarSeccion(string $clave): array
    {
        return $this->consultar('SELECT ecommerce.fn_seccion_buscar(?::text) AS resultado', [$clave]);
    }

    public function modificarSeccion(int $secId, array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_seccion_modificar(?::integer, ?::jsonb) AS resultado',
            [$secId, json_encode($datos)]
        );
    }

    // ---------- Enlaces del menú ----------

    public function crearMenuItem(array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_item_crear(?::jsonb) AS resultado',
            [json_encode($datos)]
        );
    }

    public function modificarMenuItem(int $mitId, array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_item_modificar(?::integer, ?::jsonb) AS resultado',
            [$mitId, json_encode($datos)]
        );
    }

    public function eliminarMenuItem(int $mitId): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_item_eliminar(?::integer) AS resultado',
            [$mitId]
        );
    }

    public function ordenarMenuItems(array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_item_ordenar(?::jsonb) AS resultado',
            [json_encode($datos)]
        );
    }

    // ---------- Columnas (grupos) de enlaces ----------

    public function crearMenuGrupo(array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_grupo_crear(?::jsonb) AS resultado',
            [json_encode($datos)]
        );
    }

    public function modificarMenuGrupo(int $mgrId, array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_grupo_modificar(?::integer, ?::jsonb) AS resultado',
            [$mgrId, json_encode($datos)]
        );
    }

    public function eliminarMenuGrupo(int $mgrId): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_grupo_eliminar(?::integer) AS resultado',
            [$mgrId]
        );
    }

    public function ordenarMenuGrupos(array $datos): array
    {
        return $this->modificar(
            'SELECT ecommerce.fn_menu_grupo_ordenar(?::jsonb) AS resultado',
            [json_encode($datos)]
        );
    }

    // ---------- Tienda (público, con caché) ----------

    public function listarSeccionesPublicas(): array
    {
        return Cache::remember(self::CACHE_SECCIONES_PUBLICAS, now()->addMinutes(10), function () {
            return $this->consultar('SELECT ecommerce.fn_seccion_listar_publicas() AS resultado');
        });
    }

    // ---------- Internos ----------

    // Ejecuta la función y convierte su JSON en un arreglo de PHP
    private function consultar(string $sql, array $parametros = []): array
    {
        $fila = DB::selectOne($sql, $parametros);

        return json_decode($fila->resultado, true)
            ?? ['ok' => false, 'codigo' => 500, 'mensaje' => 'Respuesta inválida de la base de datos.'];
    }

    // Igual que consultar(), pero si el cambio salió bien, la tienda verá lo nuevo de inmediato
    private function modificar(string $sql, array $parametros = []): array
    {
        $resultado = $this->consultar($sql, $parametros);

        if ($resultado['ok'] ?? false) {
            Cache::forget(self::CACHE_SECCIONES_PUBLICAS);
        }

        return $resultado;
    }
}
