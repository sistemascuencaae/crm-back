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

// Cotizador > Parámetros tipo calificación cliente, pestaña Tipo de cliente (ventas.tipo_cliente_parametros): el rango de
// score de cada tipo. El negocio vive en ventas.fn_tipo_cliente_parametros_* (PLANES/COTIZADOR/produccion_tipo_cliente_parametros.sql):
// este controller arma el jsonb con los campos del modelo y el bloque 'auditoria', y traduce el código que levanta la función.
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
        'ANTIGUEDAD_INVALIDA' => 'La antigüedad mínima y la máxima deben ser meses enteros.',
        'ANTIGUEDAD_RANGO_INVERTIDO' => 'La antigüedad mínima no puede ser mayor que la máxima.',
        'CONDICION_INVALIDA' => 'Acreedores, NN y TC solo aceptan SI o NO; vacío = no importa.',
        'REQUIERE_PERFIL' => 'El perfil de riesgo es obligatorio.',
        'PERFIL_INVALIDO' => 'El perfil de riesgo no es válido.',
        'REQUIERE_TIPO_CLIENTE' => 'El tipo de cliente (NUEVO o REITERATIVO) es obligatorio.',
        'TIPO_CLIENTE_INVALIDO' => 'El tipo de cliente debe ser NUEVO o REITERATIVO.',
    ];

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listTipoClienteParametros?pagina&tamanio&busqueda — la grilla de la pestaña.
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

    // Version 1.0
    // POST /api/ventas/guardarTipoClienteParametros — las filas nuevas (sin id) y las editadas, todo o nada.
    // De cada fila pasan solo el id y los campos del modelo.
    public function guardarTipoClienteParametros(Request $request)
    {
        try {
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

    // Version 1.0
    // DELETE /api/ventas/deleteTipoClienteParametro/{id}
    public function deleteTipoClienteParametro(Request $request, $id)
    {
        try {
            DB::selectOne('SELECT ventas.fn_tipo_cliente_parametros_eliminar(?::jsonb) AS id', [json_encode([
                'id' => (int) $id,
                'auditoria' => $this->contextoAuditoriaForense($request),
            ], JSON_UNESCAPED_UNICODE)]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', null));
        } catch (QueryException $e) {
            return $this->respuestaError($e, 'Error al eliminar el tipo de cliente');
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al eliminar el tipo de cliente', $e->getMessage()));
        }
    }

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
