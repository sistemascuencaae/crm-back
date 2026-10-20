<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Galerías de imágenes (logos de pago, marcas aliadas, fotos de Beneficios…).
 * Las galerías vienen por script; desde el CRM se suben, ordenan, ocultan y eliminan sus imágenes.
 * Llama a las funciones ecommerce.fn_galeria_*. La tienda las lee por su clave (con caché).
 */
class GaleriaService
{
    private const CACHE_PREFIJO       = 'pagina_web.galerias.publica.';
    private const CACHE_INICIO_BANNER = 'pagina_web.inicio_banner.publica';
    private const CACHE_MINUTOS       = 10;

    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_galeria_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'        => 'No se identificó al usuario que realiza el cambio.',
        'GALERIA_NO_EXISTE'       => 'La galería no existe.',
        'GALERIA_LLENA'           => 'La galería llegó a su máximo de imágenes. Elimina alguna antes de subir otra.',
        'IMAGEN_NO_EXISTE'        => 'La imagen no existe.',
        'IMAGEN_REQUERIDA'        => 'Sube una imagen.',
        'IMAGEN_INVALIDA'         => 'La dirección de la imagen no es válida.',
        'IMAGEN_LARGA'            => 'La dirección de la imagen no puede superar 500 caracteres.',
        'IMAGEN_NOMBRE_REQUERIDO' => 'Escribe el nombre de la imagen.',
        'IMAGEN_NOMBRE_LARGO'     => 'El nombre de la imagen no puede superar 100 caracteres.',
        'IMAGEN_ACTIVO_INVALIDO'  => 'La visibilidad de la imagen debe ser verdadero o falso.',
        'IMAGEN_MOVIL_INVALIDA'   => 'La dirección de la imagen para celular no es válida.',
        'IMAGEN_MOVIL_LARGA'      => 'La dirección de la imagen para celular no puede superar 500 caracteres.',
        'IMAGEN_MOSTRAR_INVALIDO' => '"Mostrar en" debe ser computadora y celular, solo computadora o solo celular.',
        // En galerías estos tres solo los lanza el enlace de la imagen (las mismas reglas del header)
        'CAMPO_ENLACE'            => 'El enlace no es válido: usa una ruta de la tienda (/...) o una dirección https://.',
        'CAMPO_LARGO'             => 'El enlace no puede superar 250 caracteres.',
        'CAMPO_INVALIDO'          => 'El valor de "abrir en pestaña nueva" debe ser verdadero o falso.',
        'ORDEN_INVALIDO'          => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO'    => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
    ];

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /** Las galerías, con cuántas imágenes tiene cada una y cuántas se muestran (gal_mostrar). */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_galeria_listar()');
    }

    /** Las imágenes de una galería, en orden (también las ocultas). */
    public function listarImagenes(int $galId): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar(?)', [$galId]);
    }

    /**
     * Agrega una imagen al final de su galería (si no llegó a su máximo). Devuelve su id.
     * $datos: {gal_id, gim_imagen, gim_nombre, usu_id} y, en banners, gim_imagen_movil, gim_enlace,
     * gim_nueva_pestana, gim_mostrar_en.
     */
    public function crearImagen(array $datos): int
    {
        $gimId = DB::selectOne('SELECT ecommerce.fn_galeria_imagen_crear(?::jsonb) AS id', [$this->json($datos)])->id;
        $this->limpiarCache((int) $datos['gal_id']);

        return $gimId;
    }

    /** Cambia los datos de la imagen (solo lo que llega). */
    public function modificarImagen(int $gimId, array $datos): void
    {
        $galId = $this->galeriaDeImagen($gimId);
        DB::statement('SELECT ecommerce.fn_galeria_imagen_modificar(?, ?::jsonb)', [$gimId, $this->json($datos)]);
        $this->limpiarCache($galId);
    }

    public function eliminarImagen(int $gimId): void
    {
        $galId = $this->galeriaDeImagen($gimId);
        DB::statement('SELECT ecommerce.fn_galeria_imagen_eliminar(?)', [$gimId]);
        $this->limpiarCache($galId);
    }

    /** Nuevo orden de TODAS las imágenes de una galería. $datos: {gal_id, ids: [gim_id…], usu_id} */
    public function ordenarImagenes(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_galeria_imagen_ordenar(?::jsonb)', [$this->json($datos)]);
        $this->limpiarCache((int) $datos['gal_id']);
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * Las imágenes visibles de una galería, en orden y solo las que se muestran:
     * [{gim_imagen, gim_imagen_movil, gim_nombre, gim_enlace, gim_nueva_pestana, gim_mostrar_en}]
     */
    public function listarPublicas(string $galClave): array
    {
        return Cache::remember(self::CACHE_PREFIJO . $galClave, now()->addMinutes(self::CACHE_MINUTOS), function () use ($galClave) {
            return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$galClave]);
        });
    }

    /** El banner del inicio; lista vacía si su sección está apagada. Mismo formato que listarPublicas. */
    public function listarInicioBanner(): array
    {
        return Cache::remember(self::CACHE_INICIO_BANNER, now()->addMinutes(self::CACHE_MINUTOS), function () {
            return DB::select('SELECT * FROM ecommerce.fn_galeria_inicio_banner_publicas()');
        });
    }

    /** Lo llama InicioSeccionService al prender o apagar una sección del inicio. */
    public static function limpiarCacheInicioBanner(): void
    {
        Cache::forget(self::CACHE_INICIO_BANNER);
    }

    // -----------------------------------------------------------------
    // Errores
    // -----------------------------------------------------------------

    /** Traduce el código que lanzó la base a un mensaje amigable; null si no es un código conocido. */
    public function mensajeError(QueryException $e): ?string
    {
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . $codigo . '\b/', $e->getMessage())) {
                return $mensaje;
            }
        }

        return null;
    }

    // -----------------------------------------------------------------
    // Internos
    // -----------------------------------------------------------------

    /** La tienda guarda en caché cada galería por su clave: tras un cambio se borra. */
    private function limpiarCache(?int $galId): void
    {
        if ($galId === null) {
            return;
        }

        $fila = DB::selectOne('SELECT gal_clave FROM ecommerce.galeria WHERE gal_id = ?', [$galId]);
        if ($fila) {
            Cache::forget(self::CACHE_PREFIJO . $fila->gal_clave);

            if ($fila->gal_clave === 'inicio_banner') {
                self::limpiarCacheInicioBanner();
            }

            // Las páginas de marca traen sus imágenes dentro de su JSON
            PaginaMarcaService::limpiarCache();
        }
    }

    private function galeriaDeImagen(int $gimId): ?int
    {
        $fila = DB::selectOne('SELECT gal_id FROM ecommerce.galeria_imagen WHERE gim_id = ?', [$gimId]);

        return $fila ? (int) $fila->gal_id : null;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}