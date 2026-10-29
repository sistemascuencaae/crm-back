<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\BloqueProductoService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Bloques destacados del inicio (CRM): cada uno con su categoría principal, título y lado de la imagen.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class BloqueProductoController extends Controller
{
    // Lo único que se acepta de un bloque (bpr_desde / bpr_hasta: "YYYY-MM-DDTHH:MM", hora de Ecuador, o null)
    private const CAMPOS = ['bpr_titulo', 'tpr_id', 'bpr_activo', 'bpr_imagen_lado', 'bpr_desde', 'bpr_hasta'];

    public function __construct(private BloqueProductoService $servicio)
    {
    }

    // GET pagina-web/bloques-productos
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Bloques', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los bloques');
        }
    }

    // POST pagina-web/bloques-productos   {bpr_titulo, tpr_id, bpr_activo, bpr_imagen_lado, bpr_desde, bpr_hasta}
    public function crear(Request $request)
    {
        try {
            $bprId = $this->servicio->crear($request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque creado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear el bloque');
        }
    }

    // PUT pagina-web/bloques-productos/{bprId}   (solo lo que cambia)
    public function modificar(Request $request, int $bprId)
    {
        try {
            $this->servicio->modificar($bprId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque guardado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el bloque');
        }
    }

    // DELETE pagina-web/bloques-productos/{bprId}   (a la papelera)
    public function eliminar(int $bprId)
    {
        try {
            $this->servicio->eliminar($bprId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque enviado a la papelera', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar el bloque');
        }
    }

    // GET pagina-web/bloques-productos-papelera
    public function papelera()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papelera()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // PUT pagina-web/bloques-productos/{bprId}/restaurar
    public function restaurar(int $bprId)
    {
        try {
            $this->servicio->restaurar($bprId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Bloque restaurado con éxito', ['bpr_id' => $bprId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar el bloque');
        }
    }

    // DELETE pagina-web/bloques-productos-papelera   → {cantidad}
    public function vaciar()
    {
        try {
            $cantidad = $this->servicio->vaciar(auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // PUT pagina-web/bloques-productos-orden   {ids: [3, 1, 2]} (todos los bloques, en el nuevo orden)
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

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, BloqueProductoService::MENSAJES_ERROR);
    }
}
