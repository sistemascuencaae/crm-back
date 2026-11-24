<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Páginas de la tienda (público, sin login): las páginas del CRM (Ecommerce > Páginas) listas para dibujar y las
// imágenes de sus secciones.
//   GET public/paginas/inicio              → la página marcada como Inicio (data = null si no hay: la tienda usa su inicio fijo)
//   GET public/paginas/{ruta}              → una página activa por su dirección (code 404 en el cuerpo si no existe o está inactiva)
//   GET public/secciones/imagenes/{nombre} → una imagen de las secciones
// La base arma la página entera (ecommerce.fn_pagina_tienda: secciones visibles en orden, su plantilla, sus valores con la
// dirección de hoy de cada enlace y las tarjetas reales de sus productos). Aquí solo se completa la dirección de las
// fotos de las tarjetas y la de las imágenes de las secciones (la base no conoce la de este servidor).
// Cada página armada se guarda 60 s (ver SEGUNDOS_CACHE); lo que se guarda en el CRM la borra y sale al momento. Siempre
// HTTP 200 (estándar): el resultado va en status y code. El mantenimiento está en ecommerce/PaginasController,
// SeccionesController y GrupoProductosController (CRM), que llaman a limpiarCache al guardar.
class PaginasTiendaController extends Controller
{
    // Imágenes de las secciones: carpeta ecommerce/secciones (NAS o local); nombre aleatorio de 40 letras y números + .webp
    private const CARPETA_IMAGENES = ArchivosPaginaWeb::CARPETA . '/secciones';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.webp$/';

    // Cada página armada se guarda 60 s (paginaGuardada): con mucha gente la base la arma una vez por minuto y no una vez
    // por visita. Lo que se guarda en el CRM borra la caché (limpiarCache) y sale al momento; lo que cambia solo (stock,
    // precio, un producto que deja de cumplir la regla de la tienda, una oferta que empieza o termina) tarda hasta 60 s.
    private const CACHE_VERSION = 'ecommerce.pagina_tienda.version';
    private const SEGUNDOS_CACHE = 60;
    private const ESPERA_SEGUNDOS = 5;

