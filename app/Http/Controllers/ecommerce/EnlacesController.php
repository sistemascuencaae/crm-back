<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// El selector de enlace del CRM (headers, footers y secciones de páginas): qué se puede elegir y cómo se llama cada
// destino ya elegido. Solo lectura. Las reglas viven en ecommerce.fn_enlace_catalogo y ecommerce.fn_enlace_describir
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
}
