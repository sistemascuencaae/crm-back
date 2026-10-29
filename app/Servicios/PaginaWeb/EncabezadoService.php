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
    /**
     * Configuración y enlaces (también los ocultos), para la pantalla del CRM.
     * Cada enlace principal trae sus sub-enlaces en "hijos":
     *   {config, enlaces: [{een_id, een_texto, …, hijos: [{een_id, een_padre_id, …}]}]}
     */
    public function buscar(): array
    {
        $fila = DB::selectOne('SELECT * FROM ecommerce.fn_encabezado_buscar()');

        return [
            'config'  => $fila ? json_decode($fila->enc_config) : new \stdClass(),
            'enlaces' => $this->agruparHijos(DB::select('SELECT * FROM ecommerce.fn_encabezado_enlace_listar()')),
        ];
    }

    /**
     * Pone cada sub-enlace dentro de su principal. La base devuelve primero los principales
     * y después los sub-enlaces, así el principal ya existe cuando llega su hijo.
     */
    private function agruparHijos(array $filas): array
    {
        $principales = [];
        foreach ($filas as $fila) {
            if ($fila->een_padre_id === null) {
                $fila->hijos = [];
                $principales[$fila->een_id] = $fila;
            } elseif (isset($principales[$fila->een_padre_id])) {
                $principales[$fila->een_padre_id]->hijos[] = $fila;
            }
        }

        return array_values($principales);
    }

    /** $datos: {config, usu_id} */
    public function modificar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_modificar(?::jsonb)', [$this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    // ---------- Enlaces del menú ----------

    /**
     * $datos: {een_padre_id, een_texto, een_enlace, een_icono, een_activo, een_nueva_pestana, usu_id}.
     * Sin een_padre_id es un enlace principal; con él, un sub-enlace de ese principal. Devuelve su id.
     */
    public function crearEnlace(array $datos): int
    {
        $eenId = DB::selectOne('SELECT ecommerce.fn_encabezado_enlace_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        EncabezadoPiePublicoService::limpiarCache();

        return $eenId;
    }

    /** $datos: solo lo que cambia + usu_id (un sub-enlace no cambia de padre) */
    public function modificarEnlace(int $eenId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_modificar(?, ?::jsonb)', [$eenId, $this->json($datos)]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** Lo manda a la papelera; si es un principal, se lleva sus sub-enlaces. */
    public function eliminarEnlace(int $eenId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_eliminar(?, ?)', [$eenId, $usuId]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** Lo saca de la papelera, al final de su grupo (un principal vuelve con sus sub-enlaces). */
    public function restaurarEnlace(int $eenId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_encabezado_enlace_restaurar(?, ?)', [$eenId, $usuId]);
        EncabezadoPiePublicoService::limpiarCache();
    }

    /** [{een_id, een_padre_id, een_texto, padre_texto, subenlaces, een_eliminado_en, een_eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_encabezado_enlace_papelera()'), 'een_eliminado_usu_id');
    }

    /** Borra para siempre lo que está en la papelera. Devuelve cuántos enlaces borró (los que se veían en la papelera). */
    public function vaciar(int $usuId): int
    {
        $cantidad = (int) DB::selectOne('SELECT ecommerce.fn_encabezado_enlace_vaciar(?) AS cantidad', [$usuId])->cantidad;
        EncabezadoPiePublicoService::limpiarCache();

        return $cantidad;
    }

    /**
     * Ordena un grupo: los principales (een_padre_id null) o los sub-enlaces de un principal.
     * $datos: {een_padre_id, ids: [een_id…] (todos los del grupo, en el nuevo orden), usu_id}
     */
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