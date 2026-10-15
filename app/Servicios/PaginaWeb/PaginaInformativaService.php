<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquestador de las páginas informativas (Términos, Garantía, etc.).
 * Llama a las funciones ecommerce.fn_pagina_informativa_* (que validan con RAISE EXCEPTION
 * y devuelven nada / filas) y prepara los datos que responden los controladores.
 * Cada modificación exitosa limpia la caché pública de esa página.
 */
class PaginaInformativaService
{
    private const CACHE_PREFIJO = 'pagina_web.paginas.publica.';
    private const CACHE_MINUTOS = 10;

    public function __construct(private LimpiadorHtml $limpiador)
    {
    }

    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_pagina_informativa_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'               => 'No se identificó al usuario que realiza el cambio.',
        'PAGINA_NO_EXISTE'               => 'La página no existe.',
        'PAGINA_SIN_VALIDACION'          => 'Esta página todavía no se puede editar.',
        'PAGINA_TITULO_REQUERIDO'        => 'El título de la página es obligatorio.',
        'PAGINA_TITULO_LARGO'            => 'El título de la página no puede superar 120 caracteres.',
        'PAGINA_ACTIVO_INVALIDO'         => 'La visibilidad de la página debe ser verdadero o falso.',
        'PAGINA_CONTENIDO_INVALIDO'      => 'El contenido de la página no es válido.',
        // Páginas de texto con editor (Garantía…)
        'PAGINA_TEXTO_REQUERIDO'         => 'Escribe el contenido de la página.',
        'PAGINA_TEXTO_LARGO'             => 'El contenido de la página es demasiado largo.',
        'PAGINA_TEXTO_NO_PERMITIDO'      => 'El contenido tiene elementos no permitidos (código o scripts).',
        'PAGINA_IMAGEN_INVALIDA'         => 'La dirección de la imagen no es válida.',
        'PAGINA_IMAGEN_LARGA'            => 'La dirección de la imagen no puede superar 500 caracteres.',
        'PAGINA_IMAGEN_TEXTO_LARGO'      => 'La descripción de la imagen no puede superar 150 caracteres.',
        'PAGINA_PDF_INVALIDO'            => 'La dirección del PDF no es válida.',
        'PAGINA_PDF_LARGO'               => 'La dirección del PDF no puede superar 500 caracteres.',
        'PAGINA_DEMASIADOS_DESPLEGABLES'      => 'La página no puede tener más de 6 desplegables.',
        'PAGINA_DESPLEGABLE_TITULO_REQUERIDO' => 'Cada desplegable necesita un título.',
        'PAGINA_DESPLEGABLE_TITULO_LARGO'     => 'El título de un desplegable no puede superar 100 caracteres.',
        'PAGINA_DESPLEGABLE_TEXTO_REQUERIDO'  => 'Cada desplegable necesita un texto.',
        'PAGINA_DESPLEGABLE_TEXTO_LARGO'      => 'El texto de un desplegable es demasiado largo.',
    ];

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /** Todas las páginas, sin su contenido. */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_pagina_informativa_listar()');
    }

    /** Una página con su contenido, para editarla. null si no existe. */
    public function buscar(string $clave): ?array
    {
        $fila = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_informativa_buscar(?)', [$clave]);

        return $fila ? $this->decodificarContenido($fila) : null;
    }

    /** Guarda solo lo que llega (todo el contenido, o solo la visibilidad). */
    public function modificar(string $clave, array $datos): void
    {
        // Páginas con editor: el HTML se limpia aquí, antes de que llegue a la base
        if (isset($datos['pin_contenido']['texto']) && is_string($datos['pin_contenido']['texto'])) {
            $datos['pin_contenido']['texto'] = $this->limpiador->limpiar($datos['pin_contenido']['texto']);
        }

        // Y el texto de cada desplegable (Garantía extendida…)
        if (isset($datos['pin_contenido']['desplegables']) && is_array($datos['pin_contenido']['desplegables'])) {
            foreach ($datos['pin_contenido']['desplegables'] as $i => $desplegable) {
                if (isset($desplegable['texto']) && is_string($desplegable['texto'])) {
                    $datos['pin_contenido']['desplegables'][$i]['texto'] = $this->limpiador->limpiar($desplegable['texto']);
                }
            }
        }

        DB::statement('SELECT ecommerce.fn_pagina_informativa_modificar(?, ?::jsonb)', [$clave, $this->json($datos)]);
        Cache::forget(self::CACHE_PREFIJO . $clave);
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /** La página si está visible; null si no existe o está oculta. Solo se guarda en caché si existe. */
    public function buscarPublica(string $clave): ?array
    {
        return Cache::remember(self::CACHE_PREFIJO . $clave, now()->addMinutes(self::CACHE_MINUTOS), function () use ($clave) {
            $fila = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_informativa_buscar_publica(?)', [$clave]);

            return $fila ? $this->decodificarContenido($fila) : null;   // null no se guarda en caché
        });
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

    /** PostgreSQL devuelve jsonb como texto: se convierte a arreglo para el JSON de la respuesta. */
    private function decodificarContenido(object $fila): array
    {
        $datos = (array) $fila;
        $datos['pin_contenido'] = json_decode($datos['pin_contenido'] ?? '{}', true);

        return $datos;
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}