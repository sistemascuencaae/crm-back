<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\PerformanceCredito;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito (ventas.performance_credito): la hoja "Actual"
// del Excel de Scoring 2026 hecha parámetro. Una sola fila por línea (ELECTRO o MOTO) y tipo de cliente (QUITADO 12-nov-2026
// (tipo cliente de la 2): las N, NN y R de ventas.tipo_cliente, sin DESCONOCIDO; 12-nov-2026: una regla de la pestaña 2 Tipo de
// cliente, ventas.tipo_cliente_parametros): si aplica crédito (SI o NO) y, para Formal e Informal, la capacidad de pago, la
// inicial (en % o en $ según inicial_tipo), el plazo en meses y la inspección (DIFERIDA o PREVIA).
// 12-nov-2026 (tipo cliente de la 2; el usuario: "lo que estamos configurando son parámetros para una precalificación de un
// cliente y esto lo va a leer otra persona"): tipo_cliente_id es el id de la REGLA de la pestaña 2 y tipo_cliente su nombre (las
// respuestas conservan esos dos campos); el combo ofrece las reglas ACTIVAS de la 2 y la función rechaza (TIPO_CLIENTE_INVALIDO) una
// regla inactiva o inexistente en una fila nueva o que cambia de tipo. Quien lee las condiciones las busca por el nombre de la regla.
// QUITADO 12-nov-2026 (performance sin bloqueos): Con aplica = NO la función deja vacíos todos esos valores.
// 12-nov-2026 (performance sin bloqueos): con aplica SI o NO esos valores son opcionales y se guardan tal como llegan (la
// función solo revisa el formato). Quien consume estas condiciones las lee con ventas.fn_performance_credito_condiciones_buscar
// (no pasa por este controller) y las valida.
// El negocio vive en ventas.fn_performance_credito_* (PLANES/COTIZADOR/produccion_performance_credito.sql): este controller
// arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
// 11-nov-2026 (líneas): la línea es linea_id (catálogo ventas.linea, LineaController, panel izquierdo); la grilla y la
// auditoría son POR LÍNEA y hay Clonar entre líneas (vista previa + clonado).
// 11-nov-2026 (activo, patrón de la pestaña 2): lo guardado ya no se borra (se desactiva con el check Activo y el Guardar de
// siempre; una fila inactiva no la devuelve fn_performance_credito_condiciones_buscar y Clonar copia solo las activas):
// deletePerformanceCredito quedó comentado. La auditoría de toda la línea (cabecera) ya muestra lo desactivado.
// 12-nov-2026 (booleanos; decisión del usuario): aplica es boolean (en la grilla, un check como Activo). El Validator revisa que
// sea verdadero o falso (el mismo mensaje de la función); sin la clave, la fila nueva nace sin marcar y la que existe no cambia.
// El listado devuelve aplica true / false y los combos ya no traen el de Aplica (SI / NO). Quien lee las condiciones
// (fn_performance_credito_condiciones_buscar) sigue recibiendo aplica 'SI' / 'NO'.
class PerformanceCreditoController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_LINEA' => 'La línea es obligatoria.',
        // QUITADO 11-nov-2026 (líneas): 'LINEA_INVALIDA' => 'La línea debe ser ELECTRO o MOTO.',
        'LINEA_INVALIDA' => 'La línea no es válida.',
        'LINEA_INACTIVA' => 'La línea está inactiva: elija una línea activa.',
        'REQUIERE_TIPO_CLIENTE' => 'El tipo de cliente es obligatorio.',
        // 12-nov-2026 (tipo cliente de la 2): también sale cuando la regla elegida no existe en la pestaña 2 o está inactiva y la fila
        // es nueva o cambia de tipo (el mensaje no cambia).
        'TIPO_CLIENTE_INVALIDO' => 'El tipo de cliente no es válido.',
        // QUITADO 12-nov-2026 (booleanos): aplica ya no es obligatoria (sin la clave, la fila nueva nace sin marcar y la que existe
        // no cambia): la función ya no levanta REQUIERE_APLICA.
        // 'REQUIERE_APLICA' => 'Indique si aplica crédito (SI o NO).',
        // QUITADO 12-nov-2026 (booleanos): 'APLICA_INVALIDA' => 'Aplica solo acepta SI o NO.',
        // 12-nov-2026 (booleanos): aplica es verdadero o falso (el check de la grilla), como activo.
        'APLICA_INVALIDA' => 'Aplica solo acepta sí o no.',
        // QUITADO 11-nov-2026 (activo): 'PERFORMANCE_DUPLICADO' => 'Ya existe una fila para esa línea y ese tipo de cliente.',
        'PERFORMANCE_DUPLICADO' => 'Ya existe una fila para esa línea y ese tipo de cliente (activa o inactiva): edítela o reactívela.',
        // QUITADO 12-nov-2026 (performance sin bloqueos): los valores de Formal e Informal ya no son obligatorios (con aplica SI
        // o NO): la función ya no levanta REQUIERE_CAPACIDAD, REQUIERE_INICIAL_TIPO, REQUIERE_INICIAL, REQUIERE_PLAZO ni
        // REQUIERE_INSPECCION.
        // 'REQUIERE_CAPACIDAD' => 'La capacidad de pago Formal e Informal es obligatoria.',
        'CAPACIDAD_INVALIDA' => 'La capacidad de pago debe ser un número de 0 en adelante, con hasta 2 decimales.',
        // 'REQUIERE_INICIAL_TIPO' => 'Elija si la inicial es porcentaje (%) o monto ($).',
        'INICIAL_TIPO_INVALIDO' => 'El tipo de inicial debe ser % o $.',
        // 'REQUIERE_INICIAL' => 'La inicial Formal e Informal es obligatoria.',
        'INICIAL_INVALIDA' => 'La inicial debe ser un número de 0 en adelante, con hasta 2 decimales.',
        'INICIAL_PORCENTAJE_INVALIDO' => 'Una inicial en porcentaje no puede pasar de 100.',
        // 'REQUIERE_PLAZO' => 'El plazo Formal e Informal es obligatorio.',
        // QUITADO 12-nov-2026 (performance sin bloqueos, plazo 0; el usuario: que el plazo acepte 0):
        // 'PLAZO_INVALIDO' => 'El plazo debe ser meses enteros, de 1 en adelante.',
        'PLAZO_INVALIDO' => 'El plazo debe ser meses enteros, de 0 en adelante.',
        // 'REQUIERE_INSPECCION' => 'La inspección Formal e Informal es obligatoria.',
        'INSPECCION_INVALIDA' => 'La inspección debe ser DIFERIDA o PREVIA.',
        'PERFORMANCE_NO_EXISTE' => 'La fila ya no existe.',
        // 11-nov-2026 (activo).
        'ACTIVO_INVALIDO' => 'Activo solo acepta sí o no.',
        // 11-nov-2026 (líneas): Clonar entre líneas.
        'REQUIERE_LINEA_ORIGEN' => 'No se recibió la línea origen.',
        'LINEA_ORIGEN_INVALIDA' => 'La línea origen no existe.',
        // QUITADO 11-nov-2026 (activo): 'ORIGEN_SIN_PARAMETROS' => 'La línea origen no tiene tipos de cliente para clonar.',
        'ORIGEN_SIN_PARAMETROS' => 'La línea origen no tiene tipos de cliente activos para clonar.',
        'REQUIERE_LINEA_DESTINO' => 'Elija al menos una línea destino.',
        'LINEA_DESTINO_INVALIDA' => 'Una de las líneas destino no existe.',
        'LINEA_DESTINO_INACTIVA' => 'Una de las líneas destino está inactiva.',
        'DESTINO_ES_ORIGEN' => 'La línea origen no puede ser también destino.',
    ];

    // 11-nov-2026 (líneas): los mensajes del Validator del pedido de Clonar (la pantalla siempre manda todo bien).
    private const MENSAJES_CLONAR = [
        'linea_id_origen.required'     => 'No se recibió la línea origen.',
        'linea_id_origen.integer'      => 'La línea origen no es válida.',
        'linea_id_origen.min'          => 'La línea origen no es válida.',
        'linea_ids_destino.required'   => 'Elija al menos una línea destino.',
        'linea_ids_destino.array'      => 'Las líneas destino no tienen un formato válido.',
        'linea_ids_destino.max'        => 'Puede clonar a 200 líneas como máximo a la vez.',
        'linea_ids_destino.*.required' => 'Una de las líneas destino no es válida.',
        'linea_ids_destino.*.integer'  => 'Una de las líneas destino no es válida.',
        'linea_ids_destino.*.min'      => 'Una de las líneas destino no es válida.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // QUITADO 12-nov-2026 (booleanos): // Version 1.0
    // Version 1.1
    // 12-nov-2026 (booleanos): cada registro trae aplica true / false (antes 'SI' / 'NO').
    // GET /api/ventas/listPerformanceCredito?linea_id&pagina&tamanio&busqueda — la grilla de la pestaña, con el nombre del tipo
    // de cliente. La función la ordena por línea y, dentro de cada una, las N y NN antes que las R; busca por línea o por tipo.
    // 12-nov-2026 (tipo cliente de la 2): el orden de la grilla es el de la pestaña 2 y el del combo (tipo de cliente NUEVO o
    // REITERATIVO, nombre, id); el nombre del tipo de cliente es el de su regla.
    // 11-nov-2026 (líneas): solo las filas de UNA línea (linea_id, obligatoria: la elegida en el panel izquierdo); la búsqueda
    // es por tipo de cliente.
    // 11-nov-2026 (activo): trae también las filas inactivas (se reactivan en la grilla), con activo.
    public function listPerformanceCredito(Request $request)
    {
        try {
            $lineaId = (int) $request->query('linea_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($lineaId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Elija una línea.', null));
            }

            // QUITADO 11-nov-2026 (líneas): DB::select('SELECT * FROM ventas.fn_performance_credito_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);
            $registros = DB::select('SELECT * FROM ventas.fn_performance_credito_listar_paginacion(?, ?, ?, ?)', [$lineaId, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las filas de performance de crédito', $e->getMessage()));
        }
    }

    // QUITADO 12-nov-2026 (tipo cliente de la 2): // Version 1.0
    // QUITADO 12-nov-2026 (booleanos): // Version 1.1
    // Version 1.2
    // 12-nov-2026 (booleanos): ya sin 'aplica' (Aplica es un check en la grilla; ventas.fn_performance_credito_aplica_listar queda
    // en la base, sin uso).
    // GET /api/ventas/listCombosPerformanceCredito — los combos de la grilla en una sola llamada (codigo, nombre): la Línea
    // (ELECTRO y MOTO), Aplica (SI o NO), el tipo de inicial (PORCENTAJE se ve "%" y MONTO "$") y la Inspección (DIFERIDA o
    // PREVIA); el Tipo de cliente va con (id, nombre): QUITADO 12-nov-2026 (tipo cliente de la 2): las N, NN y R activas de
    // ventas.tipo_cliente, sin DESCONOCIDO.
    // Una sola fuente con la validación de la función.
    // 11-nov-2026 (líneas): ya sin la Línea (la grilla es de la línea elegida en el panel izquierdo; el catálogo es LineaController).
    // 12-nov-2026 (tipo cliente de la 2): el Tipo de cliente (id, nombre) son las reglas ACTIVAS de la pestaña 2
    // (ventas.tipo_cliente_parametros), en el orden de su grilla (tipo de cliente, nombre, id). La respuesta tiene la misma forma. La
    // pantalla vuelve a pedir estos combos cada vez que se vuelve a mostrar la pestaña (se pudo agregar, activar, desactivar o renombrar
    // una regla en la 2).
    public function listCombosPerformanceCredito()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                // QUITADO 11-nov-2026 (líneas): 'lineas' => DB::select('SELECT * FROM ventas.fn_performance_credito_lineas_listar()'),
                'tipos_cliente' => DB::select('SELECT * FROM ventas.fn_performance_credito_tipos_cliente_listar()'),
                // QUITADO 12-nov-2026 (booleanos): 'aplica' => DB::select('SELECT * FROM ventas.fn_performance_credito_aplica_listar()'),
                'inicial_tipos' => DB::select('SELECT * FROM ventas.fn_performance_credito_inicial_tipos_listar()'),
                'inspecciones' => DB::select('SELECT * FROM ventas.fn_performance_credito_inspecciones_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los combos', $e->getMessage()));
        }
    }

    // QUITADO 12-nov-2026 (booleanos): // Version 1.1
    // Version 1.2
    // 12-nov-2026 (booleanos): aplica va como verdadero o falso (el Validator lo revisa con el mensaje de la función); sin la
    // clave, la fila nueva nace sin marcar y la que existe no cambia.
    // POST /api/ventas/guardarPerformanceCredito — las filas nuevas (sin id) y las editadas, todo o nada.
    // QUITADO 12-nov-2026 (performance sin bloqueos): De cada fila pasan solo el id y los campos del modelo; con aplica = NO la
    // función borra los demás valores.
    // De cada fila pasan solo el id y los campos del modelo. 12-nov-2026 (performance sin bloqueos): con aplica SI o NO, la
    // función guarda los valores de Formal e Informal tal como llegan (vacío = null); solo revisa el formato.
    // 11-nov-2026 (activo): antes, el Validator revisa que activo sea verdadero o falso (el mismo mensaje de la función).
    // Desactivar o reactivar una fila = guardarla con activo en false o true.
    public function guardarPerformanceCredito(Request $request)
    {
        try {
            // 12-nov-2026 (booleanos): + filas.*.aplica (boolean, como activo).
            $validator = Validator::make($request->only('filas'), [
                'filas' => 'array',
                'filas.*.activo' => 'boolean',
                'filas.*.aplica' => 'boolean',
            ], [
                'filas.array' => 'Las filas no tienen un formato válido.',
                'filas.*.activo.boolean' => self::MENSAJES_ERROR['ACTIVO_INVALIDO'],
                'filas.*.aplica.boolean' => self::MENSAJES_ERROR['APLICA_INVALIDA'],
            ]);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $campos = array_merge(['id'], (new PerformanceCredito())->getFillable());
            $filas = array_map(fn ($fila) => Arr::only((array) $fila, $campos), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_performance_credito_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar las filas de performance de crédito');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar las filas de performance de crédito', $e->getMessage()));
        }
    }

    // QUITADO 11-nov-2026 (activo): lo guardado ya no se borra (se desactiva con el check Activo); su ruta quedó comentada y
    // ventas.fn_performance_credito_eliminar se queda en la base, sin uso. Reactivar = descomentar este método y su ruta.
    // // Version 1.0
    // // DELETE /api/ventas/deletePerformanceCredito/{id}
    // public function deletePerformanceCredito(Request $request, $id)
    // {
    //     try {
    //         DB::selectOne('SELECT ventas.fn_performance_credito_eliminar(?::jsonb) AS id', [json_encode([
    //             'id' => (int) $id,
    //             'auditoria' => $this->contextoAuditoriaForense($request),
    //         ], JSON_UNESCAPED_UNICODE)]);
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
    //     } catch (QueryException $e) {
    //         return $this->respuestaError($e, 'Error al eliminar la fila de performance de crédito');
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la fila de performance de crédito', $e->getMessage()));
    //     }
    // }

    // Version 1.0
    // GET /api/ventas/performanceCreditoAuditoria?linea_id&pagina&tamanio&busqueda — resumen + historial paginado de UNA fila
    // (auditoria.logs_cambios). Sin columna nombre: el resumen lo arma la función con la línea y el tipo de cliente (ELECTRO N1).
    // 11-nov-2026 (líneas): de TODA la línea (linea_id): los eventos de todas sus filas, también las borradas; el resumen se
    // llama como la línea. La búsqueda también encuentra el tipo de cliente.
    public function performanceCreditoAuditoria(Request $request)
    {
        try {
            // QUITADO 11-nov-2026 (líneas): $id = (int) $request->query('id', 0);
            $id = (int) $request->query('linea_id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_performance_credito_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_performance_credito_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la fila de performance de crédito', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/clonarPerformanceCreditoVistaPrevia body {linea_id_origen, linea_ids_destino} — 11-nov-2026 (líneas):
    // Clonar parámetros, lo que pasaría en cada línea destino (solo lectura), con las mismas validaciones que el clonado:
    // {origen: {linea_id, nombre, tipos_cliente}, destinos: [{linea_id, nombre, reemplazados, nuevos, iguales, omitidos, quedan}]}.
    public function clonarPerformanceCreditoVistaPrevia(Request $request)
    {
        try {
            $validator = Validator::make($request->only('linea_id_origen', 'linea_ids_destino'), $this->reglasClonar(), self::MENSAJES_CLONAR);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $resultado = DB::selectOne('SELECT ventas.fn_performance_credito_clonar_vista_previa(?::jsonb) AS resultado',
                [json_encode($this->pedidoClonar($request), JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            return response()->json(RespuestaApi::returnResultado('success', 'Vista previa del clonado.', [
                'origen'   => $respuesta['origen'] ?? null,
                'destinos' => $respuesta['destinos'] ?? [],
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudo preparar el clonado de los parámetros');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo preparar el clonado de los parámetros', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/clonarPerformanceCredito body {linea_id_origen, linea_ids_destino} — 11-nov-2026 (líneas): Clonar
    // parámetros. En cada destino se reemplaza la fila del mismo tipo de cliente y se crea la que falta; lo demás se queda.
    // Todo o nada; cada fila pasa por crear / modificar (valida y audita). El mensaje lo arma la función.
    public function clonarPerformanceCredito(Request $request)
    {
        try {
            $validator = Validator::make($request->only('linea_id_origen', 'linea_ids_destino'), $this->reglasClonar(), self::MENSAJES_CLONAR);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->pedidoClonar($request) + ['auditoria' => $this->contextoAuditoriaForense($request)];
            $resultado = DB::selectOne('SELECT ventas.fn_performance_credito_clonar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'] ?? 'Se clono con éxito', [
                'destinos' => $respuesta['destinos'] ?? [],
            ]));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'No se pudieron clonar los parámetros');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron clonar los parámetros', $e->getMessage()));
        }
    }

    // La forma del pedido de Clonar (vista previa y clonado); el negocio lo valida ventas.fn_performance_credito_clonar_validaciones.
    private function reglasClonar(): array
    {
        return [
            'linea_id_origen'     => 'required|integer|min:1',
            'linea_ids_destino'   => 'required|array|max:200',
            'linea_ids_destino.*' => 'required|integer|min:1',
        ];
    }

    // Los id del pedido como enteros (ya validados).
    private function pedidoClonar(Request $request): array
    {
        return [
            'linea_id_origen'   => (int) $request->input('linea_id_origen'),
            'linea_ids_destino' => array_values(array_map('intval', (array) $request->input('linea_ids_destino', []))),
        ];
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico. Aquí todos los mensajes son fijos: no hay cruces que validar, así que la función no arma textos.
    private function respuestaError(QueryException $e, string $generico)
    {
        // El código se busca como palabra entera, nunca con strpos: así no se confunde con otro código más largo que lo
        // contenga (REQUIERE_LINEA está dentro de REQUIERE_LINEA_ORIGEN).
        // QUITADO 12-nov-2026 (performance sin bloqueos): el ejemplo era (REQUIERE_INICIAL está dentro de REQUIERE_INICIAL_TIPO);
        // los dos ya no salen.
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}