    public function inicio()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Página de inicio', $this->pagina(null)))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página de inicio', ['error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null));
        }
    }

    public function ver(string $ruta)
    {
        try {
            $pagina = $this->pagina($ruta);
            if (!$pagina) {
                // RespuestaApi no deja elegir el código del error (siempre 500): se reemplaza solo el code
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'La página no existe.', null))
                    ->header('Cache-Control', 'no-cache');
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Página', $pagina))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo leer la página', ['ruta' => $ruta, 'error' => $th->getMessage()]);

            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la página.', null));
        }
    }

    // Una imagen de las secciones (pública: la pide la tienda y el CRM). El nombre nunca cambia de contenido.
    public function verImagen(string $nombre)
    {
        if (!preg_match(self::NOMBRE_IMAGEN, $nombre)) {
            abort(404);
        }

        // Del NAS o de local (ArchivosPaginaWeb::entregar).
        return ArchivosPaginaWeb::entregar(self::CARPETA_IMAGENES . '/' . $nombre, 'image/webp');
    }

    // El CRM guardó un cambio que se ve en las páginas (página, sección, orden, grupo de productos): todas las páginas se
    // vuelven a armar en la próxima visita. Se cambia la versión en vez de borrar clave por clave (no hace falta saber qué
    // páginas hay ni qué dirección tenían antes).
    public static function limpiarCache(): void
    {
        Cache::forever(self::CACHE_VERSION, Str::random(8));
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /**
     * El JSON de ecommerce.fn_pagina_tienda para la ruta (null = inicio), guardado SEGUNDOS_CACHE. null si no hay página
     * (eso no se guarda: una página recién creada o activada sale al momento, y las direcciones inventadas no llenan la
     * caché). Cuando se vence, solo una petición la vuelve a armar; las demás esperan hasta ESPERA_SEGUNDOS y leen lo que
     * dejó. Se guarda el JSON de la base, no la respuesta: las direcciones de las fotos se completan en cada petición con
     * el servidor que la recibe.
     */
    private function paginaGuardada(?string $ruta): ?string
    {
        $clave = 'ecommerce.pagina_tienda.' . Cache::get(self::CACHE_VERSION, '0') . '.' . md5($ruta ?? '');
        $json = Cache::get($clave);
        if ($json !== null) {
            return $json;
        }

        $armar = function () use ($clave, $ruta) {
            $json = Cache::get($clave);
            if ($json === null) {
                $fila = DB::selectOne('SELECT ecommerce.fn_pagina_tienda(?) AS pagina', [$ruta]);
                $json = $fila->pagina ?? null;
                if ($json !== null) {
                    Cache::put($clave, $json, self::SEGUNDOS_CACHE);
                }
            }

            return $json;
        };

        try {
            return Cache::lock($clave . '.armando', self::ESPERA_SEGUNDOS * 2)->block(self::ESPERA_SEGUNDOS, $armar);
        } catch (LockTimeoutException $e) {
            // La base está tardando más de lo normal: esta petición la arma por su cuenta.
            return $armar();
        }
    }

    /**
     * La página tal como la arma la base (ecommerce.fn_pagina_tienda), con las tarjetas en su forma de la tienda y la
     * dirección de las imágenes: {pagina, url_imagenes, secciones: [...]}. null si no hay página.
     */
    private function pagina(?string $ruta): ?array
    {
        $json = $this->paginaGuardada($ruta);
        if ($json === null) {
            return null;
        }
        $pagina = json_decode($json, true);

        foreach ($pagina['secciones'] as &$seccion) {
            // Objetos vacíos de la base siguen siendo objetos en el JSON
            foreach (['sec_valores', 'sec_valores_tarjeta', 'tarjetas'] as $campo) {
                $seccion[$campo] = $seccion[$campo] ?: (object) [];
            }
            foreach ($seccion['elementos'] as &$elemento) {
                $elemento['sel_valores'] = $elemento['sel_valores'] ?: (object) [];
            }
            unset($elemento);
            if (is_array($seccion['tarjetas'])) {
                foreach ($seccion['tarjetas'] as $clave => $lista) {
                    $seccion['tarjetas'][$clave] = array_map(fn ($t) => $this->tarjeta($t), $lista);
                }
            }
        }
        unset($seccion);

        return [
            'pagina' => $pagina['pagina'],
            'url_imagenes' => url('api/public/secciones/imagenes') . '/',
            'secciones' => $pagina['secciones'],
        ];
    }

    /**
     * La tarjeta de producto de todo el sitio, de una fila de ecommerce.fn_producto_tienda_tarjetas.
     */
    private function tarjeta(array $f): array
    {
        return [
            'pro_id'          => (int) $f['pro_id'],
            'codigo'          => $f['pro_codigo'],
            'nombre'          => $f['pro_nombre'],
            'marca'           => $f['marca'],
            'tpr_id'          => (int) $f['tpr_id'],
            'categoria'       => $f['categoria'],
            'imagenes'        => array_map(fn ($ruta) => url($ruta), is_array($f['imagenes'] ?? null) ? $f['imagenes'] : []),
            'destacado'       => (bool) $f['destacado'],
            // En oferta (producto.pro_oferta): etiqueta Oferta.
            'oferta'          => (bool) ($f['oferta'] ?? false),
            'precio'          => $f['precio'] !== null ? (float) $f['precio'] : null,
            'precio_anterior' => $f['precio_anterior'] !== null ? (float) $f['precio_anterior'] : null,
            'stock'           => (float) $f['stock'],
            // Su factura pide los datos de MOTOS (inventarios.fn_producto_es_moto)
            'es_moto'         => (bool) ($f['es_moto'] ?? false),
        ];
    }
}
