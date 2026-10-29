<?php

namespace App\Servicios\PaginaWeb;

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

    // Páginas que muestran "Nuestras marcas" (MarcaWebService::publicas)
    private const CLAVES_CON_MARCAS = ['garantia_extendida', 'beneficios_empresa'];

    public function __construct(private LimpiadorHtml $limpiador, private MarcaWebService $marcas)
    {
    }

    /** Tras un cambio en las marcas de la tienda, las páginas que las muestran deben verlo. */
    public static function limpiarCacheMarcas(): void
    {
        foreach (self::CLAVES_CON_MARCAS as $clave) {
            Cache::forget(self::CACHE_PREFIJO . $clave);
        }
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
        'PAGINA_FIJA'                    => 'Esta página tiene un diseño propio en la tienda: no se puede eliminar ni poner en una carpeta. Puedes ocultarla.',
        'ORDEN_INVALIDO'                 => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO'           => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
        'PAGINA_GRUPO_INVALIDO'          => 'La carpeta elegida no es válida.',
        'GRUPO_NO_EXISTE'                => 'La carpeta no existe.',
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
        'PAGINA_DESPLEGABLE_PDF_INVALIDO'     => 'La dirección del PDF de un desplegable no es válida.',
        'PAGINA_DESPLEGABLE_PDF_LARGO'        => 'La dirección del PDF de un desplegable no puede superar 500 caracteres.',
        'PAGINA_DESPLEGABLE_BOTON_LARGO'      => 'El texto del botón no puede superar 40 caracteres.',
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

    /** Crea una página de documento (oculta). Devuelve su clave, que sale del título. */
    public function crear(array $datos): string
    {
        $pinId = DB::selectOne('SELECT ecommerce.fn_pagina_informativa_crear(?::jsonb) AS id', [$this->json($datos)])->id;

        return DB::selectOne('SELECT pin_clave FROM ecommerce.pagina_informativa WHERE pin_id = ?', [$pinId])->pin_clave;
    }

    /** Manda a la papelera una página que no sea fija. */
    public function eliminar(string $clave, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_eliminar(?, ?)', [$clave, $usuId]);
        Cache::forget(self::CACHE_PREFIJO . $clave);
    }

    /** La saca de la papelera, al final de su carpeta. */
    public function restaurar(string $clave, int $usuId): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_restaurar(?, ?)', [$clave, $usuId]);
        Cache::forget(self::CACHE_PREFIJO . $clave);
    }

    /** Papelera de páginas y carpetas: [{tipo ('carpeta' | 'pagina'), id, pin_clave, texto, detalle, eliminado_en, eliminado_usu_id, eliminado_por}] */
    public function papelera(): array
    {
        return PapeleraPaginaWeb::agregarEliminadoPor(DB::select('SELECT * FROM ecommerce.fn_pagina_informativa_papelera()'), 'eliminado_usu_id');
    }

    /** Borra para siempre las páginas y carpetas de la papelera. Devuelve cuántas borró (las que se veían en la papelera). */
    public function vaciar(int $usuId): int
    {
        $claves = array_filter(array_column(DB::select('SELECT * FROM ecommerce.fn_pagina_informativa_papelera()'), 'pin_clave'));

        $cantidad = (int) DB::selectOne('SELECT ecommerce.fn_pagina_informativa_vaciar(?) AS cantidad', [$usuId])->cantidad;
        foreach ($claves as $clave) {
            Cache::forget(self::CACHE_PREFIJO . $clave);
        }

        return $cantidad;
    }

    /** Nuevo orden de los documentos de una carpeta. $datos: {pig_id (o null), ids: [pin_id…], usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_pagina_informativa_ordenar(?::jsonb)', [$this->json($datos)]);
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * La página si está visible; null si no existe o está oculta. Solo se guarda en caché si existe.
     * Garantía extendida y Beneficios para empresa traen además marcas: [{mar_id, nombre, imagen, enlace}].
     */
    public function buscarPublica(string $clave): ?array
    {
        return Cache::remember(self::CACHE_PREFIJO . $clave, now()->addMinutes(self::CACHE_MINUTOS), function () use ($clave) {
            $fila = DB::selectOne('SELECT * FROM ecommerce.fn_pagina_informativa_buscar_publica(?)', [$clave]);

            if (!$fila) {
                return null;   // null no se guarda en caché
            }

            $pagina = $this->decodificarContenido($fila);
            if (in_array($clave, self::CLAVES_CON_MARCAS, true)) {
                $pagina['marcas'] = $this->marcas->publicas();
            }

            return $pagina;
        });
    }

    /**
     * Diálogo de documentos de la tienda: los documentos visibles agrupados por carpeta, en orden.
     * [ {pig_id, pig_nombre, documentos: [{pin_clave, pin_titulo}, …]}, … ]
     * Los que no tienen carpeta van al final, en una sección con pig_id y pig_nombre en null.
     * Sin caché: es una consulta pequeña.
     */
    public function listarPublicas(): array
    {
        $filas = DB::select('SELECT * FROM ecommerce.fn_pagina_informativa_listar_publicas()');
        $secciones = [];

        foreach ($filas as $fila) {
            $ultima = count($secciones) - 1;

            // Las filas vienen ordenadas por carpeta: cuando cambia la carpeta, empieza otra sección
            if ($ultima < 0 || $secciones[$ultima]['pig_id'] !== $fila->pig_id) {
                $secciones[] = ['pig_id' => $fila->pig_id, 'pig_nombre' => $fila->pig_nombre, 'documentos' => []];
                $ultima++;
            }

            $secciones[$ultima]['documentos'][] = ['pin_clave' => $fila->pin_clave, 'pin_titulo' => $fila->pin_titulo];
        }

        return $secciones;
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