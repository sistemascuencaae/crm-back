<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EncabezadoService;
use App\Servicios\PaginaWeb\ErroresEncabezadoPie;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Header de la tienda (CRM): configuración y enlaces del menú.
 * Validación ligera aquí; las reglas están en la base.
 */
class EncabezadoController extends Controller
{
    // Lo único que se acepta de un enlace
    private const CAMPOS_ENLACE = ['een_texto', 'een_enlace', 'een_icono', 'een_activo', 'een_nueva_pestana'];

    public function __construct(private EncabezadoService $servicio)
    {
    }

    // GET pagina-web/encabezado → {config, enlaces}
    public function buscar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Encabezado', $this->servicio->buscar()));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'No se pudo cargar el encabezado');
        }
    }

    // PUT pagina-web/encabezado   {config}
    public function modificar(Request $request)
    {
        if (!is_array($request->input('config'))) {
            return response()->json(RespuestaApi::returnResultado('error', 'La configuración no es válida.', null));
        }

        try {
            $this->servicio->modificar(['config' => $request->input('config'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Encabezado guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'Error al guardar el encabezado');
        }
    }

    // POST pagina-web/encabezado/enlaces
    public function crearEnlace(Request $request)
    {
        try {
            $eenId = $this->servicio->crearEnlace($request->only(self::CAMPOS_ENLACE) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace creado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'Error al crear el enlace');
        }
    }

    // PUT pagina-web/encabezado/enlaces/{eenId}   (solo lo que cambia)
    public function modificarEnlace(Request $request, int $eenId)
    {
        try {
            $this->servicio->modificarEnlace($eenId, $request->only(self::CAMPOS_ENLACE) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace guardado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'Error al guardar el enlace');
        }
    }

    // DELETE pagina-web/encabezado/enlaces/{eenId}
    public function eliminarEnlace(int $eenId)
    {
        try {
            $this->servicio->eliminarEnlace($eenId);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace eliminado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'Error al eliminar el enlace');
        }
    }

    // PUT pagina-web/encabezado/enlaces-orden   {ids: [3, 1, 2]} (todos, en el nuevo orden)
    public function ordenarEnlaces(Request $request)
    {
        try {
            $this->servicio->ordenarEnlaces(['ids' => $request->input('ids'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresEncabezadoPie::respuesta($e, 'Error al guardar el orden');
        }
    }
}