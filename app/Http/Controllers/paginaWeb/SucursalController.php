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
 * Sucursales de la página "Nuestras tiendas" (CRM).
 * Validación ligera aquí; las reglas de negocio están en ecommerce.fn_sucursal_validaciones.
 */
class SucursalController extends Controller
{
    private const CAMPOS = [
        'sci_id',
        'suc_nombre',
        'suc_direccion',
        'suc_horario',
        'suc_telefono',
        'suc_latitud',
        'suc_longitud',
        'suc_activo',
    ];

    public function __construct(private SucursalService $servicio)
    {
    }

    /** Ciudades con sus sucursales, incluidas las inactivas. */
    public function listar()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Sucursales listadas con éxito', $this->servicio->listarAdmin()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar las sucursales');
        }
    }

    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sci_id'        => 'required|integer',
            'suc_nombre'    => 'required|string',
            'suc_direccion' => 'required|string',
            'suc_activo'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $sucId = $this->servicio->crear($this->payload($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal creada con éxito', ['suc_id' => $sucId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al crear la sucursal');
        }
    }

    // Acepta solo los campos que cambian. Para borrar un campo opcional, el CRM envía null.
    public function modificar(Request $request, int $sucId)
    {
        $validator = Validator::make($request->all(), [
            'sci_id'        => 'sometimes|integer',
            'suc_nombre'    => 'sometimes|string',
            'suc_direccion' => 'sometimes|string',
            'suc_activo'    => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->modificar($sucId, $this->payload($request));

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal modificada con éxito', ['suc_id' => $sucId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al modificar la sucursal');
        }
    }

    // DELETE pagina-web/sucursales/{sucId}   (a la papelera)
    public function eliminar(int $sucId)
    {
        try {
            $this->servicio->eliminar($sucId, $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal enviada a la papelera', ['suc_id' => $sucId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al eliminar la sucursal');
        }
    }

    // PUT pagina-web/sucursales/{sucId}/restaurar
    public function restaurar(int $sucId)
    {
        try {
            $this->servicio->restaurar($sucId, $this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Sucursal restaurada con éxito', ['suc_id' => $sucId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al restaurar la sucursal');
        }
    }

    // GET pagina-web/sucursales-papelera   (ciudades y sucursales)
    public function papelera()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Papelera', $this->servicio->papelera()));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la papelera');
        }
    }

    // DELETE pagina-web/sucursales-papelera   → {cantidad}
    public function vaciar()
    {
        try {
            $cantidad = $this->servicio->vaciar($this->usuarioId());

            return response()->json(RespuestaApi::returnResultado('success', 'Papelera vaciada', ['cantidad' => $cantidad]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al vaciar la papelera');
        }
    }

    /** Body: {"sci_id": 1, "ids": [5, 3, 4]} con todas las sucursales de esa ciudad en el nuevo orden. */
    public function ordenar(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'sci_id' => 'required|integer',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        if ($validator->fails()) {
            return response()->json(RespuestaApi::returnResultado('error', 'Validación de datos', $validator->errors()));
        }

        try {
            $this->servicio->ordenar([
                'sci_id' => $request->input('sci_id'),
                'ids'    => $request->input('ids'),
                'usu_id' => $this->usuarioId(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Orden de sucursales actualizado', null));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al ordenar las sucursales');
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