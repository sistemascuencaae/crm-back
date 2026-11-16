<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EnlacesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

// El selector de enlace del CRM (headers, footers y secciones de páginas): qué se puede elegir y cómo se llama cada
// destino ya elegido. Solo lectura.
class EnlacesController extends Controller
{
    public function __construct(private EnlacesService $enlaces)
    {
        $this->middleware('auth:api');
    }

    // Páginas, documentos, carpetas de nivel 1, pantallas de la tienda y grupos. GET ecommerce/enlacesCatalogo
    public function catalogo()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Catálogo de enlaces', $this->enlaces->catalogo()));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar el catálogo de enlaces', $th->getMessage()));
        }
    }

    // { destinos: [...] } → [{ etiqueta, ruta, disponible }] en el mismo orden. POST ecommerce/enlacesDescribir
    public function describir(Request $request)
    {
        $validator = Validator::make($request->all(), ['destinos' => 'required|array|max:500']);
        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Envíe la lista de destinos.', $validator->errors()));
        }

        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Destinos', $this->enlaces->describir($request->input('destinos'))));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron leer los destinos', $th->getMessage()));
        }
    }
}
