<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// El selector de enlace del CRM (headers, footers y secciones de páginas): qué se puede elegir y cómo se llama cada
// destino ya elegido, y el buscador de categorías y marcas. Solo lectura. Las reglas viven en ecommerce.fn_enlace_catalogo y ecommerce.fn_enlace_describir
// (database/sql/enlaces_catalogo_funciones.sql).
class EnlacesController extends Controller
{
    // Qué datos lleva cada lista del catálogo (las mismas claves que lee el CRM)
    private const CAMPOS_CATALOGO = [
        'pagina'    => ['paginas', ['id', 'nombre', 'ruta', 'activo', 'inicio']],
        'documento' => ['documentos', ['id', 'nombre', 'ruta', 'activo', 'carpeta']],
        'carpeta'   => ['carpetas', ['id', 'nombre', 'ruta']],
        'pantalla'  => ['pantallas', ['clave', 'nombre', 'ruta']],
        'grupo'     => ['grupos', ['id', 'nombre', 'activo']],
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // GET ecommerce/enlacesCatalogo → {paginas, documentos, carpetas, pantallas, grupos}, cada lista en su orden
    // (ecommerce.fn_enlace_catalogo)
    public function catalogo()
    {
        try {
            $catalogo = array_fill_keys(array_column(self::CAMPOS_CATALOGO, 0), []);
            foreach (DB::select('SELECT * FROM ecommerce.fn_enlace_catalogo()') as $fila) {
                [$lista, $campos] = self::CAMPOS_CATALOGO[$fila->tipo];
                $catalogo[$lista][] = array_intersect_key((array) $fila, array_flip($campos));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Catálogo de enlaces', $catalogo));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el catálogo de enlaces', $th->getMessage()));
        }
    }

    // POST ecommerce/enlacesDescribir  { destinos: [...] } → [{ etiqueta, ruta, disponible }] en el mismo orden
    // (ecommerce.fn_enlace_describir)
    public function describir(Request $request)
    {
        $validator = Validator::make($request->all(), ['destinos' => 'required|array|max:500']);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Envíe la lista de destinos.', $validator->errors()));
        }

        try {
            $filas = DB::select(
                'SELECT etiqueta, ruta, disponible FROM ecommerce.fn_enlace_describir(?::jsonb)',
                [json_encode(array_values($request->input('destinos')))]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Destinos', array_map(
                fn ($f) => ['etiqueta' => $f->etiqueta, 'ruta' => $f->ruta, 'disponible' => (bool) $f->disponible],
                $filas
            )));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron leer los destinos', $th->getMessage()));
        }
    }

    // GET ecommerce/enlacesOpciones?tipo=categoria|marca&pagina=&tamanio=&busqueda= → { registros: [{ id, nombre, productos }],
    // total }. Solo las que hoy tienen productos en la tienda (ecommerce.fn_enlace_opciones_listar_paginacion).
    public function opciones(Request $request)
    {
        $validator = Validator::make($request->query(), [
            'tipo'     => 'required|in:categoria,marca',
            'pagina'   => 'nullable|integer|min:1',
            'tamanio'  => 'nullable|integer|min:1|max:100',
            'busqueda' => 'nullable|string|max:100',
        ]);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos: ' . $validator->errors()->first(), $validator->errors()));
        }

        try {
            $pagina = (int) $request->query('pagina', 1);
            $tamanio = (int) $request->query('tamanio', 20);
            $registros = DB::select(
                'SELECT * FROM ecommerce.fn_enlace_opciones_listar_paginacion(?, ?, ?, ?)',
                [$request->query('tipo'), $pagina, $tamanio, trim((string) $request->query('busqueda', ''))]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Opciones del enlace', [
                'registros' => array_map(fn ($f) => ['id' => (int) $f->id, 'nombre' => $f->nombre, 'productos' => (int) $f->productos], $registros),
                'total'     => (int) ($registros[0]->total_registros ?? 0),
                'pagina'    => $pagina,
                'tamanio'   => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las opciones', $th->getMessage()));
        }
    }
}
