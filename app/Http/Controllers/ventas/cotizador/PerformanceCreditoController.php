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

// Cotizador > Parámetros tipo calificación cliente, pestaña Performance crédito (ventas.performance_credito): la hoja "Actual"
// del Excel de Scoring 2026 hecha parámetro. Una sola fila por línea (ELECTRO o MOTO) y tipo de cliente (las N, NN y R de
// ventas.tipo_cliente, sin DESCONOCIDO): si aplica crédito (SI o NO) y, para Formal e Informal, la capacidad de pago, la
// inicial (en % o en $ según inicial_tipo), el plazo en meses y la inspección (DIFERIDA o PREVIA). Con aplica = NO la función
// deja vacíos todos esos valores. Quien consume estas condiciones las lee con ventas.fn_performance_credito_condiciones_buscar
// (no pasa por este controller).
// El negocio vive en ventas.fn_performance_credito_* (PLANES/COTIZADOR/produccion_performance_credito.sql): este controller
// arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
class PerformanceCreditoController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_LINEA' => 'La línea es obligatoria.',
        'LINEA_INVALIDA' => 'La línea debe ser ELECTRO o MOTO.',
        'REQUIERE_TIPO_CLIENTE' => 'El tipo de cliente es obligatorio.',
        'TIPO_CLIENTE_INVALIDO' => 'El tipo de cliente no es válido.',
        'REQUIERE_APLICA' => 'Indique si aplica crédito (SI o NO).',
        'APLICA_INVALIDA' => 'Aplica solo acepta SI o NO.',
        'PERFORMANCE_DUPLICADO' => 'Ya existe una fila para esa línea y ese tipo de cliente.',
        'REQUIERE_CAPACIDAD' => 'La capacidad de pago Formal e Informal es obligatoria.',
        'CAPACIDAD_INVALIDA' => 'La capacidad de pago debe ser un número de 0 en adelante, con hasta 2 decimales.',
        'REQUIERE_INICIAL_TIPO' => 'Elija si la inicial es porcentaje (%) o monto ($).',
        'INICIAL_TIPO_INVALIDO' => 'El tipo de inicial debe ser % o $.',
        'REQUIERE_INICIAL' => 'La inicial Formal e Informal es obligatoria.',
        'INICIAL_INVALIDA' => 'La inicial debe ser un número de 0 en adelante, con hasta 2 decimales.',
        'INICIAL_PORCENTAJE_INVALIDO' => 'Una inicial en porcentaje no puede pasar de 100.',
        'REQUIERE_PLAZO' => 'El plazo Formal e Informal es obligatorio.',
        'PLAZO_INVALIDO' => 'El plazo debe ser meses enteros, de 1 en adelante.',
        'REQUIERE_INSPECCION' => 'La inspección Formal e Informal es obligatoria.',
        'INSPECCION_INVALIDA' => 'La inspección debe ser DIFERIDA o PREVIA.',
        'PERFORMANCE_NO_EXISTE' => 'La fila ya no existe.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listPerformanceCredito?pagina&tamanio&busqueda — la grilla de la pestaña, con el nombre del tipo de
    // cliente. La función la ordena por línea y, dentro de cada una, las N y NN antes que las R; busca por línea o por tipo.
    public function listPerformanceCredito(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_performance_credito_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

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

    // Version 1.0
    // GET /api/ventas/listCombosPerformanceCredito — los combos de la grilla en una sola llamada (codigo, nombre): la Línea
    // (ELECTRO y MOTO), Aplica (SI o NO), el tipo de inicial (PORCENTAJE se ve "%" y MONTO "$") y la Inspección (DIFERIDA o
    // PREVIA); el Tipo de cliente va con (id, nombre): las N, NN y R activas de ventas.tipo_cliente, sin DESCONOCIDO.
    // Una sola fuente con la validación de la función.
    public function listCombosPerformanceCredito()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'lineas' => DB::select('SELECT * FROM ventas.fn_performance_credito_lineas_listar()'),
                'tipos_cliente' => DB::select('SELECT * FROM ventas.fn_performance_credito_tipos_cliente_listar()'),
                'aplica' => DB::select('SELECT * FROM ventas.fn_performance_credito_aplica_listar()'),
                'inicial_tipos' => DB::select('SELECT * FROM ventas.fn_performance_credito_inicial_tipos_listar()'),
                'inspecciones' => DB::select('SELECT * FROM ventas.fn_performance_credito_inspecciones_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los combos', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarPerformanceCredito — las filas nuevas (sin id) y las editadas, todo o nada.
    // De cada fila pasan solo el id y los campos del modelo; con aplica = NO la función borra los demás valores.
    public function guardarPerformanceCredito(Request $request)
    {
        try {
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

    // Version 1.0
    // DELETE /api/ventas/deletePerformanceCredito/{id}
    public function deletePerformanceCredito(Request $request, $id)
    {
        try {
            DB::selectOne('SELECT ventas.fn_performance_credito_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ], JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar la fila de performance de crédito');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la fila de performance de crédito', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/performanceCreditoAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA fila
    // (auditoria.logs_cambios). Sin columna nombre: el resumen lo arma la función con la línea y el tipo de cliente (ELECTRO N1).
    public function performanceCreditoAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
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

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico. Aquí todos los mensajes son fijos: no hay cruces que validar, así que la función no arma textos.
    private function respuestaError(QueryException $e, string $generico)
    {
        // El código se busca como palabra entera, nunca con strpos: así no se confunde con otro código más largo que lo
        // contenga (REQUIERE_INICIAL está dentro de REQUIERE_INICIAL_TIPO).
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}
