<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Clientes de la tienda web (ecommerce.clientes_web) desde el CRM: listar, ver, activar / desactivar y desbloquear.
// El CRM nunca ve ni cambia sus contraseñas. Las reglas viven en ecommerce.fn_cliente_web_*.
// Reemplaza a paginaWeb/ClienteWebController.
class ClientesWebController extends Controller
{
    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'CAMPO_INVALIDO'    => 'El valor no es válido.',
        'REQUIERE_USUARIO'  => 'No se identificó al usuario que realiza el cambio.',
        'CLIENTE_NO_EXISTE' => 'La cuenta no existe.',
    ];

    // GET pagina-web/clientes-web?busqueda=&pagina=1&tamanio=20   → {registros, total}
    public function listar(Request $request)
    {
        try {
            $filas = DB::select('SELECT * FROM ecommerce.fn_cliente_web_listar(?, ?, ?)', [
                (string) $request->input('busqueda', ''),
                (int) $request->input('pagina', 1),
                (int) $request->input('tamanio', 20),
            ]);

            // El total no se repite en cada registro
            $total = (int) ($filas[0]->total ?? 0);
            foreach ($filas as $fila) {
                unset($fila->total);
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Clientes', ['registros' => $filas, 'total' => $total]));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los clientes');
        }
    }

    // GET pagina-web/clientes-web/{clwId}   (sin filas → code 404 en el cuerpo)
    public function buscar(int $clwId)
    {
        try {
            $cliente = DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_buscar(?)', [$clwId]);

            if ($cliente === null) {
                return response()->json(['code' => 404] + RespuestaApi::returnResultado('error', 'El cliente no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente', $cliente));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar el cliente');
        }
    }

    // PUT pagina-web/clientes-web/{clwId}/activo   {activo: true | false}  (desactivar cierra sus sesiones)
    // Otra cosa que no sea true / false llega como null → CAMPO_INVALIDO de la base
    public function cambiarActivo(Request $request, int $clwId)
    {
        try {
            $activo = $request->input('activo');
            DB::statement('SELECT ecommerce.fn_cliente_web_cambiar_activo(?, ?::boolean, ?)', [
                $clwId, is_bool($activo) ? ($activo ? 'true' : 'false') : null, $this->usuario(),
            ]);

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente guardado con éxito', ['clw_id' => $clwId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el cliente');
        }
    }

    // PUT pagina-web/clientes-web/{clwId}/desbloquear
    public function desbloquear(int $clwId)
    {
        try {
            DB::statement('SELECT ecommerce.fn_cliente_web_desbloquear(?, ?)', [$clwId, $this->usuario()]);

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente desbloqueado', ['clw_id' => $clwId]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al desbloquear el cliente');
        }
    }

    // -----------------------------------------------------------------

    // Usuario del CRM que hace el cambio (sin sesión → TypeError, igual que antes)
    private function usuario(): int
    {
        return auth()->id();
    }

    // Código conocido → su mensaje; si trae campo, data = {campo: mensaje} y el CRM lo pinta debajo del campo.
    // Desconocido → mensaje genérico (el detalle queda en el log).
    // El código viene después de "ERROR:", por ejemplo:  ERROR:  CAMPO_INVALIDO:clw_activo
    private function error(QueryException $e, string $mensajeGenerico)
    {
        if (preg_match('/ERROR:\s+([A-Z_]+)(?::([a-z0-9_.]+))?(?::(\d+))?/', $e->getMessage(), $m)
            && isset(self::MENSAJES_ERROR[$m[1]])) {
            $mensaje = sprintf(self::MENSAJES_ERROR[$m[1]], $m[3] ?? '');
            $campo   = $m[2] ?? '';

            if ($campo !== '') {
                return response()->json(RespuestaApi::returnResultado('error', 'Revisa los datos marcados.', [$campo => $mensaje]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
        }

        Log::error($mensajeGenerico, ['error' => $e->getMessage()]);

        return response()->json(RespuestaApi::returnResultado('error', $mensajeGenerico, null));
    }
}
