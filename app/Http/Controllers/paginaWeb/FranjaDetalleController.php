<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\FranjaDetalleService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Franjas del detalle del producto (CRM): hasta 5, con su orden, vigencia y papelera.
 * Validación ligera aquí; las reglas están en la base. Los errores de un campo llegan como data {campo: mensaje}.
 */
class FranjaDetalleController extends Controller
{
    // Lo único que se acepta (fde_tipo: cinta | fija; fechas: "YYYY-MM-DDTHH:MM", hora de Ecuador, o null)
    private const CAMPOS = ['fde_tipo', 'fde_texto', 'fde_texto_resaltado', 'fde_icono', 'fde_logos', 'fde_nota',
                            'fde_activo', 'fde_desde', 'fde_hasta'];

    public function __construct(private FranjaDetalleService $servicio)
    {
    }

    // GET pagina-web/franjas-detalle
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Franjas', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las franjas');
        }
    }

    // POST pagina-web/franjas-detalle
    public function crear(Request $request)
    {
        try {
            $fdeId = $this->servicio->crear($request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Franja creada con éxito', ['fde_id' => $fdeId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la franja');
        }
    }

    // PUT pagina-web/franjas-detalle/{fdeId}   (solo lo que cambia)
    public function modificar(Request $request, int $fdeId)
    {
        try {
            $this->servicio->modificar($fdeId, $request->only(self::CAMPOS) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Franja guardada con éxito', ['fde_id' => $fdeId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la franja');
        }
    }

    // PUT pagina-web/franjas-detalle-orden   {ids: [3, 1, 2]} (todas, en el nuevo orden)
    public function ordenar(Request $request)
    {
        try {
            $this->servicio->ordenar(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el orden');
        }
    }

    // DELETE pagina-web/franjas-detalle/{fdeId}   (a la papelera)
    public function eliminar(int $fdeId)
    {
        try {
            $this->servicio->eliminar($fdeId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Franja enviada a la papelera', ['fde_id' => $fdeId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la franja');
        }
    }

    // PUT pagina-web/franjas-detalle/{fdeId}/restaurar
    public function restaurar(int $fdeId)
    {
        try {
            $this->servicio->restaurar($fdeId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Franja restaurada con éxito', ['fde_id' => $fdeId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la franja');
        }
    }

    // GET pagina-web/franjas-detalle-papelera
    public function papelera()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papelera()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // DELETE pagina-web/franjas-detalle-papelera   → {cantidad}
    public function vaciar()
    {
        try {
            $cantidad = $this->servicio->vaciar(auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico);
    }
}
