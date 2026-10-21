<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\PiePaginaService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Footer de la tienda (CRM): configuración, columnas y enlaces de cada columna.
 * Validación ligera aquí; las reglas están en la base.
 */
class PiePaginaController extends Controller
{
    // Lo único que se acepta de una columna y de un enlace
    private const CAMPOS_COLUMNA = ['pco_titulo', 'pco_activo'];
    private const CAMPOS_ENLACE  = ['pen_texto', 'pen_enlace', 'pen_icono', 'pen_activo', 'pen_nueva_pestana'];

    public function __construct(private PiePaginaService $servicio)
    {
    }

    // GET pagina-web/pie-pagina → {config, columnas: [{…, enlaces: [...]}]}
    public function buscar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Pie de página', $this->servicio->buscar()));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'No se pudo cargar el pie de página');
        }
    }

    // PUT pagina-web/pie-pagina   {config}
    public function modificar(Request $request)
    {
        if (!is_array($request->input('config'))) {
            return response()->json(RespuestaApi::returnResultado('error', 'La configuración no es válida.', null));
        }

        try {
            $this->servicio->modificar(['config' => $request->input('config'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Pie de página guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el pie de página');
        }
    }

    // ---------- Columnas ----------

    // POST pagina-web/pie-pagina/columnas
    public function crearColumna(Request $request)
    {
        try {
            $pcoId = $this->servicio->crearColumna($request->only(self::CAMPOS_COLUMNA) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Columna creada con éxito', ['pco_id' => $pcoId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al crear la columna');
        }
    }

    // PUT pagina-web/pie-pagina/columnas/{pcoId}   (solo lo que cambia)
    public function modificarColumna(Request $request, int $pcoId)
    {
        try {
            $this->servicio->modificarColumna($pcoId, $request->only(self::CAMPOS_COLUMNA) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Columna guardada con éxito', ['pco_id' => $pcoId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar la columna');
        }
    }

    // DELETE pagina-web/pie-pagina/columnas/{pcoId}   (también elimina sus enlaces)
    public function eliminarColumna(int $pcoId)
    {
        try {
            $this->servicio->eliminarColumna($pcoId);

            return response()->json(RespuestaApi::returnResultado('success', 'Columna eliminada con éxito', ['pco_id' => $pcoId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al eliminar la columna');
        }
    }

    // PUT pagina-web/pie-pagina/columnas-orden   {ids: [3, 1, 2]} (todas, en el nuevo orden)
    public function ordenarColumnas(Request $request)
    {
        try {
            $this->servicio->ordenarColumnas(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el orden');
        }
    }

    // ---------- Enlaces de una columna ----------

    // POST pagina-web/pie-pagina/columnas/{pcoId}/enlaces
    public function crearEnlace(Request $request, int $pcoId)
    {
        try {
            $penId = $this->servicio->crearEnlace(
                $request->only(self::CAMPOS_ENLACE) + ['pco_id' => $pcoId, 'usu_id' => auth()->id()]
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace creado con éxito', ['pen_id' => $penId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al crear el enlace');
        }
    }

    // PUT pagina-web/pie-pagina/enlaces/{penId}   (solo lo que cambia)
    public function modificarEnlace(Request $request, int $penId)
    {
        try {
            $this->servicio->modificarEnlace($penId, $request->only(self::CAMPOS_ENLACE) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace guardado con éxito', ['pen_id' => $penId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el enlace');
        }
    }

    // DELETE pagina-web/pie-pagina/enlaces/{penId}
    public function eliminarEnlace(int $penId)
    {
        try {
            $this->servicio->eliminarEnlace($penId);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace eliminado con éxito', ['pen_id' => $penId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al eliminar el enlace');
        }
    }

    // PUT pagina-web/pie-pagina/columnas/{pcoId}/enlaces-orden   {ids: [7, 5, 6]} (todos los de la columna)
    public function ordenarEnlaces(Request $request, int $pcoId)
    {
        try {
            $this->servicio->ordenarEnlaces(['pco_id' => $pcoId, 'ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el orden');
        }
    }
}