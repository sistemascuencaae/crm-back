<?php

namespace App\Servicios\PaginaWeb;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Categorías de la tienda. Las categorías vienen del ERP (public.tipo_producto, solo lectura);
 * aquí se maneja lo que decide marketing: qué departamentos salen en la tienda, qué categorías
 * se ocultan, cuáles son destacadas, su imagen redonda, un nombre visible y su orden.
 * La regla de "qué está en la tienda" está en la base (fn_categoria_web_en_tienda).
 * Llama a las funciones ecommerce.fn_categoria_web_*. La tienda lee el menú con caché;
 * las destacadas van dentro del inicio (InicioSeccionService::publica).
 */
class CategoriaWebService
{
    private const CACHE_MENU    = 'pagina_web.categorias_menu.publica';
    private const CACHE_MINUTOS = 10;

    // Mensaje amigable para cada código que lanzan las funciones ecommerce.fn_categoria_web_*
    public const MENSAJES_ERROR = [
        'REQUIERE_USUARIO'             => 'No se identificó al usuario que realiza el cambio.',
        'CATEGORIA_NO_EXISTE'          => 'La categoría no existe o está inactiva en el sistema.',
        'CATEGORIA_NOMBRE_LARGO'       => 'El nombre visible no puede superar 60 caracteres.',
        'CATEGORIA_IMAGEN_INVALIDA'    => 'La dirección de la imagen no es válida.',
        'CATEGORIA_IMAGEN_LARGA'       => 'La dirección de la imagen no puede superar 500 caracteres.',
        'CATEGORIA_DESTACADA_INVALIDA' => 'El valor de "destacada" debe ser verdadero o falso.',
        'CATEGORIA_SIN_IMAGEN'         => 'Sube una imagen antes de marcarla como destacada.',
        'CATEGORIA_VISIBLE_INVALIDO'   => 'El valor de "mostrar en la tienda" debe ser verdadero o falso.',
        'CATEGORIA_SOLO_DEPARTAMENTO'  => 'Solo los departamentos se pueden mostrar u ocultar en la tienda.',
        'CATEGORIA_OCULTA_INVALIDO'    => 'El valor de "oculta" debe ser verdadero o falso.',
        'CATEGORIA_NO_SE_OCULTA_DEPARTAMENTO' => 'Un departamento no se oculta: apágalo con "En la tienda".',
        'ORDEN_INVALIDO'               => 'El nuevo orden no es válido.',
        'ORDEN_DESACTUALIZADO'         => 'La lista cambió mientras la ordenabas. Recarga la página e inténtalo de nuevo.',
    ];

    /** Tras un cambio, la tienda debe ver lo nuevo. */
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_MENU);

        // Las destacadas van dentro del inicio, y un bloque cuya categoría se oculta deja de verse
        // en el inicio y en las páginas de marca
        InicioSeccionService::limpiarCache();
        PaginaMarcaService::limpiarCache();
    }

    // -----------------------------------------------------------------
    // CRM
    // -----------------------------------------------------------------

    /** Todas las categorías activas del ERP, con su ruta y lo configurado. */
    public function listar(): array
    {
        return DB::select('SELECT * FROM ecommerce.fn_categoria_web_listar()');
    }

    /** Antes de ocultar: cuántas subcategorías y productos activos tiene esa rama. {subcategorias, productos} */
    public function impacto(int $tprId): object
    {
        return DB::selectOne('SELECT * FROM ecommerce.fn_categoria_web_impacto(?)', [$tprId]);
    }

    /** $datos: solo lo que cambia (cwe_destacada, cwe_imagen, cwe_nombre, cwe_visible, cwe_oculta) + usu_id. */
    public function modificar(int $tprId, array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_categoria_web_modificar(?, ?::jsonb)', [$tprId, $this->json($datos)]);
        self::limpiarCache();
    }

    /** $datos: {ids: [tpr_id…] (todas las destacadas, en el nuevo orden), usu_id} */
    public function ordenar(array $datos): void
    {
        DB::statement('SELECT ecommerce.fn_categoria_web_ordenar(?::jsonb)', [$this->json($datos)]);
        self::limpiarCache();
    }

    // -----------------------------------------------------------------
    // Tienda
    // -----------------------------------------------------------------

    /**
     * El menú de categorías del header, como árbol:
     *   [{id, nombre, hijos: [{id, nombre, hijos: [...]}]}]
     * La base devuelve filas planas con su padre, ordenadas por nivel: así el padre siempre
     * aparece antes que sus hijos.
     */
    public function listarMenu(): array
    {
        return Cache::remember(self::CACHE_MENU, now()->addMinutes(self::CACHE_MINUTOS), function () {
            $filas = DB::select('SELECT * FROM ecommerce.fn_categoria_web_listar_menu()');

            // Un nodo por categoría (los objetos se comparten: agregar un hijo a $nodos lo agrega al árbol)
            $nodos = [];
            foreach ($filas as $fila) {
                $nodos[$fila->tpr_id] = (object) ['id' => $fila->tpr_id, 'nombre' => $fila->nombre, 'hijos' => []];
            }

            // Cada nodo va dentro de su padre; los departamentos (sin padre) van en la raíz
            $menu = [];
            foreach ($filas as $fila) {
                if ($fila->padre_id === null) {
                    $menu[] = $nodos[$fila->tpr_id];
                } elseif (isset($nodos[$fila->padre_id])) {
                    $nodos[$fila->padre_id]->hijos[] = $nodos[$fila->tpr_id];
                }
            }

            return $menu;
        });
    }

    private function json(array $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE);
    }
}