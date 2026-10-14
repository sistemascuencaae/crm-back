<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Orquestador de las páginas informativas (Términos, Garantías, Conócenos, etc.).
 * Llama a las funciones ecommerce.fn_pagina_informativa_* (que validan con RAISE EXCEPTION
 * y devuelven nada / filas) y prepara los datos que responden los controladores.
 * Cada modificación exitosa limpia la caché pública de esa página.
 */
class PaginaInformativaService
{
    private const CACHE_PREFIJO = 'pagina_web.paginas.publica.';
    private const CACHE_MINUTOS = 10;

    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_pagina_informativa_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'               => 'No se identificó al usuario que realiza el cambio.',
        'PAGINA_NO_EXISTE'               => 'La página no existe.',
        'PAGINA_SIN_VALIDACION'          => 'Esta página todavía no se puede editar.',
        'PAGINA_TITULO_REQUERIDO'        => 'El título de la página es obligatorio.',
        'PAGINA_TITULO_LARGO'            => 'El título de la página no puede superar 120 caracteres.',
        'PAGINA_ACTIVO_INVALIDO'         => 'La visibilidad de la página debe ser verdadero o falso.',
        'PAGINA_CONTENIDO_INVALIDO'      => 'El contenido de la página no es válido.',
        // Términos y condiciones
        'TERMINOS_EMPRESA_REQUERIDA'     => 'El nombre de la empresa es obligatorio.',
        'TERMINOS_EMPRESA_LARGA'         => 'El nombre de la empresa no puede superar 120 caracteres.',
        'TERMINOS_TITULO_REQUERIDO'      => 'El título del documento es obligatorio.',
        'TERMINOS_TITULO_LARGO'          => 'El título del documento no puede superar 150 caracteres.',
        'TERMINOS_SECCIONES_INVALIDAS'   => 'Las secciones no tienen un formato válido.',
        'TERMINOS_DEMASIADAS_SECCIONES'  => 'La página no puede tener más de 50 secciones.',
        'TERMINOS_SECCION_VACIA'         => 'Hay una sección vacía. Agrega un título, párrafos, una lista o una tabla, o elimínala.',
        'TERMINOS_SECCION_TITULO_LARGO'  => 'El título de una sección no puede superar 200 caracteres.',
        'TERMINOS_PARRAFO_LARGO'         => 'Un párrafo no puede superar 3000 caracteres.',
        'TERMINOS_DEMASIADOS_PARRAFOS'   => 'Una sección no puede tener más de 30 párrafos.',
        'TERMINOS_LISTA_ITEM_LARGO'      => 'Un elemento de la lista no puede superar 500 caracteres.',
        'TERMINOS_DEMASIADOS_ITEMS'      => 'Una lista no puede tener más de 50 elementos.',
        'TERMINOS_TABLA_FILA_INCOMPLETA' => 'Cada fila de la tabla necesita el dato y el valor.',
        'TERMINOS_TABLA_TEXTO_LARGO'     => 'El dato de la tabla no puede superar 100 caracteres, y el valor 500.',
        'TERMINOS_DEMASIADAS_FILAS'      => 'Una tabla no puede tener más de 30 filas.',
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