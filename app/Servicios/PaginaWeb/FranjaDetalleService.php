<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Franjas del detalle del producto (envío gratis, pagos con tarjeta…): hasta 5, cada una de tipo 'cinta' o 'fija',
 * con texto, texto resaltado, ícono, nota, logos de tarjetas (sí / no) y vigencia. Llama a las funciones
 * ecommerce.fn_franja_detalle_*. Las de la tienda van en caché que respeta la vigencia (CachePaginaWeb).
 */
class FranjaDetalleService
{
    private const CACHE_PUBLICAS = 'pagina_web.franjas_detalle.publicas';

    // Galería cuyos logos salen en las franjas con logos (la misma "Tarjetas de crédito" del footer)
    public const GALERIA_LOGOS = 'pagos_tarjetas';

    /** Tras un cambio de las franjas o de los logos de su galería, la tienda lo ve al momento. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_PUBLICAS);
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /**
     * [{fde_id, fde_tipo, fde_texto, fde_texto_resaltado, fde_icono, fde_logos, fde_nota, fde_activo,
     *   fde_desde, fde_hasta, vigencia, fde_orden}] en su orden
     */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_franja_detalle_listar()');
    }

    /** $datos: {fde_tipo, fde_texto, fde_texto_resaltado, fde_icono, fde_logos, fde_nota, fde_activo, fde_desde, fde_hasta, usu_id}. Devuelve su id. */
    public function crear(array $datos): int
    {
        $fdeId = DB::selectOne('SELECT ecommerce.fn_franja_detalle_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        self::limpiarCache();

        return $fdeId;
    }

    /** $datos: solo lo que cambia + usu_id */
    public function modificar(int $fdeId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_franja_detalle_modificar(?, ?::jsonb)', [$fdeId, $this->json($datos)]);
        self::limpiarCache();
    }

    /** $datos: {ids: [fde_id…] (todas, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_franja_detalle_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    /** La manda a la papelera */
    public function eliminar(int $fdeId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_franja_detalle_eliminar(?, ?)', [$fdeId, $usuId]);
        self::limpiarCache();
    }

    /** La saca de la papelera, al final */
    public function restaurar(int $fdeId, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_franja_detalle_restaurar(?, ?)', [$fdeId, $usuId]);
        self::limpiarCache();
    }

    /** [{fde_id, fde_tipo, fde_texto, fde_texto_resaltado, fde_eliminado_en, fde_eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_franja_detalle_papelera()'), 'fde_eliminado_usu_id');
    }

    /** Borra para siempre las franjas de la papelera. Devuelve cuántas borró. */
    public function vaciar(int $usuId): int
    {
        $cantidad = (int) DB::selectOne('SELECT ecommerce.fn_franja_detalle_vaciar(?) AS cantidad', [$usuId])->cantidad;
        self::limpiarCache();

        return $cantidad;
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Las franjas visibles y vigentes, en su orden:
     *   [{tipo, texto, texto_resaltado, icono, nota, logos: [imágenes de pagos_tarjetas] | null}]
     */
    public function publicas(): array
    {
        return Cache::remember(self::CACHE_PUBLICAS, fn () => CachePaginaWeb::vencimiento(), function () {
            $logos = null;

            return array_map(function ($f) use (&$logos) {
                if ($f->logos && $logos === null) {
                    $logos = DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [self::GALERIA_LOGOS]);
                }

                return [
                    'tipo'            => $f->tipo,
                    'texto'           => $f->texto,
                    'texto_resaltado' => $f->texto_resaltado,
                    'icono'           => $f->icono,
                    'nota'            => $f->nota,
                    'logos'           => $f->logos ? $logos : null,
                ];
            }, DB::select('SELECT * FROM ecommerce.fn_franja_detalle_listar_publicas()'));
        });
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}
