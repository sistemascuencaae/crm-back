<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\CotizadorParametro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Ventas > Parámetros cotizador (V02, ventas.cotizador_parametros): los topes por concepto (MARGEN, APORTE o TARJETA; un
// concepto TARJETA es el default de un plazo de tarjeta). Sin eliminar: las cabeceras de margen y de aporte apuntan a estos id.
// El negocio vive en ventas.fn_cotizador_parametros_* (PLANES/COTIZADOR/produccion_cotizador_parametros.sql): este controller
// valida la forma, arma el jsonb con los campos del modelo y el bloque 'auditoria', y devuelve tal cual el {success, message}
// de la función. Viene de ventas/ParametrosCotizadorController (Fase 1 del plan del cotizador, 10-nov-2026), con los mismos
// mensajes y las mismas funciones SQL.
class CotizadorParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listCotizadorParametros — la grilla, con el nombre del tipo y del tipo de tarjeta. Son pocos: se
    // listan todos, sin paginar.
    public function listCotizadorParametros()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_cotizador_parametros_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los parámetros', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listCombosCotizadorParametros — los combos del formulario en una sola llamada (id, nombre): el Tipo del
    // concepto (ventas.tipo_parametro_cotizador: MARGEN, APORTE y TARJETA) y el Tipo de tarjeta de los conceptos TARJETA
    // (ventas.tipo_tarjeta, solo las activas). Antes eran listTiposParametrosCotizador y listTiposTarjetaCotizador.
    public function listCombosCotizadorParametros()
    {
        try {
            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'tipos' => DB::select('SELECT * FROM ventas.fn_tipo_parametro_cotizador_listar()'),
                'tipos_tarjeta' => DB::select('SELECT * FROM ventas.fn_tipo_tarjeta_listar()'),
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los combos', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/addCotizadorParametro — crea un concepto. Devuelve su id.
    public function addCotizadorParametro(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadParametro($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cotizador_parametros_crear(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear el parámetro', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/editCotizadorParametro/{id} — modifica un concepto. Devuelve su id.
    public function editCotizadorParametro(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadParametro($request);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_cotizador_parametros_modificar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'id' => $respuesta['id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo actualizar el parámetro', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/cotizadorParametroAuditoria?id&pagina&tamanio&busqueda — resumen + historial paginado de UN concepto
    // (auditoria.logs_cambios).
    public function cotizadorParametroAuditoria(Request $request)
    {
        try {
            $id = (int) $request->query('id', 0);
            $pagina = max((int) $request->query('pagina', 1), 1);
            $tamanio = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_cotizador_parametros_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_cotizador_parametros_auditoria_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);

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

    // Solo la forma; las reglas de negocio y sus mensajes van en ventas.fn_cotizador_parametros_validaciones.
    private function reglasRequest(): array
    {
        return [
            'nombre' => 'required|string|max:255',
            'tipo_id' => 'required|integer',
            'minimo' => 'required|numeric',
            'maximo' => 'required|numeric',
            'valor_margen' => 'required|numeric',
            // Solo los conceptos TARJETA (la función los pide ahí y los ignora en los demás).
            'tipo_tarjeta_id' => 'nullable|integer',
            'plazo' => 'nullable|integer',
            'meses_gracia' => 'nullable|integer',
        ];
    }

    // Los campos del modelo, como los manda la pantalla; el que no llega va en null.
    private function payloadParametro(Request $request): array
    {
        $campos = (new CotizadorParametro())->getFillable();

        return array_combine($campos, array_map(fn ($campo) => $request->input($campo), $campos));
    }
}
