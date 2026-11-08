<?php

namespace App\Http\Controllers\paginaWeb;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Servicios\PaginaWeb\ClienteWebService;
use App\Servicios\PaginaWeb\ErroresPaginaWeb;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

/**
 * Clientes de la tienda (CRM): listar, ver, activar / desactivar y desbloquear. El CRM nunca ve ni cambia sus contraseñas.
 */
class ClienteWebController extends Controller
{
    public function __construct(private ClienteWebService $servicio)
    {
    }

    // GET pagina-web/clientes-web?busqueda=&pagina=1&tamanio=20   → {registros, total}
    public function listar(Request $request)
    {
        try {
            $clientes = $this->servicio->listar(
                (string) $request->input('busqueda', ''),
                (int) $request->input('pagina', 1),
                (int) $request->input('tamanio', 20)
            );

            return response()->json(RespuestaApi::returnResultado('success', 'Clientes', $clientes));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los clientes');
        }
    }

    // GET pagina-web/clientes-web/{clwId}   (sin filas → code 404 en el cuerpo)
    public function buscar(int $clwId)
    {
        try {
            $cliente = $this->servicio->buscar($clwId);

            if ($cliente === null) {
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'El cliente no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente', $cliente));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar el cliente');
        }
    }

    // PUT pagina-web/clientes-web/{clwId}/activo   {activo: true | false}  (desactivar cierra sus sesiones)
    public function cambiarActivo(Request $request, int $clwId)
    {
        try {
            $this->servicio->cambiarActivo($clwId, $request->input('activo'), auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente guardado con éxito', ['clw_id' => $clwId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el cliente');
        }
    }

    // PUT pagina-web/clientes-web/{clwId}/desbloquear
    public function desbloquear(int $clwId)
    {
        try {
            $this->servicio->desbloquear($clwId, auth()->id());

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente desbloqueado', ['clw_id' => $clwId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al desbloquear el cliente');
        }
    }

    // -----------------------------------------------------------------

    private function error(QueryException $e, string $mensajeGenerico)
    {
        return ErroresPaginaWeb::respuesta($e, $mensajeGenerico);
    }
}
