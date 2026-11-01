<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Galerías de imágenes (logos de pago, fotos de Beneficios, banners…). Los logos de las marcas no son galería:
 * salen de cada marca (MarcaWebService).
 * Las galerías vienen por script; desde el CRM se suben, ordenan, ocultan y eliminan sus imágenes.
 * Cada imagen puede tener vigencia (desde / hasta); las de galerías tipo 'tarjetas' llevan además
 * título, subtítulo, color y botón. Llama a las funciones ecommerce.fn_galeria_*.
 * La tienda las lee por su clave (con caché que respeta la vigencia: CachePaginaWeb).
 */
class GaleriaService
{
    private const CACHE_PREFIJO = 'pagina_web.galerias.publica.';

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
        'IMAGEN_TITULO_REQUERIDO' => 'La tarjeta necesita un título.',
        'IMAGEN_TITULO_LARGO'     => 'El título de la tarjeta no puede superar 40 caracteres.',
        'IMAGEN_SUBTITULO_LARGO'  => 'El subtítulo de la tarjeta no puede superar 80 caracteres.',
        'IMAGEN_BOTON_LARGO'      => 'El texto del botón no puede superar 20 caracteres.',
        'IMAGEN_COLOR_INVALIDO'   => 'Elige un color de la paleta.',
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

    /** Las imágenes de una galería, en orden (también las ocultas y vencidas), con su vigencia. */
    public function listarImagenes(int $galId): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar(?)', [$galId]);
    }

    /**
     * Agrega una imagen al final de su galería (si no llegó a su máximo). Devuelve su id.
     * $datos: {gal_id, gim_imagen, gim_nombre, gim_desde, gim_hasta, usu_id}; en banners, gim_imagen_movil,
     * gim_enlace, gim_nueva_pestana, gim_mostrar_en; en tarjetas, gim_titulo, gim_subtitulo, gim_color, gim_boton.
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

    /** La manda a la papelera (el archivo se borra al vaciar) */
    public function eliminarImagen(int $gimId, int $usuId): void
    {
        $galId = $this->galeriaDeImagen($gimId);
        DB::statement('SELECT ecommerce.fn_galeria_imagen_eliminar(?, ?)', [$gimId, $usuId]);
        $this->limpiarCache($galId);
    }

    /** La saca de la papelera, al final de su galería */
    public function restaurarImagen(int $gimId, int $usuId): void
    {
        $galId = $this->galeriaDeImagen($gimId);
        DB::statement('SELECT ecommerce.fn_galeria_imagen_restaurar(?, ?)', [$gimId, $usuId]);
        $this->limpiarCache($galId);
    }

    /** [{gim_id, gim_imagen, gim_imagen_movil, gim_nombre, gim_eliminado_en, gim_eliminado_usu_id, eliminado_por}] */
    public function papeleraImagenes(int $galId): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_papelera(?)', [$galId]), 'gim_eliminado_usu_id');
    }

    /** Borra para siempre las imágenes de la papelera de la galería, con sus archivos. Devuelve cuántas borró. */
    public function vaciarImagenes(int $galId, int $usuId): int
    {
        // La base devuelve una fila por archivo (imagen y, si tiene, la de celular): se cuentan las imágenes antes
        $cantidad = count(DB::select('SELECT gim_id FROM ecommerce.fn_galeria_imagen_papelera(?)', [$galId]));

        ArchivosPaginaWeb::borrar(array_column(DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_vaciar(?, ?)', [$galId, $usuId]), 'imagen'));
        $this->limpiarCache($galId);

        return $cantidad;
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
     * Las imágenes visibles y vigentes de una galería, en orden:
     * [{gim_imagen, gim_imagen_movil, gim_nombre, gim_enlace, gim_nueva_pestana, gim_mostrar_en,
     *   gim_titulo, gim_subtitulo, gim_color, gim_boton}]
     */
    public function listarPublicas(string $galClave): array
    {
        return Cache::remember(self::CACHE_PREFIJO . $galClave, fn () => CachePaginaWeb::vencimiento(), function () use ($galClave) {
            return DB::select('SELECT * FROM ecommerce.fn_galeria_imagen_listar_publicas(?)', [$galClave]);
        });
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

            // El inicio, las páginas de marca y España Motos traen imágenes dentro de su JSON
            PaginaMarcaService::limpiarCache();
            InicioSeccionService::limpiarCache();
            EspanaMotosService::limpiarCache();

            // Los logos de las franjas del detalle salen de esa galería
            if ($fila->gal_clave === FranjaDetalleService::GALERIA_LOGOS) {
                FranjaDetalleService::limpiarCache();
            }
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