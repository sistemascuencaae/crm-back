<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Páginas de la tienda (público, sin login): las páginas del CRM (Ecommerce > Páginas) listas para dibujar y las
// imágenes de sus secciones.
//   GET public/paginas/inicio            → la página marcada como Inicio (data = null si no hay: la tienda usa su inicio fijo)
//   GET public/paginas/{ruta}            → una página activa por su dirección (404 si no existe o está inactiva)
//   GET public/secciones/imagenes/{nombre} → una imagen de las secciones
// Cada página va con sus secciones VISIBLES en orden, cada una con su plantilla (campos con sus valores por defecto,
// listas y tarjeta), sus valores y elementos, las tarjetas reales de sus productos (ecommerce.fn_producto_tienda_tarjetas)
// y cada enlace elegido con su dirección de hoy (ecommerce.fn_enlace_ruta; null = el destino está inactivo o ya no
// existe: la tienda no lo enlaza). La forma de cada sección es la que usa la vista previa del CRM (estadoDesdeSeccion).
// Sin caché de la respuesta: lo que se guarda en el CRM sale al momento. El mantenimiento está en ecommerce/
// PaginasController y SeccionesController (CRM).
class PaginasTiendaController extends Controller
{
    // Imágenes de las secciones: carpeta privada storage/app/secciones; nombre aleatorio de 40 letras y números + .webp
    private const CARPETA_IMAGENES = 'secciones';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    public function inicio()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Página de inicio', $this->publica(null)))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página de inicio', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null), 500);
        }
    }

    public function ver(string $ruta)
    {
        try {
            $pagina = $this->publica($ruta);
            if (!$pagina) {
                return response()->json(RespuestaApi::returnResultado('error', 'La página no existe.', null), 404);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página', ['ruta' => $ruta, 'error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null), 500);
        }
    }

    // Una imagen de las secciones (pública: la pide la tienda y el CRM). El nombre nunca cambia de contenido.
    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;

        if (!preg_match(self::NOMBRE_IMAGEN, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * La página activa con esa dirección; sin dirección, la marcada como Inicio. null si no hay.
     *   {pagina: {pag_nombre, pag_ruta, pag_inicio}, url_imagenes, secciones: [...]}
     */
    private function publica(?string $ruta): ?array
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
     * Las tarjetas reales de TODOS los productos de la página con una sola consulta (tarjetasDe:
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
        foreach ($todos ? $this->tarjetasDe($todos, count($todos)) : [] as $tarjeta) {
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

        $rutas = $this->rutas($destinos);
        foreach ($refs as $i => &$ref) {
            $ref['ruta'] = $rutas[$i] ?? null;
        }
        unset($ref);
    }

    /**
     * Las tarjetas de UNOS productos, en el orden pedido y como máximo $cantidad (ecommerce.fn_producto_tienda_tarjetas):
     * las de las SECCIONES de las páginas (los productos de un grupo, los afiches, el 360). Misma forma que las del
     * listado de /shop, más la descripción (el 360 la muestra):
     *   [{pro_id, codigo, nombre, marca, tpr_id, categoria, imagenes: [url…], destacado, oferta, precio, precio_anterior,
     *     stock, descripcion, es_moto}]
     */
    private function tarjetasDe(array $proIds, int $cantidad = 24): array
    {
        $proIds = array_values(array_filter(array_map('intval', $proIds), fn ($id) => $id > 0));
        if (!$proIds) {
            return [];
        }

        return array_map(
            fn ($f) => $this->tarjeta($f),
            DB::select('SELECT * FROM ecommerce.fn_producto_tienda_tarjetas(?::integer[], ?)', [$this->arreglo($proIds), $cantidad])
        );
    }

    /**
     * La tarjeta de producto de todo el sitio, de una fila de las funciones de la tienda (traen precio, stock y es_moto).
     */
    private function tarjeta(object $f): array
    {
        return [
            'pro_id'          => (int) $f->pro_id,
            'codigo'          => $f->pro_codigo,
            'nombre'          => $f->pro_nombre,
            'marca'           => $f->marca,
            'tpr_id'          => (int) $f->tpr_id,
            'categoria'       => $f->categoria,
            'imagenes'        => $this->imagenes($f->imagenes),
            'destacado'       => (bool) $f->destacado,
            // En oferta (producto.pro_oferta): etiqueta Oferta.
            'oferta'          => (bool) ($f->oferta ?? false),
            'precio'          => $f->precio !== null ? (float) $f->precio : null,
            'precio_anterior' => $f->precio_anterior !== null ? (float) $f->precio_anterior : null,
            'stock'           => (float) $f->stock,
            // La descripción del producto (solo la traen las tarjetas de las secciones; el 360 la muestra).
            'descripcion'     => $f->descripcion ?? null,
            // Su factura pide los datos de MOTOS (inventarios.fn_producto_es_moto)
            'es_moto'         => (bool) ($f->es_moto ?? false),
        ];
    }

    /**
     * Las fotos que arma la base (inventarios.fn_producto_imagen_ruta: /api/public/pagina-web/imagenes/productos/…) con
     * la dirección de este servidor, porque la tienda está en otro.
     */
    private function imagenes(?string $json): array
    {
        $rutas = json_decode($json ?? '[]', true);

        return is_array($rutas) ? array_map(fn ($ruta) => url($ruta), $rutas) : [];
    }

    /** Lista de números como arreglo de Postgres: [1, 2, 3] → "{1,2,3}" */
    private function arreglo(array $ids): string
    {
        return '{' . implode(',', array_map('intval', $ids)) . '}';
    }

    // Las direcciones de una lista de destinos de enlace, en el mismo orden, con UNA sola consulta (ecommerce.fn_enlace_ruta).
    // null = no lleva a ningún lado (sin destino, o el destino está inactivo, en la papelera o ya no existe).
    private function rutas(array $destinos): array
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
}
