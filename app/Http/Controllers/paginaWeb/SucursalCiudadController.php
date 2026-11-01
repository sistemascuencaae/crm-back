<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use App\Servicios\PaginaWeb\SucursalService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Ciudades de la página "Nuestras tiendas" (CRM).
 * Validación ligera aquí; las reglas de negocio están en ecommerce.fn_sucursal_ciudad_validaciones.
 */
class SucursalCiudadController extends Controller
{
    private const CAMPOS = ['sci_nombre', 'sci_activo'];

    public function __construct(private SucursalService $servicio)
    {
    }

    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sci_nombre' => 'required|string',
            'sci_activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $sciId = $this->servicio->crearCiudad($this->payload($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad creada con éxito', ['sci_id' => $sciId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la ciudad');
        }
    }

    // Acepta solo los campos que cambian (por ejemplo, solo sci_activo desde el interruptor)
    public function modificar(Request $request, int $sciId)
    {
        $validator = Validator::make($request->all(), [
            'sci_nombre' => 'sometimes|string',
            'sci_activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->modificarCiudad($sciId, $this->payload($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad modificada con éxito', ['sci_id' => $sciId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al modificar la ciudad');
        }
    }

    // DELETE pagina-web/sucursales/ciudades/{sciId}   (a la papelera)
    public function eliminar(int $sciId)
    {
        try {
            $this->servicio->eliminarCiudad($sciId, $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad enviada a la papelera', ['sci_id' => $sciId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la ciudad');
        }
    }

    // PUT pagina-web/sucursales/ciudades/{sciId}/restaurar
    public function restaurar(int $sciId)
    {
        try {
            $this->servicio->restaurarCiudad($sciId, $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Ciudad restaurada con éxito', ['sci_id' => $sciId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la ciudad');
        }
    }

    /** Body: {"ids": [3, 1, 2]} con todas las ciudades en el nuevo orden. */
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->ordenarCiudades([
                'ids'    => $request->input('ids'),
                'usu_id' => $this->usuarioId(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden de ciudades actualizado', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al ordenar las ciudades');
        }
    }

    // -----------------------------------------------------------------

    private function payload(Request $request): array
    {
        return $request->only(self::CAMPOS) + ['usu_id' => $this->usuarioId()];
    }

    /** Código conocido → su mensaje (arriba, como siempre). Desconocido → mensaje genérico; el detalle queda en el log. */
    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico, SucursalService::MENSAJES_ERROR, false);
    }

    /** Clave primaria del usuario autenticado (usu_id). */
    private function usuarioId(): ?int
    {
        return auth()->id();
    }
}