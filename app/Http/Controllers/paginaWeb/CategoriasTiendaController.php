<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Menú de categorías del header de la tienda (público, sin login). La base decide cuáles salen
// (ecommerce.fn_categoria_web_listar_menu: las que se ven y tienen productos en la tienda en su rama) y las devuelve
// planas con su padre, ordenadas por nivel y nombre: aquí solo se arma el árbol. Lo que sale en la tienda se decide en
// Inventarios > Tipos de producto ("En la web"); al guardar productos, marcas o tipos de producto se borra la caché.
class CategoriasTiendaController extends Controller
{
    private const CACHE_MENU    = 'pagina_web.categorias_menu.publica';
    private const CACHE_MINUTOS = 10;

    // Tras un cambio (Inventarios), la tienda debe ver lo nuevo.
    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_MENU);
    }

    // GET public/pagina-web/categorias-menu → [{id, nombre, hijos: [{id, nombre, hijos: [...]}]}]
    public function menu()
    {
        try {
            $menu = Cache::remember(self::CACHE_MENU, now()->addMinutes(self::CACHE_MINUTOS), function () {
                $filas = DB::select('SELECT * FROM ecommerce.fn_categoria_web_listar_menu()');

                // Un nodo por categoría (los objetos se comparten: agregar un hijo a $nodos lo agrega al árbol)
                $nodos = [];
                foreach ($filas as $fila) {
                    $nodos[$fila->tpr_id] = (object) ['id' => $fila->tpr_id, 'nombre' => $fila->nombre, 'hijos' => []];
                }

                // Cada nodo va dentro de su padre; los departamentos (sin padre) van en la raíz
                $raiz = [];
                foreach ($filas as $fila) {
                    if ($fila->padre_id === null) {
                        $raiz[] = $nodos[$fila->tpr_id];
                    } elseif (isset($nodos[$fila->padre_id])) {
                        $nodos[$fila->padre_id]->hijos[] = $nodos[$fila->tpr_id];
                    }
                }

                return $raiz;
            });

            return response()
                ->json(RespuestaApi::returnResultado('success', 'Menú de categorías', $menu))
                ->header('Cache-Control', 'no-cache');
        } catch (\Throwable $th) {
            Log::error('No se pudo cargar el menú de categorías', ['error' => $th->getMessage()]);

            return response()
                ->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el menú de categorías', null), 500)
                ->header('Cache-Control', 'no-store');
        }
    }
}
