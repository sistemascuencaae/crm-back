<?php

namespace App\Http\Controllers\ventas;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

// Parametros del cotizador (ventas.parametros_cotizador): topes por concepto.
// El negocio vive en ventas.fn_parametros_cotizador_* (PLANES/COTIZADOR): este
// controller solo valida la forma, arma el jsonb con 'auditoria' y traduce la respuesta.
class ParametrosCotizadorController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Son pocos: se listan todos, sin paginar.
    public function listParametrosCotizador()
    {
        try {
            $registros = DB::select('SELECT * FROM ventas.fn_parametros_cotizador_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $registros));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los parámetros', $e->getMessage()));
        }
    }

    // Combo Tipo del formulario (ventas.tipos_parametros_cotizador: MARGEN, APORTE).
    public function listTiposParametrosCotizador()
    {
        try {
            $tipos = DB::select('SELECT * FROM ventas.fn_tipos_parametros_cotizador_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los tipos', $e->getMessage()));
        }
    }

    // Combo Tipo de tarjeta del formulario (25-oct: los conceptos TARJETA son los defaults de los plazos).
    public function listTiposTarjetaCotizador()
    {
        try {
            $tipos = DB::select('SELECT * FROM ventas.fn_tipo_tarjeta_listar()');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $tipos));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los tipos de tarjeta', $e->getMessage()));
        }
    }

    public function addParametroCotizador(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadParametro($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_cotizador_crear(?::jsonb) AS resultado', [json_encode($payload)]);
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

    public function editParametroCotizador(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadParametro($request);
            $payload['id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT ventas.fn_parametros_cotizador_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
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

    // Auditoria de UN parametro: resumen + historial paginado (auditoria.logs_cambios).
    public function parametroCotizadorAuditoria(Request $request)
    {
        try {
            $id       = (int) $request->query('id', 0);
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($id <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Parámetro no válido', null));
            }

            $resumen = DB::selectOne('SELECT * FROM ventas.fn_parametros_cotizador_auditoria_resumen(?)', [$id]);
            $eventos = DB::select('SELECT * FROM ventas.fn_parametros_cotizador_auditoria_listar_paginacion(?, ?, ?, ?)', [$id, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría del parámetro', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Solo la forma; las reglas de negocio van en ventas.fn_parametros_cotizador_validaciones.
    private function reglasRequest(): array
    {
        return [
            'nombre'          => 'required|string|max:255',
            'tipo_id'         => 'required|integer',
            'minimo'          => 'required|numeric',
            'maximo'          => 'required|numeric',
            'valor_margen'    => 'required|numeric',
            // Solo los conceptos TARJETA (la funcion los pide ahi y los ignora en los demas).
            'tipo_tarjeta_id' => 'nullable|integer',
            'plazo'           => 'nullable|integer',
            'meses_gracia'    => 'nullable|integer',
        ];
    }

    private function payloadParametro(Request $request): array
    {
        return [
            'nombre'          => $request->input('nombre'),
            'tipo_id'         => $request->input('tipo_id'),
            'minimo'          => $request->input('minimo'),
            'maximo'          => $request->input('maximo'),
            'valor_margen'    => $request->input('valor_margen'),
            'tipo_tarjeta_id' => $request->input('tipo_tarjeta_id'),
            'plazo'           => $request->input('plazo'),
            'meses_gracia'    => $request->input('meses_gracia'),
        ];
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios.
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
}
