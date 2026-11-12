<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\ComportamientoClienteParametro;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizador > Parámetros tipo calificación cliente, pestaña Comportamiento (ventas.comportamiento_cliente_parametros).
// El negocio vive en ventas.fn_comportamiento_cliente_parametros_* (PLANES/COTIZADOR/produccion_comportamiento_cliente_parametros.sql):
// este controller arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
// 11-nov-2026 (activo, patrón de la pestaña 2): lo guardado ya no se borra (se desactiva con el check Activo y el Guardar de
// siempre): deleteComportamientoClienteParametro quedó comentado. Nueva la auditoría de TODA la pestaña
// (comportamientoClienteParametrosAuditoria), además de la de una fila. Solo UNA fila puede estar activa y, si hay filas,
// tiene que quedar una: lo valida la función (SOLO_UN_ACTIVO y REQUIERE_UN_ACTIVO).
class ComportamientoClienteParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'REQUIERE_ESTADO_CREDITO' => 'El estado de crédito es obligatorio.',
        'ESTADO_CREDITO_INVALIDO' => 'El estado de crédito no es válido.',
        'NUMERO_ENTERO_INVALIDO' => 'Los meses, las cuotas y los días deben ser números enteros, de 0 en adelante.',
        'PARTICIPACION_REC_INVALIDA' => 'El % de cuotas pagadas con REC debe ser un número entero entre 0 y 100.',
        'DIAS_PLATA_INVALIDOS' => 'Los días de plata deben ser los días de oro + 1.',
        'DIAS_BRONCE_INVALIDOS' => 'Los días de bronce deben ser mayores que los de plata.',
        'PORCENTAJE_INVALIDO' => 'Los porcentajes deben estar entre 0 y 100.',
        'PARAMETRO_DUPLICADO' => 'Ya existe una fila con ese nombre y estado de crédito.',
        'PARAMETRO_NO_EXISTE' => 'La fila ya no existe.',
        'PARAMETRO_EN_USO' => 'Esta fila la usa un requerimiento de comportamiento: cambie primero ese requerimiento.',
        // 11-nov-2026 (activo): activo y la regla de una sola fila activa.
        'ACTIVO_INVALIDO' => 'Activo solo acepta sí o no.',
        'SOLO_UN_ACTIVO' => 'Solo puede haber un parámetro de comportamiento activo.',
        'REQUIERE_UN_ACTIVO' => 'Tiene que quedar un parámetro de comportamiento activo: para cambiarlo, marque otro como activo.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.1
    // GET /api/ventas/listComportamientoClienteParametros?pagina&tamanio&busqueda — la grilla de la pantalla.
    // 11-nov-2026 (activo): trae también las filas inactivas (se reactivan en la grilla), con activo, y hay_activa: si la tabla
    // ya tiene su fila activa (aunque la búsqueda o la página no la muestren), para que una fila nueva nazca inactiva.
    public function listComportamientoClienteParametros(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            $hayActiva = DB::selectOne('SELECT EXISTS (SELECT 1 FROM ventas.comportamiento_cliente_parametros WHERE activo) AS hay');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
                'hay_activa' => (bool) ($hayActiva->hay ?? false),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los parámetros', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listEstadosCreditoComportamientoClienteParametros — el combo Estado crédito (codigo, nombre), una sola
    // fuente con la validación de la función.
    public function listEstadosCreditoComportamientoClienteParametros()
    {
        try {
            $data = DB::select('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_estados_credito_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los estados de crédito', $e->getMessage()));
        }
    }

    // Version 1.1
    // POST /api/ventas/guardarComportamientoClienteParametros — las filas nuevas (sin id) y las editadas, todo o nada.
    // De cada fila pasan solo el id y los campos del modelo.
    // 11-nov-2026 (activo): antes, el Validator revisa que activo sea verdadero o falso (el mismo mensaje de la función); lo
    // demás (una sola activa, que quede una) lo valida la función. Cambiar la activa = guardar la de antes con activo en
    // false y la nueva en true en el mismo Guardar (la función aplica primero las desactivaciones).
    public function guardarComportamientoClienteParametros(Request $request)
    {
        try {
            $validator = Validator::make($request->only('filas'), $this->reglasFilas(), $this->mensajesFilas());
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $campos = array_merge(['id'], (new ComportamientoClienteParametro())->getFillable());
            $filas = array_map(fn ($fila) => Arr::only((array) $fila, $campos), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_comportamiento_cliente_parametros_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar los parámetros');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar los parámetros', $e->getMessage()));
        }
    }

    // QUITADO 11-nov-2026 (activo): lo guardado ya no se borra (se desactiva con el check Activo); su ruta quedó comentada y
    // ventas.fn_comportamiento_cliente_parametros_eliminar se queda en la base, sin uso. Reactivar = descomentar este método y
    // su ruta.
    // // Version 1.0
    // // DELETE /api/ventas/deleteComportamientoClienteParametro/{id}
    // public function deleteComportamientoClienteParametro(Request $request, $id)
    // {
    //     try {
    //         DB::selectOne('SELECT ventas.fn_comportamiento_cliente_parametros_eliminar(?::jsonb) AS id', [json_encode([
    //             'id' => (int) $id,
    //             'auditoria' => $this->contextoAuditoriaForense($request),
    //         ], JSON_UNESCAPED_UNICODE)]);
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
    //     } catch (QueryException $e) {
    //         return $this->respuestaError($e, 'Error al eliminar el parámetro');
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el parámetro', $e->getMessage()));
    //     }
    // }

    // Version 1.0
    // GET /api/ventas/comportamientoClienteParametroAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado
    // de UNA fila (auditoria.logs_cambios).
    public function comportamientoClienteParametroAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

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

    // Version 1.0
    // GET /api/ventas/comportamientoClienteParametrosAuditoria?pagina&tamanio&busqueda — 11-nov-2026 (activo): la auditoría de
    // TODA la pestaña (el botón Auditoría de la cabecera): resumen + historial paginado de todas las filas, también las borradas
    // antes y las desactivadas (auditoria.logs_cambios). La búsqueda también encuentra el nombre de la fila.
    public function comportamientoClienteParametrosAuditoria(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_auditoria_tabla_resumen()');
            $eventos = DB::select('SELECT * FROM ventas.fn_comportamiento_cliente_parametros_auditoria_tabla_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de los parámetros de comportamiento', $e->getMessage()));
        }
    }

    // 11-nov-2026 (activo): la forma de activo de cada fila (verdadero o falso). Lo demás lo valida la función.
    private function reglasFilas(): array
    {
        return [
            'filas' => 'array',
            'filas.*.activo' => 'boolean',
        ];
    }

    // 11-nov-2026 (activo): el mismo mensaje del código de la función.
    private function mensajesFilas(): array
    {
        return [
            'filas.array' => 'Las filas no tienen un formato válido.',
            'filas.*.activo.boolean' => self::MENSAJES_ERROR['ACTIVO_INVALIDO'],
        ];
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico.
    private function respuestaError(QueryException $e, string $generico)
    {
        // QUITADO 11-nov-2026 (activo): el código se buscaba con strpos (if (strpos($e->getMessage(), $codigo) !== false)).
        // Ahora como palabra entera (estándar CRUD): así un código nunca se confunde con otro más largo que lo contenga.
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}
