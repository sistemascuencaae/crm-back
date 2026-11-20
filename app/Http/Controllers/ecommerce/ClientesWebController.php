<?php

namespace App\Http\Controllers\ecommerce;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Clientes de la tienda web (ecommerce.clientes_web) desde el CRM: listar, ver, activar / desactivar, desbloquear y
// auditoría. El CRM nunca ve ni cambia sus contraseñas. Las reglas viven en ecommerce.fn_cliente_web_*
// (database/sql/clientes_web.sql): validan, guardan y auditan (módulo CLIENTES_WEB). Cada escritura manda
// {datos, auditoria} (estándar de Clientes).
class ClientesWebController extends Controller
{
    // Código que lanza la función PG -> mensaje para el usuario.
    private const MENSAJES_ERROR = [
        'CAMPO_INVALIDO'    => 'El valor no es válido.',
        'REQUIERE_USUARIO'  => 'No se identificó al usuario que realiza el cambio.',
        'CLIENTE_NO_EXISTE' => 'La cuenta no existe.',
    ];

    // GET ecommerce/listClientesWeb?busqueda=&pagina=1&tamanio=20   → {registros, total, pagina, tamanio}
    public function listar(Request $request)
    {
        try {
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ecommerce.fn_cliente_web_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Clientes listados con éxito', [
                'registros' => $registros,
                'total'     => (int) ($registros[0]->total_registros ?? 0),
                'pagina'    => $pagina,
                'tamanio'   => $tamanio,
            ]));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudieron listar los clientes');
        }
    }

    // GET ecommerce/getClienteWeb/{id}   (sin filas → code 404 en el cuerpo)
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

    // POST ecommerce/activoClienteWeb/{id}   {activo: true | false}  (desactivar cierra sus sesiones)
    // El valor va tal cual: si no es true / false, la base responde CAMPO_INVALIDO:clw_activo.
    public function cambiarActivo(Request $request, int $clwId)
    {
        try {
            $id = DB::selectOne('SELECT ecommerce.fn_cliente_web_cambiar_activo(?::jsonb) AS id', [json_encode([
                'clw_id'     => $clwId,
                'clw_activo' => $request->input('activo'),
                'auditoria'  => $this->contextoAuditoriaForense($request),
            ])])->id;

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente guardado con éxito', ['clw_id' => $id]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al guardar el cliente');
        }
    }

    // POST ecommerce/desbloquearClienteWeb/{id}
    public function desbloquear(Request $request, int $clwId)
    {
        try {
            $id = DB::selectOne('SELECT ecommerce.fn_cliente_web_desbloquear(?::jsonb) AS id', [json_encode([
                'clw_id'    => $clwId,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])])->id;

            return response()->json(RespuestaApi::returnResultado('success', 'Cliente desbloqueado', ['clw_id' => $id]));
        } catch (QueryException $e) {
            return $this->error($e, 'Error al desbloquear el cliente');
        }
    }

    // GET ecommerce/clienteWebAuditoria?clw_id=&pagina=1&tamanio=10&busqueda=
    // Resumen e historial paginado con la foto completa (sin la huella de la contraseña).
    public function auditoria(Request $request)
    {
        try {
            $clwId    = (int) $request->query('clw_id', 0);
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = min(max((int) $request->query('tamanio', 10), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($clwId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Cliente no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ecommerce.fn_cliente_web_auditoria_resumen(?)', [$clwId]);
            $eventos = DB::select('SELECT * FROM ecommerce.fn_cliente_web_auditoria_listar_paginacion(?, ?, ?, ?)', [$clwId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) ($eventos[0]->total_registros ?? 0),
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (QueryException $e) {
            return $this->error($e, 'No se pudo cargar la auditoría del cliente');
        }
    }

    // -----------------------------------------------------------------

    // Contexto de auditoría (estándar de Clientes): el usuario del CRM que hace el cambio. Sin sesión, usuario_id va
    // vacío y la base responde REQUIERE_USUARIO.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id'     => $u->id ?? null,
            'usuario_login'  => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address'     => $request->ip(),
            'user_agent'     => $request->userAgent(),
            'request_id'     => (string) Str::uuid(),
        ];
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
