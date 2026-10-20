<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\InicioSeccionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Interruptores de las secciones del inicio (CRM): mostrar u ocultar una sección completa.
 */
class InicioSeccionController extends Controller
{
    public function __construct(private InicioSeccionService $servicio)
    {
    }

    // GET pagina-web/inicio-secciones
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Secciones del inicio', $this->servicio->listar()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las secciones del inicio');
        }
    }

    // PUT pagina-web/inicio-secciones/{clave}   {ise_activo: true|false}
    public function modificar(Request $request, string $clave)
    {
        try {
            $this->servicio->modificar($clave, ['ise_activo' => $request->input('ise_activo'), 'usu_id' => auth()->id()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Sección guardada con éxito', ['ise_clave' => $clave]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar la sección');
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