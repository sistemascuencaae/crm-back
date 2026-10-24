<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\EncabezadoService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
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
            return ErroresPaginaWeb::respuesta($e, 'No se pudo cargar el encabezado');
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
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el encabezado');
        }
    }

    // POST pagina-web/encabezado/enlaces   (con een_padre_id es un sub-enlace de ese principal)
    public function crearEnlace(Request $request)
    {
        try {
            $datos = $request->only(self::CAMPOS_ENLACE) + ['een_padre_id' => $request->input('een_padre_id'), 'usu_id' => auth()->id()];
            $eenId = $this->servicio->crearEnlace($datos);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace creado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al crear el enlace');
        }
    }

    // PUT pagina-web/encabezado/enlaces/{eenId}   (solo lo que cambia; no cambia de padre)
    public function modificarEnlace(Request $request, int $eenId)
    {
        try {
            $this->servicio->modificarEnlace($eenId, $request->only(self::CAMPOS_ENLACE) + ['usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace guardado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el enlace');
        }
    }

    // DELETE pagina-web/encabezado/enlaces/{eenId}   (un principal se lleva sus sub-enlaces)
    public function eliminarEnlace(int $eenId)
    {
        try {
            $this->servicio->eliminarEnlace($eenId);

            return response()->json(RespuestaApi::returnResultado('success', 'Enlace eliminado con éxito', ['een_id' => $eenId]));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al eliminar el enlace');
        }
    }

    // PUT pagina-web/encabezado/enlaces-orden
    //   {een_padre_id: null, ids: [3, 1, 2]}  → los principales
    //   {een_padre_id: 8,    ids: [10, 9]}    → los sub-enlaces del principal 8
    // Siempre todos los del grupo, en el nuevo orden.
    public function ordenarEnlaces(Request $request)
    {
        try {
            $this->servicio->ordenarEnlaces([
                'een_padre_id' => $request->input('een_padre_id'),
                'ids'          => $request->input('ids'),
                'usu_id'       => auth()->id(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden guardado con éxito', null));
        } catch (QueryException $e) {
            return ErroresPaginaWeb::respuesta($e, 'Error al guardar el orden');
        }
    }
}