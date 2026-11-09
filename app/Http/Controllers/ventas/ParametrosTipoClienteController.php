<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Tipos de cliente por rango de score (ventas.parametros_tipo_cliente), pestaña de Parámetros tipo calificación cliente.
// El negocio vive en ventas.fn_parametros_tipo_cliente_* (PLANES/COTIZADOR): este controller arma el jsonb con 'auditoria'
// y traduce el código que levanta la función.
class ParametrosTipoClienteController extends Controller
{
    private const CAMPOS_FILA = [
        'id',
        'nombre',
        'tipo',
        'score_minimo',
        'score_maximo'
    ];

    // Mensaje amigable para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'NOMBRE_MUY_LARGO' => 'El nombre no puede pasar de 60 caracteres.',
        'REQUIERE_TIPO' => 'El tipo es obligatorio.',
        'TIPO_INVALIDO' => 'El tipo no es válido.',
        'SCORE_INVALIDO' => 'El score mínimo y el máximo deben ser números enteros.',
        'SCORE_RANGO_INVERTIDO' => 'El score mínimo no puede ser mayor que el máximo.',
        'TIPO_CLIENTE_DUPLICADO' => 'Ya existe una fila con ese nombre.',
        'TIPO_CLIENTE_NO_EXISTE' => 'La fila ya no existe.',
        'PARAMETRO_NO_CONFIGURADO' => 'Faltan los límites del score en crm.parametro.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listParametrosTipoCliente?pagina&tamanio&busqueda — el grid de la pestaña.
    public function listParametrosTipoCliente(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_parametros_tipo_cliente_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los tipos de cliente', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listTiposParametroTipoCliente — el combo Tipo (codigo, nombre), una sola fuente con la validación de la función.
    public function listTiposParametroTipoCliente()
    {
        try {
            $data = DB::select('SELECT * FROM ventas.fn_parametros_tipo_cliente_tipos_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los tipos', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarParametrosTipoCliente — las filas nuevas (sin id) y las editadas, todo o nada.
    public function guardarParametrosTipoCliente(Request $request)
    {
        try {
            $filas = array_map(fn($fila) => Arr::only((array) $fila, self::CAMPOS_FILA), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_tipo_cliente_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar los tipos de cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar los tipos de cliente', $e->getMessage()));
        }
    }

    // Version 1.0
    // DELETE /api/ventas/deleteParametroTipoCliente/{id}
    public function deleteParametroTipoCliente(Request $request, $id)
    {
        try {
            DB::selectOne('SELECT ventas.fn_parametros_tipo_cliente_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el tipo de cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el tipo de cliente', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/parametroTipoClienteAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA fila
    // (auditoria.logs_cambios).
    public function parametroTipoClienteAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_parametros_tipo_cliente_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_parametros_tipo_cliente_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del tipo de cliente', $e->getMessage()));
        }
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico. Los límites del score y los rangos que chocan solo los conoce la función: ella arma ese texto.
    private function respuestaError(QueryException $e, string $generico)
    {
        if (preg_match('/(?:SCORE_FUERA_DE_LIMITES|RANGO_CRUZADO): (.+?)\s*(?:\R|\(SQL:|$)/', $e->getMessage(), $texto)) {
            return response()->json(RespuestaApi::returnResultado('error', $texto[1], null));
        }

        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (strpos($e->getMessage(), $codigo) !== false) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios.
    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}
