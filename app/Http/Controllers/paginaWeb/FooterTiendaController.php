<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Footer de la tienda (público, sin login): el footer que se muestra ahora (ecommerce.fn_footer_tienda) y las imágenes
// de los footers. El mantenimiento está en ecommerce/FooterController (CRM), que borra la caché al guardar.
class FooterTiendaController extends Controller
{
    // Imágenes de los footers (logo, logos de bancos): carpeta privada storage/app/footers; se piden por
    // api/public/footers/imagenes/{nombre}. En la base se guarda la ruta relativa; aquí se completa con el dominio al salir.
    private const CARPETA_IMAGENES = 'footers';
    private const RUTA_IMAGENES = '/api/public/footers/imagenes/';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra. Los 60 s cubren el
    // cambio de footer por fechas sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.footer_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // Solo esto sale a la tienda (sin usuarios, fechas de auditoría ni papelera).
    private const TIENDA_FOOTER = ['foo_fuente', 'foo_fondo', 'foo_color_titulo', 'foo_color_texto', 'foo_color_enlace',
        'foo_color_efecto', 'foo_derechos_visible', 'foo_derechos_fondo', 'foo_derechos_color'];
    private const TIENDA_COLUMNA = ['fco_titulo', 'fco_ancho'];
    private const TIENDA_ELEMENTO = ['fel_tipo', 'fel_texto', 'fel_negrita', 'fel_cursiva', 'fel_mayusculas', 'fel_color',
        'fel_tamano', 'fel_enlace', 'fel_nueva_pestana', 'fel_icono', 'fel_color_icono', 'fel_imagen', 'fel_imagen_alt',
        'fel_ancho', 'fel_iconos_tipo', 'fel_forma', 'fel_color_fondo_icono', 'fel_color_contorno', 'fel_alto'];
    private const TIENDA_ICONO = ['fic_red', 'fic_imagen', 'fic_imagen_alt', 'fic_enlace', 'fic_nueva_pestana'];

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // El footer que la tienda muestra ahora (fn_footer_tienda), listo para dibujar: solo columnas visibles, elementos
    // activos (los enlaces, además, vigentes) con sus sub-enlaces adentro, íconos activos y {año} ya reemplazado.
    // data = null si todavía no hay footers (la tienda usa su diseño de respaldo).
    public function tienda()
    {
        try {
            $footer = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_footer_tienda() AS footer');

                return $fila->footer ? $this->footerParaTienda(json_decode($fila->footer, true)) : null;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Footer de la tienda', $footer));
        } catch (\Throwable $th) {
            Log::error('No se pudo leer el footer de la tienda', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer el footer.', null));
        }
    }

    // Entrega una imagen de la carpeta de footers y nada más (el nombre se revisa antes de abrir el archivo).
    public function verImagen(string $nombre)
    {
        $ruta = self::CARPETA_IMAGENES . '/' . $nombre;

        if (!preg_match(self::NOMBRE_IMAGEN, $nombre) || !Storage::disk('local')->exists($ruta)) {
            abort(404);
        }

        return response()->file(Storage::disk('local')->path($ruta), [
            'Content-Type' => self::TIPOS_IMAGEN[pathinfo($nombre, PATHINFO_EXTENSION)],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    // ================= Ayudantes =================

    private function footerParaTienda(array $f): array
    {
        $f = $this->conImagenesCompletas($f);

        // fel_enlace = la dirección del destino elegido (fel_destino), todas con una sola consulta. Un enlace cuyo destino
        // hoy no lleva a ningún lado (inactivo o en la papelera) no sale; una imagen sale sin enlace.
        $destinos = [];
        foreach ($f['columnas'] ?? [] as $i => $c) {
            foreach ($c['elementos'] ?? [] as $j => $e) {
                $destinos["c$i-$j"] = $e['fel_destino'] ?? null;
            }
        }
        foreach ($f['derechos'] ?? [] as $j => $e) {
            $destinos["d-$j"] = $e['fel_destino'] ?? null;
        }
        $rutas = array_combine(array_keys($destinos), $this->rutas(array_values($destinos)) ?: []) ?: [];
        $poner = function (array $e, ?string $ruta): array {
            $e['fel_enlace'] = $ruta;
            $e['sin_salida'] = !empty($e['fel_destino']) && $ruta === null;
            return $e;
        };
        foreach ($f['columnas'] ?? [] as $i => $c) {
            foreach ($c['elementos'] ?? [] as $j => $e) {
                $f['columnas'][$i]['elementos'][$j] = $poner($e, $rutas["c$i-$j"] ?? null);
            }
        }
        foreach ($f['derechos'] ?? [] as $j => $e) {
            $f['derechos'][$j] = $poner($e, $rutas["d-$j"] ?? null);
        }

        $ahora = now();
        $solo = fn(array $fila, array $campos) => array_intersect_key($fila, array_flip($campos));

        $visible = fn(array $e) => !empty($e['fel_activo'])
            && ($e['fel_tipo'] !== 'enlace' || empty($e['sin_salida']))
            && ($e['fel_tipo'] !== 'enlace' || empty($e['fel_vigente_desde'])
                || ($ahora->gte(\Carbon\Carbon::parse($e['fel_vigente_desde'])) && $ahora->lt(\Carbon\Carbon::parse($e['fel_vigente_hasta']))));

        // Arma el árbol de un lugar (una columna o la franja de derechos). Un enlace sin destino sale como texto (con su
        // ícono, como el horario) o como título de sus sub-enlaces; una franja de íconos sin íconos activos no sale.
        $arbol = function (array $elementos, ?int $padre) use (&$arbol, $visible, $solo): array {
            $hijos = array_values(array_filter($elementos, fn($e) => ($e['fel_padre_id'] ?? null) === $padre && $visible($e)));
            usort($hijos, fn($a, $b) => [$a['fel_orden'], $a['fel_id']] <=> [$b['fel_orden'], $b['fel_id']]);

            $salida = [];
            foreach ($hijos as $e) {
                $item = $solo($e, self::TIENDA_ELEMENTO);

                if ($e['fel_tipo'] === 'enlace') {
                    $item['subenlaces'] = $arbol($elementos, $e['fel_id']);
                }

                if ($e['fel_tipo'] === 'iconos') {
                    $item['iconos'] = array_values(array_map(fn($i) => $solo($i, self::TIENDA_ICONO),
                        array_filter($e['iconos'] ?? [], fn($i) => !empty($i['fic_activo']))));
                    if (!$item['iconos']) {
                        continue;
                    }
                }

                $salida[] = $item;
            }

            return $salida;
        };

        $columnas = [];
        foreach ($f['columnas'] ?? [] as $c) {
            if (empty($c['fco_visible'])) {
                continue;
            }
            $columnas[] = $solo($c, self::TIENDA_COLUMNA) + ['elementos' => $arbol($c['elementos'] ?? [], null)];
        }

        return $solo($f, self::TIENDA_FOOTER) + [
            'foo_derechos_texto' => str_replace('{año}', (string) $ahora->year, (string) ($f['foo_derechos_texto'] ?? '')),
            'columnas' => $columnas,
            'derechos' => $arbol($f['derechos'] ?? [], null),
        ];
    }

    // Las imágenes se guardan con su ruta relativa: al salir llevan el dominio del back.
    private function conImagenesCompletas(array $f): array
    {
        $completa = fn($ruta) => is_string($ruta) && str_starts_with($ruta, self::RUTA_IMAGENES) ? url($ruta) : $ruta;
        $elementos = function (array $lista) use ($completa): array {
            foreach ($lista as $i => $e) {
                $lista[$i]['fel_imagen'] = $completa($e['fel_imagen'] ?? null);
                foreach ($e['iconos'] ?? [] as $j => $icono) {
                    $lista[$i]['iconos'][$j]['fic_imagen'] = $completa($icono['fic_imagen'] ?? null);
                }
            }

            return $lista;
        };

        foreach ($f['columnas'] ?? [] as $i => $c) {
            $f['columnas'][$i]['elementos'] = $elementos($c['elementos'] ?? []);
        }
        $f['derechos'] = $elementos($f['derechos'] ?? []);

        return $f;
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
