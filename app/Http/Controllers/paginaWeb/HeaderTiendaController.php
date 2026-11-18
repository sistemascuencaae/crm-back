<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

// Header de la tienda (público, sin login): el header que se muestra ahora (ecommerce.fn_header_tienda) y las imágenes
// de los headers (el logo). El mantenimiento está en ecommerce/HeaderController (CRM), que borra la caché al guardar.
class HeaderTiendaController extends Controller
{
    // Imágenes de los headers (el logo): carpeta privada storage/app/headers; se piden por
    // api/public/headers/imagenes/{nombre}. Nombre aleatorio de 40 letras y números + .webp (nunca cambia de contenido).
    private const CARPETA_IMAGENES = 'headers';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra (limpiarCache). Los 60 s
    // cubren el cambio de header por fechas (empieza o termina una campaña) sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.header_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // Solo esto sale a la tienda (sin usuarios, fechas de auditoría ni papelera). hbl_enlace y hen_enlace son la dirección
    // ya armada a partir del destino elegido (hbl_destino / hen_destino): la tienda no cambia.
    private const TIENDA_HEADER = ['hea_fuente', 'hea_fijo', 'hea_superior_visible', 'hea_superior_fondo', 'hea_superior_borde',
        'hea_media_fondo', 'hea_inferior_visible', 'hea_inferior_fondo', 'hea_inferior_contorno'];
    private const TIENDA_BLOQUE = ['hbl_tipo', 'hbl_franja', 'hbl_orden', 'hbl_imagen', 'hbl_imagen_alt', 'hbl_ancho', 'hbl_enlace',
        'hbl_nueva_pestana', 'hbl_color_fondo', 'hbl_color_contorno', 'hbl_color_efecto', 'hbl_color_texto_efecto', 'hbl_color_foco',
        'hbl_redondeo', 'hbl_icono', 'hbl_color_icono', 'hbl_color_fondo_icono', 'hbl_panel_fondo', 'hbl_panel_contorno',
        'hbl_panel_efecto', 'hbl_contador_fondo', 'hbl_contador_texto', 'hbl_segundos'];
    private const TIENDA_TEXTO = ['htx_clave', 'htx_texto', 'htx_negrita', 'htx_cursiva', 'htx_mayusculas', 'htx_color', 'htx_tamano',
        'htx_resaltado'];
    private const TIENDA_ENLACE = ['hen_texto', 'hen_enlace', 'hen_nueva_pestana', 'hen_icono', 'hen_color_icono', 'hen_negrita',
        'hen_cursiva', 'hen_mayusculas', 'hen_color', 'hen_tamano', 'hen_resaltado'];

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // Pública: el header que la tienda muestra ahora (ecommerce.fn_header_tienda), listo para dibujar. Solo van las
    // partes visibles, los textos activos y los links activos y vigentes en este momento (con sus sublinks adentro).
    // data = null si todavía no hay headers (la tienda usa su diseño de respaldo).
    public function tienda()
    {
        try {
            $header = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_header_tienda() AS header');

                return $fila->header ? $this->headerParaTienda(json_decode($fila->header, true)) : null;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Header de la tienda', $header));
        } catch (\Throwable $th) {
            Log::error('No se pudo leer el header de la tienda', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo leer el header.', null));
        }
    }

    private function headerParaTienda(array $h): array
    {
        // Las direcciones de todos los destinos (partes y links) con una sola consulta.
        $destinos = [];
        foreach ($h['bloques'] ?? [] as $i => $b) {
            $destinos["b$i"] = $b['hbl_destino'] ?? null;
            foreach ($b['enlaces'] ?? [] as $j => $e) {
                $destinos["b$i-e$j"] = $e['hen_destino'] ?? null;
            }
        }
        $rutas = array_combine(array_keys($destinos), $this->rutas(array_values($destinos)) ?: []) ?: [];
        foreach ($h['bloques'] ?? [] as $i => $b) {
            $h['bloques'][$i]['hbl_enlace'] = $rutas["b$i"] ?? null;
            foreach ($b['enlaces'] ?? [] as $j => $e) {
                $h['bloques'][$i]['enlaces'][$j]['hen_enlace'] = $rutas["b$i-e$j"] ?? null;
                // Un destino que hoy no lleva a ningún lado (inactivo o en la papelera): el link no sale.
                $h['bloques'][$i]['enlaces'][$j]['sin_salida'] = !empty($e['hen_destino']) && ($rutas["b$i-e$j"] ?? null) === null;
            }
        }

        $ahora = now();
        $vigente = fn(array $e) => !empty($e['hen_activo']) && empty($e['sin_salida'])
            && (empty($e['hen_vigente_desde'])
                || ($ahora->gte(\Carbon\Carbon::parse($e['hen_vigente_desde'])) && $ahora->lt(\Carbon\Carbon::parse($e['hen_vigente_hasta']))));
        $porOrden = fn(string $campo) => fn($a, $b) => ($a[$campo] ?? 0) <=> ($b[$campo] ?? 0);
        $solo = fn(array $fila, array $campos) => array_intersect_key($fila, array_flip($campos));

        $bloques = [];
        foreach ($h['bloques'] ?? [] as $b) {
            if (empty($b['hbl_visible'])) {
                continue;
            }

            $textos = array_values(array_filter($b['textos'] ?? [], fn($t) => ($t['htx_activo'] ?? true) !== false));
            usort($textos, fn($x, $y) => [$x['htx_clave'], $x['htx_orden']] <=> [$y['htx_clave'], $y['htx_orden']]);

            $todos = array_values(array_filter($b['enlaces'] ?? [], $vigente));
            usort($todos, $porOrden('hen_orden'));
            $enlaces = [];
            foreach ($todos as $e) {
                if (!empty($e['hen_padre_id'])) {
                    continue;
                }
                $sublinks = array_values(array_filter($todos, fn($s) => ($s['hen_padre_id'] ?? null) === $e['hen_id']));
                // Un link sin destino solo sirve para abrir sus sublinks.
                if (empty($e['hen_enlace']) && !$sublinks) {
                    continue;
                }
                $enlaces[] = $solo($e, self::TIENDA_ENLACE) + ['sublinks' => array_map(fn($s) => $solo($s, self::TIENDA_ENLACE), $sublinks)];
            }

            $bloques[] = $solo($b, self::TIENDA_BLOQUE) + [
                'textos' => array_map(fn($t) => $solo($t, self::TIENDA_TEXTO), $textos),
                'enlaces' => $enlaces,
            ];
        }
        usort($bloques, $porOrden('hbl_orden'));

        return $solo($h, self::TIENDA_HEADER) + ['bloques' => $bloques];
    }

    // Pública: entrega una imagen de la carpeta de headers y nada más (el nombre se revisa antes de abrir el archivo).
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
