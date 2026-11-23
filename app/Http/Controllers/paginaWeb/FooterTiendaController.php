<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ArchivosPaginaWeb;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Footer de la tienda (público, sin login): el footer que se muestra ahora (ecommerce.fn_footer_tienda_publico) y las imágenes
// de los footers. El mantenimiento está en ecommerce/FooterController (CRM), que borra la caché al guardar.
class FooterTiendaController extends Controller
{
    // Imágenes de los footers (logo, logos de bancos): carpeta ecommerce/footers (NAS o local); se piden por
    // api/public/footers/imagenes/{nombre}. En la base se guarda la ruta relativa; aquí se completa con el dominio al salir.
    private const CARPETA_IMAGENES = ArchivosPaginaWeb::CARPETA . '/footers';
    private const RUTA_IMAGENES = '/api/public/footers/imagenes/';
    private const NOMBRE_IMAGEN = '/^[A-Za-z0-9]{40}\.(webp|jpg|png)$/';
    private const TIPOS_IMAGEN = ['webp' => 'image/webp', 'jpg' => 'image/jpeg', 'png' => 'image/png'];

    // Lo que lee la tienda (tienda()): se guarda 60 s; cada cambio guardado en el CRM lo borra. Los 60 s cubren el
    // cambio de footer por fechas sin que nadie guarde nada.
    private const CACHE_TIENDA = 'ecommerce.footer_tienda';
    private const SEGUNDOS_CACHE_TIENDA = 60;

    // El CRM guardó un cambio: la tienda lo ve en la próxima petición.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_TIENDA);
    }

    // El footer que la tienda muestra ahora, listo para dibujar (ecommerce.fn_footer_tienda_publico decide qué sale:
    // columnas visibles, elementos activos y vigentes con sus sub-enlaces, íconos activos y {año} ya reemplazado).
    // Aquí solo se completan las imágenes con el dominio. data = null si todavía no hay footers (diseño de respaldo).
    public function tienda()
    {
        try {
            $footer = Cache::remember(self::CACHE_TIENDA, self::SEGUNDOS_CACHE_TIENDA, function () {
                $fila = DB::selectOne('SELECT ecommerce.fn_footer_tienda_publico() AS footer');

                return $fila->footer ? $this->conImagenesCompletas(json_decode($fila->footer, true)) : null;
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
        if (!preg_match(self::NOMBRE_IMAGEN, $nombre)) {
            abort(404);
        }

        // Del NAS o de local (ArchivosPaginaWeb::entregar).
        return ArchivosPaginaWeb::entregar(self::CARPETA_IMAGENES . '/' . $nombre, self::TIPOS_IMAGEN[pathinfo($nombre, PATHINFO_EXTENSION)]);
    }

    // ================= Ayudantes =================

    // Las imágenes se guardan con su ruta relativa: al salir llevan el dominio del back (la base no lo conoce).
    // fel_imagen de cada elemento (también en los sub-enlaces) y fic_imagen de cada ícono.
    private function conImagenesCompletas(array $f): array
    {
        $completa = fn($ruta) => is_string($ruta) && str_starts_with($ruta, self::RUTA_IMAGENES) ? url($ruta) : $ruta;
        $elementos = function (array $lista) use (&$elementos, $completa): array {
            foreach ($lista as $i => $e) {
                if (array_key_exists('fel_imagen', $e)) {
                    $lista[$i]['fel_imagen'] = $completa($e['fel_imagen']);
                }
                foreach ($e['iconos'] ?? [] as $j => $icono) {
                    if (array_key_exists('fic_imagen', $icono)) {
                        $lista[$i]['iconos'][$j]['fic_imagen'] = $completa($icono['fic_imagen']);
                    }
                }
                if (!empty($e['subenlaces'])) {
                    $lista[$i]['subenlaces'] = $elementos($e['subenlaces']);
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
}