<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\CategoriaWebService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Categorías de la tienda (CRM): listar las del ERP, mostrar u ocultar departamentos en la tienda,
 * marcar destacadas, imagen, nombre visible y orden.
 * La imagen se sube antes con POST pagina-web/imagenes, que devuelve su URL.
 * Validación ligera aquí; las reglas están en la base.
 */
class CategoriaWebController extends Controller
{
    // Lo único que se acepta al modificar
    private const CAMPOS = ['cwe_destacada', 'cwe_imagen', 'cwe_nombre', 'cwe_visible'];

    public function __construct(private CategoriaWebService $servicio)
    {
    }

    // GET pagina-web/categorias-web
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Categorías listadas con éxito', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las categorías');
        }
    }

    // PUT pagina-web/categorias-web/{tprId}   (solo lo que cambia)
    public function modificar(Request $request, int $tprId)
    {
        try {
            $this->servicio->modificar($tprId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Categoría guardada con éxito', ['tpr_id' => $tprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la categoría');
        }
    }

    // PUT pagina-web/categorias-web-orden   {ids: [212, 228, 301]} (todas las destacadas, en el nuevo orden)
    public function ordenar(Request $request)
    {
        try {
            $this->servicio->ordenar(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // -----------------------------------------------------------------

    /** Código conocido → su mensaje. Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        $mensaje = $this->servicio->mensajeError($e);

        if ($mensaje === null) {
            Log::error($mensajeGenerico, ['error' => $e->getMessage()]);
            $mensaje = $mensajeGenerico;
        }

        return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
    }
}