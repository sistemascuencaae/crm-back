<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\TipoClienteParametro;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente (ventas.tipo_cliente_parametros): el rango de
// score de cada tipo. El negocio vive en ventas.fn_tipo_cliente_parametros_* (PLANES/COTIZADOR/produccion_tipo_cliente_parametros.sql):
// este controller arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
// 11-nov-2026 (pestaña 2): acreedores en 3 cortes (mínimo y máximo), las fechas del Excel y activo. Lo guardado ya no se borra
// (se desactiva con el check Activo y el Guardar de siempre): deleteTipoClienteParametro quedó comentado. Nueva la auditoría
// de TODA la pestaña (tipoClienteParametrosAuditoria), además de la de una fila.
// 12-nov-2026 (booleanos): NN y TC son checks (verdadero o falso); salen los 3 cortes de acreedores y las 4 fechas (y sus
// códigos de error, que ya no salen de la función).
class TipoClienteParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_NOMBRE' => 'El nombre es obligatorio.',
        'NOMBRE_MUY_LARGO' => 'El nombre no puede pasar de 60 caracteres.',
        'SCORE_INVALIDO' => 'El score mínimo y el máximo deben ser números enteros.',
        'SCORE_RANGO_INVERTIDO' => 'El score mínimo no puede ser mayor que el máximo.',
        'TIPO_CLIENTE_DUPLICADO' => 'Ya existe una fila con ese nombre.',
        'TIPO_CLIENTE_NO_EXISTE' => 'La fila ya no existe.',
        'PARAMETRO_NO_CONFIGURADO' => 'Faltan los límites del score en crm.parametro.',
        'EDAD_INVALIDA' => 'La edad mínima y la máxima deben ser números enteros.',
        'EDAD_RANGO_INVERTIDO' => 'La edad mínima no puede ser mayor que la máxima.',
        // QUITADO 13-nov-2026 (antiguedad): 'ANTIGUEDAD_INVALIDA' => 'La antigüedad mínima y la máxima deben ser meses enteros.',
        // QUITADO 13-nov-2026 (antiguedad): 'ANTIGUEDAD_RANGO_INVERTIDO' => 'La antigüedad mínima no puede ser mayor que la máxima.',
        // 13-nov-2026 (antiguedad): una sola antigüedad; la función ya no levanta ANTIGUEDAD_RANGO_INVERTIDO.
        'ANTIGUEDAD_INVALIDA' => 'La antigüedad debe ser un número de 0 en adelante.',
        // QUITADO 11-nov-2026 (pestaña 2): 'CONDICION_INVALIDA' => 'Acreedores, NN y TC solo aceptan SI o NO; vacío = no importa.',
        // QUITADO 12-nov-2026 (booleanos): 'CONDICION_INVALIDA' => 'NN y TC solo aceptan SI o NO; vacío = no importa.',
        // QUITADO 13-nov-2026 (nn texto): 'CONDICION_INVALIDA' => 'NN y TC solo aceptan sí o no.',
        // 13-nov-2026 (nn texto): NN es texto (MAYÚSCULAS o vacío); solo TC sigue siendo sí o no.
        'CONDICION_INVALIDA' => 'TC solo acepta sí o no.',
        'REQUIERE_PERFIL' => 'El perfil de riesgo es obligatorio.',
        'PERFIL_INVALIDO' => 'El perfil de riesgo no es válido.',
        'REQUIERE_TIPO_CLIENTE' => 'El tipo de cliente (NUEVO o REITERATIVO) es obligatorio.',
        'TIPO_CLIENTE_INVALIDO' => 'El tipo de cliente debe ser NUEVO o REITERATIVO.',
        // 11-nov-2026 (pestaña 2): los acreedores de los 3 cortes, las 4 fechas y activo.
        // QUITADO 12-nov-2026 (booleanos): los acreedores y las 4 fechas salieron (la función ya no levanta estos códigos):
        // 'ACREEDORES_INVALIDO' => 'Los acreedores (Actual, -6 Meses y -12 Meses) deben ser números enteros de 0 en adelante.',
        // 'ACREEDORES_ACTUAL_RANGO_INVERTIDO' => 'Acreedores Actual: el mínimo no puede ser mayor que el máximo.',
        // 'ACREEDORES_6_MESES_RANGO_INVERTIDO' => 'Acreedores -6 Meses: el mínimo no puede ser mayor que el máximo.',
        // 'ACREEDORES_12_MESES_RANGO_INVERTIDO' => 'Acreedores -12 Meses: el mínimo no puede ser mayor que el máximo.',
        // 'FECHA_APERTURA_SBS_INVALIDA' => 'La fecha de apertura SBS no es una fecha válida.',
        // 'FECHA_APERTURA_RFR_INVALIDA' => 'La fecha de apertura RFR no es una fecha válida.',
        // 'FECHA_APERTURA_SICOM_INVALIDA' => 'La fecha de apertura SICOM no es una fecha válida.',
        // 'FECHA_CONSULTA_INVALIDA' => 'La fecha de consulta no es una fecha válida.',
        'ACTIVO_INVALIDO' => 'Activo solo acepta sí o no.',
    ];

    // QUITADO 12-nov-2026 (booleanos): los campos de acreedores y de fecha que revisaba el Validator (salieron de la tabla).
    // // 11-nov-2026 (pestaña 2): los campos que revisa el Validator antes de la función, y el código de cada fecha.
    // private const CAMPOS_ACREEDORES = [
    //     'acreedores_actual_minimo',
    //     'acreedores_actual_maximo',
    //     'acreedores_6_meses_minimo',
    //     'acreedores_6_meses_maximo',
    //     'acreedores_12_meses_minimo',
    //     'acreedores_12_meses_maximo',
    // ];
    //
    // private const CAMPOS_FECHA = [
    //     'fecha_apertura_sbs' => 'FECHA_APERTURA_SBS_INVALIDA',
    //     'fecha_apertura_rfr' => 'FECHA_APERTURA_RFR_INVALIDA',
    //     'fecha_apertura_sicom' => 'FECHA_APERTURA_SICOM_INVALIDA',
    //     'fecha_consulta' => 'FECHA_CONSULTA_INVALIDA',
    // ];

    // 12-nov-2026 (booleanos): los checks de cada fila que revisa el Validator (verdadero o falso) y el código de su mensaje.
    // 13-nov-2026 (nn texto): NN sale de aquí: es texto (la función lo deja en MAYÚSCULAS y vacío = null).
    private const CAMPOS_BOOLEANOS = [
        // QUITADO 13-nov-2026 (nn texto): 'garancheck_nn' => 'CONDICION_INVALIDA',
        'garancheck_tc' => 'CONDICION_INVALIDA',
        'activo' => 'ACTIVO_INVALIDO',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listTipoClienteParametros?pagina&tamanio&busqueda — la grilla de la pestaña.
    // 11-nov-2026 (pestaña 2): trae también las filas inactivas (se reactivan en la grilla), con los campos nuevos.
    public function listTipoClienteParametros(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Version 1.1
    // GET /api/ventas/listCombosTipoClienteParametros — los combos de la grilla en una sola llamada (codigo, nombre):
    // Perfil de riesgo y SI/NO de las condiciones. Una sola fuente con la validación de la función.
    // 10-nov noche: + Tipo Cliente (NUEVO o REITERATIVO).
    // 11-nov-2026 (pestaña 2): SI/NO ya es solo de NN y TC (los acreedores son números).
    // 12-nov-2026 (booleanos): NN y TC son checks: la grilla ya no usa 'condiciones' (la clave y la función se quedan).
    public function listCombosTipoClienteParametros()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'tipos_cliente' => DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_tipos_cliente_listar()'),
                'perfiles' => DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_perfiles_listar()'),
                'condiciones' => DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_condiciones_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los combos', $e->getMessage()));
        }
    }

    // Version 1.2
    // POST /api/ventas/guardarTipoClienteParametros — las filas nuevas (sin id) y las editadas, todo o nada.
    // De cada fila pasan solo el id y los campos del modelo.
    // 11-nov-2026 (pestaña 2): antes, el Validator revisa la forma de los campos nuevos (acreedores enteros de 0 en adelante,
    // fechas y activo verdadero o falso) con los mismos mensajes de la función; lo demás (mínimo contra máximo, fechas
    // AAAA-MM-DD, cruces) lo valida la función. Desactivar o reactivar una fila = guardarla con activo en false o true.
    // 12-nov-2026 (booleanos): el Validator revisa que NN, TC y activo sean verdadero o falso (sin acreedores ni fechas).
    // 13-nov-2026 (nn texto): NN ya no pasa por el Validator (es texto); solo TC y activo.
    public function guardarTipoClienteParametros(Request $request)
    {
        try {
            $validator = Validator::make($request->only('filas'), $this->reglasFilas(), $this->mensajesFilas());
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $campos = array_merge(['id'], (new TipoClienteParametro())->getFillable());
            $filas = array_map(fn ($fila) => Arr::only((array) $fila, $campos), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_tipo_cliente_parametros_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar los tipos de cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar los tipos de cliente', $e->getMessage()));
        }
    }

    // QUITADO 11-nov-2026 (pestaña 2): lo guardado ya no se borra (se desactiva con el check Activo); su ruta quedó comentada y
    // ventas.fn_tipo_cliente_parametros_eliminar se queda en la base, sin uso. Reactivar = descomentar este método y su ruta.
    // // Version 1.0
    // // DELETE /api/ventas/deleteTipoClienteParametro/{id}
    // public function deleteTipoClienteParametro(Request $request, $id)
    // {
    //     try {
    //         DB::selectOne('SELECT ventas.fn_tipo_cliente_parametros_eliminar(?::jsonb) AS id', [json_encode([
    //             'id' => (int) $id,
    //             'auditoria' => $this->contextoAuditoriaForense($request),
    //         ], JSON_UNESCAPED_UNICODE)]);
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
    //     } catch (QueryException $e) {
    //         return $this->respuestaError($e, 'Error al eliminar el tipo de cliente');
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el tipo de cliente', $e->getMessage()));
    //     }
    // }

    // Version 1.0
    // GET /api/ventas/tipoClienteParametroAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA fila
    // (auditoria.logs_cambios).
    public function tipoClienteParametroAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_tipo_cliente_parametros_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

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

    // Version 1.0
    // GET /api/ventas/tipoClienteParametrosAuditoria?pagina&tamanio&busqueda — 11-nov-2026 (pestaña 2): la auditoría de TODA la
    // pestaña (el botón Auditoría de la cabecera): resumen + historial paginado de todas las filas, también las borradas antes
    // y las desactivadas (auditoria.logs_cambios). La búsqueda también encuentra el nombre de la regla (N1, R2...).
    public function tipoClienteParametrosAuditoria(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_tipo_cliente_parametros_auditoria_tabla_resumen()');
            $eventos = DB::select('SELECT * FROM ventas.fn_tipo_cliente_parametros_auditoria_tabla_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de los tipos de cliente', $e->getMessage()));
        }
    }

    // 11-nov-2026 (pestaña 2): la forma de los campos nuevos de cada fila (vacío = no importa / sin fecha). Lo demás lo valida
    // la función.
    // 12-nov-2026 (booleanos): NN, TC y activo verdadero o falso.
    private function reglasFilas(): array
    {
        $reglas = ['filas' => 'array'];
        // QUITADO 12-nov-2026 (booleanos): los acreedores ('integer|min:0|nullable') y las fechas ('date|nullable'):
        // foreach (self::CAMPOS_ACREEDORES as $campo) {
        //     $reglas["filas.*.{$campo}"] = 'integer|min:0|nullable';
        // }
        // foreach (array_keys(self::CAMPOS_FECHA) as $campo) {
        //     $reglas["filas.*.{$campo}"] = 'date|nullable';
        // }
        // QUITADO 12-nov-2026 (booleanos): $reglas['filas.*.activo'] = 'boolean'; (va con NN y TC en CAMPOS_BOOLEANOS)
        foreach (array_keys(self::CAMPOS_BOOLEANOS) as $campo) {
            $reglas["filas.*.{$campo}"] = 'boolean';
        }

        return $reglas;
    }

    // 11-nov-2026 (pestaña 2): los mismos mensajes de los códigos de la función.
    private function mensajesFilas(): array
    {
        $mensajes = ['filas.array' => 'Las filas no tienen un formato válido.'];
        // QUITADO 12-nov-2026 (booleanos): los mensajes de los acreedores y de las fechas:
        // foreach (self::CAMPOS_ACREEDORES as $campo) {
        //     $mensajes["filas.*.{$campo}.integer"] = self::MENSAJES_ERROR['ACREEDORES_INVALIDO'];
        //     $mensajes["filas.*.{$campo}.min"] = self::MENSAJES_ERROR['ACREEDORES_INVALIDO'];
        // }
        // foreach (self::CAMPOS_FECHA as $campo => $codigo) {
        //     $mensajes["filas.*.{$campo}.date"] = self::MENSAJES_ERROR[$codigo];
        // }
        // QUITADO 12-nov-2026 (booleanos): $mensajes['filas.*.activo.boolean'] = self::MENSAJES_ERROR['ACTIVO_INVALIDO'];
        foreach (self::CAMPOS_BOOLEANOS as $campo => $codigo) {
            $mensajes["filas.*.{$campo}.boolean"] = self::MENSAJES_ERROR[$codigo];
        }

        return $mensajes;
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico. Los límites del score y los rangos que chocan solo los conoce la función: ella arma ese texto.
    private function respuestaError(QueryException $e, string $generico)
    {
        if (preg_match('/(?:SCORE_FUERA_DE_LIMITES|RANGO_CRUZADO): (.+?)\s*(?:\R|\(SQL:|$)/', $e->getMessage(), $texto)) {
            return response()->json(RespuestaApi::returnResultado('error', $texto[1], null));
        }

        // El código se busca como palabra entera: EDAD_INVALIDA está dentro de ANTIGUEDAD_INVALIDA.
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}
