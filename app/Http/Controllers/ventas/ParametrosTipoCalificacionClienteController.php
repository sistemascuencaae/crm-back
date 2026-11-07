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

// Parámetros tipo calificación cliente (ventas.parametros_tipo_calificacion_cliente): los valores que recibe
// ventas.fn_score_cliente_calificacion. El negocio vive en ventas.fn_parametros_tipo_calificacion_cliente_*
// (PLANES/COTIZADOR): este controller arma el jsonb con 'auditoria' y traduce el código que levanta la función.
class ParametrosTipoCalificacionClienteController extends Controller
{
    private const CAMPOS_FILA = [
        'id',
        'nombre',
        'estado_credito',
        'meses_factura',
        'dias_atraso_oro',
        'dias_atraso_plata',
        'dias_atraso_bronce',
        'porcentaje_oro',
        'porcentaje_plata',
        'porcentaje_bronce'
    ];

    // Mensaje amigable para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'REQUIERE_ESTADO_CREDITO' => 'El estado de crédito es obligatorio.',
        'ESTADO_CREDITO_INVALIDO' => 'El estado de crédito no es válido.',
        'NUMERO_ENTERO_INVALIDO' => 'Los meses y los días deben ser números enteros, de 0 en adelante.',
        'DIAS_PLATA_INVALIDOS' => 'Los días de plata deben ser los días de oro + 1.',
        'DIAS_BRONCE_INVALIDOS' => 'Los días de bronce deben ser mayores que los de plata.',
        'PORCENTAJE_INVALIDO' => 'Los porcentajes deben estar entre 0 y 100.',
        'PARAMETRO_DUPLICADO' => 'Ya existe una fila con ese nombre y estado de crédito.',
        'PARAMETRO_NO_EXISTE' => 'La fila ya no existe.',
        'PARAMETRO_EN_USO' => 'Esta fila la usa un requerimiento de comportamiento: cambie primero ese requerimiento.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listParametrosTipoCalificacionCliente?pagina&tamanio&busqueda — el grid de la pantalla.
    public function listParametrosTipoCalificacionCliente(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_parametros_tipo_calificacion_cliente_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los parámetros', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listEstadosCreditoTipoCalificacionCliente — el combo Estado crédito (codigo, nombre), una sola
    // fuente con la validación de la función.
    public function listEstadosCreditoTipoCalificacionCliente()
    {
        try {
            $data = DB::select('SELECT * FROM ventas.fn_parametros_tipo_calificacion_cliente_estados_credito_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los estados de crédito', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarParametrosTipoCalificacionCliente — las filas nuevas (sin id) y las editadas, todo o nada.
    public function guardarParametrosTipoCalificacionCliente(Request $request)
    {
        try {
            $filas = array_map(fn($fila) => Arr::only((array) $fila, self::CAMPOS_FILA), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_tipo_calificacion_cliente_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar los parámetros');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar los parámetros', $e->getMessage()));
        }
    }

    // Version 1.0
    // DELETE /api/ventas/deleteParametroTipoCalificacionCliente/{id}
    public function deleteParametroTipoCalificacionCliente(Request $request, $id)
    {
        try {
            DB::selectOne('SELECT ventas.fn_parametros_tipo_calificacion_cliente_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ])]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el parámetro');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el parámetro', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/parametroTipoCalificacionClienteAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado
    // de UNA fila (auditoria.logs_cambios).
    public function parametroTipoCalificacionClienteAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_parametros_tipo_calificacion_cliente_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_parametros_tipo_calificacion_cliente_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del parámetro', $e->getMessage()));
        }
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico (como CalificacionColorController).
    private function respuestaError(QueryException $e, string $generico)
    {
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
