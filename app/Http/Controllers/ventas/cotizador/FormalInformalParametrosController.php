<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\FormalInformalParametro;
use Exception;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Cotizador > Parámetros tipo calificación cliente, pestaña Formal / Informal (ventas.formal_informal_parametros): las reglas
// para ser FORMAL (línea, rango de edad y meses mínimos en la afiliación activa al IESS); quien no cumple ninguna es INFORMAL.
// El negocio vive en ventas.fn_formal_informal_parametros_* (PLANES/COTIZADOR/produccion_formal_informal_parametros.sql):
// este controller arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
// 11-nov-2026 (líneas): la línea es linea_id (catálogo ventas.linea, compartido con Performance crédito); la grilla muestra su nombre.
// 11-nov-2026 (activo, patrón de la pestaña 2): lo guardado ya no se borra (se desactiva con el check Activo y el Guardar de
// siempre; una regla inactiva no cuenta en el cálculo ni choca): deleteFormalInformalParametro quedó comentado. Nueva la
// auditoría de TODA la pestaña (formalInformalParametrosAuditoria), además de la de una fila.
class FormalInformalParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    // Mensaje para cada código que levantan las funciones.
    private const MENSAJES_ERROR = [
        'REQUIERE_LINEA' => 'La línea es obligatoria.',
        'LINEA_INVALIDA' => 'La línea no es válida.',
        // 11-nov-2026 (líneas): una fila nueva (o que cambia de línea) no puede usar una línea inactiva.
        'LINEA_INACTIVA' => 'La línea está inactiva: elija una línea activa.',
        'REQUIERE_EDAD' => 'La edad mínima y la máxima son obligatorias.',
        'EDAD_INVALIDA' => 'La edad mínima y la máxima deben ser números enteros.',
        'EDAD_RANGO_INVERTIDO' => 'La edad mínima no puede ser mayor que la máxima.',
        'REQUIERE_ESTABILIDAD' => 'La estabilidad mínima es obligatoria.',
        'ESTABILIDAD_INVALIDA' => 'La estabilidad mínima debe ser meses enteros.',
        'FORMAL_INFORMAL_NO_EXISTE' => 'La fila ya no existe.',
        // 11-nov-2026 (activo).
        'ACTIVO_INVALIDO' => 'Activo solo acepta sí o no.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listFormalInformalParametros?pagina&tamanio&busqueda — la grilla de la pestaña.
    // 11-nov-2026 (activo): trae también las reglas inactivas (se reactivan en la grilla), con activo.
    public function listFormalInformalParametros(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select('SELECT * FROM ventas.fn_formal_informal_parametros_listar_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                'registros' => $registros,
                'total' => (int) ($registros[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las reglas de formal/informal', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listCombosFormalInformalParametros — los combos de la grilla en una sola llamada (codigo, nombre):
    // la Línea (hoy ELECTRO, MOTO y TECNOLOGIA). Una sola fuente con la validación de la función y el requerimiento 'formal informal'.
    // 11-nov-2026 (líneas): las líneas ACTIVAS del catálogo ventas.linea (codigo = el id como texto, nombre).
    public function listCombosFormalInformalParametros()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', [
                // QUITADO 11-nov-2026 (líneas): 'lineas' => DB::select('SELECT * FROM ventas.fn_formal_informal_parametros_lineas_listar()'),
                'lineas' => DB::select('SELECT * FROM ventas.fn_linea_activas_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los combos', $e->getMessage()));
        }
    }

    // Version 1.1
    // POST /api/ventas/guardarFormalInformalParametros — las filas nuevas (sin id) y las editadas, todo o nada.
    // De cada fila pasan solo el id y los campos del modelo.
    // 11-nov-2026 (activo): antes, el Validator revisa que activo sea verdadero o falso (el mismo mensaje de la función).
    // Desactivar o reactivar una regla = guardarla con activo en false o true.
    public function guardarFormalInformalParametros(Request $request)
    {
        try {
            $validator = Validator::make($request->only('filas'), [
                'filas' => 'array',
                'filas.*.activo' => 'boolean',
            ], [
                'filas.array' => 'Las filas no tienen un formato válido.',
                'filas.*.activo.boolean' => self::MENSAJES_ERROR['ACTIVO_INVALIDO'],
            ]);
            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $campos = array_merge(['id'], (new FormalInformalParametro())->getFillable());
            $filas = array_map(fn ($fila) => Arr::only((array) $fila, $campos), (array) $request->input('filas', []));
            $payload = ['filas' => $filas, 'auditoria' => $this->contextoAuditoriaForense($request)];

            $resultado = DB::selectOne('SELECT ventas.fn_formal_informal_parametros_guardar_lote(?::jsonb) AS cantidad', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $resultado->cantidad));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al guardar las reglas de formal/informal');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar las reglas de formal/informal', $e->getMessage()));
        }
    }

    // QUITADO 11-nov-2026 (activo): lo guardado ya no se borra (se desactiva con el check Activo); su ruta quedó comentada y
    // ventas.fn_formal_informal_parametros_eliminar se queda en la base, sin uso. Reactivar = descomentar este método y su ruta.
    // // Version 1.0
    // // DELETE /api/ventas/deleteFormalInformalParametro/{id}
    // public function deleteFormalInformalParametro(Request $request, $id)
    // {
    //     try {
    //         DB::selectOne('SELECT ventas.fn_formal_informal_parametros_eliminar(?::jsonb) AS id', [json_encode([
    //             'id' => (int) $id,
    //             'auditoria' => $this->contextoAuditoriaForense($request),
    //         ], JSON_UNESCAPED_UNICODE)]);
    //
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
    //     } catch (QueryException $e) {
    //         return $this->respuestaError($e, 'Error al eliminar la regla de formal/informal');
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar la regla de formal/informal', $e->getMessage()));
    //     }
    // }

    // Version 1.0
    // GET /api/ventas/formalInformalParametroAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UNA fila
    // (auditoria.logs_cambios). Sin columna nombre: el resumen lo arma la función con la línea y las edades (ELECTRO 21-65).
    public function formalInformalParametroAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_formal_informal_parametros_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_formal_informal_parametros_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la regla de formal/informal', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/formalInformalParametrosAuditoria?pagina&tamanio&busqueda — 11-nov-2026 (activo): la auditoría de TODA la
    // pestaña (el botón Auditoría de la cabecera): resumen + historial paginado de todas las reglas, también las borradas antes
    // y las desactivadas (auditoria.logs_cambios). La búsqueda también encuentra la línea de la regla.
    public function formalInformalParametrosAuditoria(Request $request)
    {
        try {
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_formal_informal_parametros_auditoria_tabla_resumen()');
            $eventos = DB::select('SELECT * FROM ventas.fn_formal_informal_parametros_auditoria_tabla_paginacion(?, ?, ?)', [$pagina, $tamanio, $busqueda]);

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total' => (int) ($eventos[0]->total_registros ?? 0),
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de las reglas de formal/informal', $e->getMessage()));
        }
    }

    // El código corto que levantó la función, traducido con MENSAJES_ERROR; si no es uno conocido, el genérico con el
    // detalle técnico. Las edades que se cruzan en una misma línea solo las conoce la función: ella arma ese texto.
    private function respuestaError(QueryException $e, string $generico)
    {
        if (preg_match('/RANGO_CRUZADO: (.+?)\s*(?:\R|\(SQL:|$)/', $e->getMessage(), $texto)) {
            return response()->json(RespuestaApi::returnResultado('error', $texto[1], null));
        }

        // El código se busca como palabra entera, nunca con strpos: así no se confunde con otro código más largo que lo
        // contenga (en Tipo de cliente, EDAD_INVALIDA está dentro de ANTIGUEDAD_INVALIDA).
        foreach (self::MENSAJES_ERROR as $codigo => $mensaje) {
            if (preg_match('/\b' . preg_quote($codigo, '/') . '\b/', $e->getMessage())) {
                return response()->json(RespuestaApi::returnResultado('error', $mensaje, null));
            }
        }

        return response()->json(RespuestaApi::returnResultado('error', $generico, $e->getMessage()));
    }
}
