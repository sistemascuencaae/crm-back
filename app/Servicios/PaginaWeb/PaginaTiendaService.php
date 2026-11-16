<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\DB;

/**
 * Una página del CRM (Ecommerce > Páginas) lista para que la tienda la dibuje: sus secciones VISIBLES en orden, cada una
 * con su plantilla (campos con sus valores por defecto, listas y tarjeta), sus valores y elementos, las tarjetas reales
 * de sus productos (la misma regla de la tienda: ProductoTiendaService::tarjetasDe) y cada enlace elegido con su
 * dirección de hoy (EnlacesService; null = el destino está inactivo o ya no existe: la tienda no lo enlaza).
 *
 * La forma de cada sección es la que usa la vista previa del CRM (estadoDesdeSeccion): así la tienda dibuja lo mismo.
 */
class PaginaTiendaService
{
    public function __construct(
        private ProductoTiendaService $productos,
        private EnlacesService $enlaces,
    ) {
    }

    /**
     * La página activa con esa dirección; sin dirección, la marcada como Inicio. null si no hay.
     *   {pagina: {pag_nombre, pag_ruta, pag_inicio}, url_imagenes, secciones: [...]}
     */
    public function publica(?string $ruta): ?array
    {
        $pagina = $ruta === null
            ? DB::selectOne('SELECT pag_id, pag_nombre, pag_ruta, pag_inicio FROM ecommerce.paginas
                              WHERE pag_inicio AND pag_activo AND pag_eliminado_en IS NULL LIMIT 1')
            : DB::selectOne('SELECT pag_id, pag_nombre, pag_ruta, pag_inicio FROM ecommerce.paginas
                              WHERE pag_ruta = ? AND pag_activo AND pag_eliminado_en IS NULL', [strtolower($ruta)]);
        if (!$pagina) {
            return null;
        }

        $filas = DB::select('SELECT sec_id, pla_id FROM ecommerce.secciones
                              WHERE pag_id = ? AND sec_visible AND sec_eliminado_en IS NULL
                              ORDER BY sec_orden, sec_id', [$pagina->pag_id]);

        $plantillas = [];
        $secciones = [];
        foreach ($filas as $f) {
            $seccion = json_decode(DB::selectOne('SELECT ecommerce.fn_seccion_buscar(?) AS s', [$f->sec_id])->s, true);
            $plantillas[$f->pla_id] ??= json_decode(DB::selectOne('SELECT ecommerce.fn_plantilla_buscar(?) AS p', [$f->pla_id])->p, true);
            $plantilla = $plantillas[$f->pla_id];

            $secciones[] = [
                'sec_id' => (int) $seccion['sec_id'],
                'sec_nombre' => $seccion['sec_nombre'],
                'pla_codigo' => $seccion['pla_codigo'],
                'sec_valores' => $seccion['sec_valores'] ?: (object) [],
                'sec_valores_tarjeta' => $seccion['sec_valores_tarjeta'] ?: (object) [],
                'elementos' => array_map(fn($e) => [
                    'pco_clave' => $e['pco_clave'],
                    'sel_imagen' => $e['sel_imagen'],
                    'sel_imagen_movil' => $e['sel_imagen_movil'],
                    'gpr_id' => $e['gpr_id'],
                    'pro_id' => $e['pro_id'],
                    'sel_valores' => $e['sel_valores'] ?: (object) [],
                ], $seccion['elementos'] ?? []),
                'plantilla' => $plantilla,
                // Por ahora solo qué productos van en cada lista; las tarjetas se piden juntas abajo.
                'tarjetas' => $this->clavesTarjetas($plantilla, $seccion['elementos'] ?? []),
            ];
        }

        $this->ponerTarjetas($secciones);
        $this->ponerRutas($secciones);

        return [
            'pagina' => ['pag_nombre' => $pagina->pag_nombre, 'pag_ruta' => $pagina->pag_ruta, 'pag_inicio' => (bool) $pagina->pag_inicio],
            'url_imagenes' => url('api/public/secciones/imagenes') . '/',
            'secciones' => $secciones,
        ];
    }

    /**
     * Qué productos lleva cada lista de productos de la sección, con las mismas claves que usa la vista previa del CRM:
     * 'g:<gpr_id>' (los de un grupo, en su orden) y 'p:<pro_id>,<pro_id>…' (los productos elegidos). [clave => [pro_id…]]
     */
    private function clavesTarjetas(array $plantilla, array $elementos): array
    {
        $claves = [];
        foreach ($plantilla['contenidos'] ?? [] as $c) {
            $lista = array_values(array_filter($elementos, fn($e) => $e['pco_clave'] === $c['pco_clave']));
            if ($c['pco_tipo'] === 'grupo') {
                foreach ($lista as $e) {
                    if (!empty($e['gpr_id']) && !isset($claves['g:' . $e['gpr_id']])) {
                        $claves['g:' . $e['gpr_id']] = array_map('intval', array_column(DB::select(
                            'SELECT pro_id FROM ecommerce.grupo_productos_detalles WHERE gpr_id = ? ORDER BY gpd_orden', [$e['gpr_id']]
                        ), 'pro_id'));
                    }
                }
            } elseif ($c['pco_tipo'] === 'producto') {
                $ids = array_values(array_filter(array_map(fn($e) => (int) ($e['pro_id'] ?? 0), $lista)));
                if ($ids) {
                    $claves['p:' . implode(',', $ids)] = $ids;
                }
            }
        }

        return $claves;
    }

    /**
     * Las tarjetas reales de TODOS los productos de la página con una sola consulta (ProductoTiendaService::tarjetasDe:
     * solo los que se venden en la web, con stock), y a cada lista las suyas en su orden. Cada tarjeta depende solo de
     * su producto: es lo mismo que pedirlas lista por lista.
     */
    private function ponerTarjetas(array &$secciones): void
    {
        $todos = [];
        foreach ($secciones as $s) {
            foreach ($s['tarjetas'] as $ids) {
                array_push($todos, ...$ids);
            }
        }
        $todos = array_values(array_unique($todos));
        $porProducto = [];
        foreach ($todos ? $this->productos->tarjetasDe($todos, count($todos)) : [] as $tarjeta) {
            $porProducto[(int) $tarjeta['pro_id']] = $tarjeta;
        }

        foreach ($secciones as &$s) {
            $listas = [];
            foreach ($s['tarjetas'] as $clave => $ids) {
                $listas[$clave] = array_values(array_filter(array_map(fn($id) => $porProducto[$id] ?? null, $ids)));
            }
            $s['tarjetas'] = $listas ?: (object) [];
        }
        unset($s);
    }

    /**
     * A cada enlace elegido ({tipo, id | valor}) de los valores de las secciones, de sus tarjetas y de sus elementos le
     * agrega 'ruta' (la dirección de hoy, o null), con una sola consulta para toda la página.
     */
    private function ponerRutas(array &$secciones): void
    {
        $refs = [];
        $destinos = [];
        $buscar = function (&$valores) use (&$refs, &$destinos) {
            if (!is_array($valores)) {
                return;
            }
            foreach ($valores as $k => $v) {
                if (is_array($v) && isset($v['tipo']) && is_string($v['tipo']) && (isset($v['id']) || isset($v['valor']) || $v['tipo'] === 'inicio')) {
                    $refs[] = &$valores[$k];
                    $destinos[] = $v;
                }
            }
        };
        foreach ($secciones as &$s) {
            $buscar($s['sec_valores']);
            $buscar($s['sec_valores_tarjeta']);
            foreach ($s['elementos'] as &$e) {
                $buscar($e['sel_valores']);
            }
            unset($e);
        }
        unset($s);

        $rutas = $this->enlaces->rutas($destinos);
        foreach ($refs as $i => &$ref) {
            $ref['ruta'] = $rutas[$i] ?? null;
        }
        unset($ref);
    }
}
