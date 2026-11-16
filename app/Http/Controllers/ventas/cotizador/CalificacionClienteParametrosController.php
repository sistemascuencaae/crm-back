<?php

namespace App\Http\Controllers\ventas\cotizador;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Http\Traits\AuditoriaForenseTrait;
use App\Models\ventas\cotizador\CalificacionClienteParametro;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

// Version 1.0
// Ventas > Parámetros de calificación (V05, ventas.calificacion_cliente_parametros): a la izquierda las políticas activas de
// clientes (public.politica, la tabla del ERP) y a la derecha los parámetros de la elegida, que se guardan todos juntos; desde
// ahí se clonan a otras políticas. Sin eliminar: quitar un parámetro guardado = activo false (ventas.cproforma.calificacion_id
// apunta a estos id).
// El negocio vive en ventas.fn_calificacion_cliente_parametros_* (PLANES/POLITICAS/produccion_politicas_parametros_calificacion.sql
// y produccion_parametros_calificacion_pantalla.sql): este controller valida la forma, arma el jsonb con los campos del modelo y
// el bloque 'auditoria', y devuelve tal cual el {success, message} de la función. La auditoría sigue siendo la de la política
// (un evento POLITICAS en public.politica por cada Guardar y por cada destino de un Clonar): la pantalla la lee con getPolitica
// y politicaAuditoria de openceo/PoliticaController (Dynamo), que no cambian.
// Viene de los 5 métodos de V05 de openceo/PoliticaController (Fase 4 del plan del cotizador, 11-nov-2026), con los mismos
// mensajes y la misma lógica; las rutas pasaron de api/crm a api/ventas.
class CalificacionClienteParametrosController extends Controller
{
    use AuditoriaForenseTrait;

    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Version 1.0
    // GET /api/ventas/listPoliticasCalificacionClienteParametros?pagina&tamanio&busqueda — la lista de la izquierda: solo las
    // políticas ACTIVAS de clientes, con su modalidad (credito true = CRÉDITO), paginadas y buscadas en el servidor. Antes
    // PoliticaController@listPoliticasActivasClientes.
    public function listPoliticasCalificacionClienteParametros(Request $request)
    {
        try {
            // Topes: tamanio 1..100 y pagina sin desbordar el OFFSET (integer) de la funcion.
            $pagina   = min(max((int) $request->query('pagina', 1), 1), 1000000);
            $tamanio  = min(max((int) $request->query('tamanio', 20), 1), 100);
            $busqueda = trim((string) $request->query('busqueda', ''));

            $registros = DB::select(
                'SELECT * FROM crm.fn_politica_activas_clientes_listar_paginado(?, ?, ?)',
                [$pagina, $tamanio, $busqueda]
            );

            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', [
                'registros' => $registros,
                'total'     => (int) $total,
                'pagina'    => $pagina,
                'tamanio'   => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar las políticas', $e->getMessage()));
        }
    }

    // Version 1.0
    // GET /api/ventas/listCatalogosCalificacionClienteParametros — los combos de la grilla (tipo de cliente, Formal/Informal y
    // comportamiento) en su orden y con su activo, más con qué actividad y comportamiento nace una fila nueva (por_defecto); la
    // pantalla solo ofrece los activos. Antes PoliticaController@catalogosParametrosCalificacion.
    public function listCatalogosCalificacionClienteParametros()
    {
        try {
            $fila = DB::selectOne('SELECT ventas.fn_calificacion_cliente_parametros_catalogos_listar() AS resultado');

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron cargar los tipos de cliente, actividades y comportamientos', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/guardarCalificacionClienteParametros/{pol_id} body {parametros} — guarda todos los parámetros de UNA
    // política de clientes (solo los nuevos y los cambiados, todo o nada) y devuelve cómo quedaron. Quitar uno guardado = activo
    // false (la función nunca borra). Antes PoliticaController@guardarParametrosPolitica.
    public function guardarCalificacionClienteParametros(Request $request, $polId)
    {
        try {
            // Los id con tope de integer de PostgreSQL: uno mayor revienta el cast en SQL.
            $validator = Validator::make(['pol_id' => $polId] + $request->all(), [
                'pol_id'                                 => 'required|integer|min:1|max:2147483647',
                'parametros'                             => 'present|array',
                'parametros.*'                           => 'array',
                'parametros.*.id'                        => 'nullable|integer|max:2147483647',
                'parametros.*.tipo_cliente_id'           => 'required|integer|min:1|max:2147483647',
                'parametros.*.actividad_cliente_id'      => 'required|integer|min:1|max:2147483647',
                'parametros.*.comportamiento_cliente_id' => 'required|integer|min:1|max:2147483647',
                'parametros.*.margen_financiero_general' => 'required|numeric',
                'parametros.*.incremento'                => 'required|numeric',
                'parametros.*.predeterminado'            => 'required|boolean',
                'parametros.*.activo'                    => 'required|boolean',
            ], $this->mensajesParametros());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'pol_id'     => (int) $polId,
                'parametros' => $this->payloadParametros($request),
                'auditoria'  => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_calificacion_cliente_parametros_guardar_lote(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'parametros' => $respuesta['parametros'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron guardar los parámetros de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/vistaPreviaClonarCalificacionClienteParametros/{pol_id} body {pol_ids_destino} — Clonar parámetros: qué
    // pasaría en cada política destino (solo lectura), con las mismas validaciones que el clonado. Antes
    // PoliticaController@vistaPreviaClonarParametrosPolitica.
    public function vistaPreviaClonarCalificacionClienteParametros(Request $request, $polId)
    {
        try {
            $validator = Validator::make(['pol_id' => $polId] + $request->only('pol_ids_destino'),
                $this->reglasClonarParametros(), $this->mensajesClonarParametros());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            // Los id ya se validaron como enteros: el arreglo de PostgreSQL se arma sin riesgo.
            $destinos = '{' . implode(',', $this->idsDestinoClonar($request)) . '}';

            $resultado = DB::selectOne(
                'SELECT ventas.fn_calificacion_cliente_parametros_clonar_vista_previa(?, ?::integer[]) AS resultado',
                [(int) $polId, $destinos]
            );
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'origen'   => $respuesta['origen'] ?? null,
                    'destinos' => $respuesta['destinos'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo preparar el clonado de los parámetros de calificación', $e->getMessage()));
        }
    }

    // Version 1.0
    // POST /api/ventas/clonarCalificacionClienteParametros/{pol_id} body {pol_ids_destino} — Clonar parámetros: cada destino
    // queda con exactamente los parámetros activos guardados de la política {pol_id}. Todo o nada. Antes
    // PoliticaController@clonarParametrosPolitica.
    public function clonarCalificacionClienteParametros(Request $request, $polId)
    {
        try {
            $validator = Validator::make(['pol_id' => $polId] + $request->only('pol_ids_destino'),
                $this->reglasClonarParametros(), $this->mensajesClonarParametros());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = [
                'pol_id_origen'   => (int) $polId,
                'pol_ids_destino' => $this->idsDestinoClonar($request),
                'auditoria'       => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT ventas.fn_calificacion_cliente_parametros_clonar(?::jsonb) AS resultado', [json_encode($payload, JSON_UNESCAPED_UNICODE)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'destinos' => $respuesta['destinos'] ?? [],
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron clonar los parámetros de calificación', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Ayudantes
    // ------------------------------------------------------------------------

    // Cada fila del pedido con su id (sin id = nueva; con id = existente, la función verifica que sea de esta política) y los
    // campos del modelo, menos pol_id: la política es la de la URL y vale para todo el lote.
    private function payloadParametros(Request $request): array
    {
        $campos = array_merge(['id'], array_values(array_diff((new CalificacionClienteParametro())->getFillable(), ['pol_id'])));

        return array_map(fn ($p) => Arr::only((array) $p, $campos), array_values((array) $request->input('parametros', [])));
    }

    // Mensajes de la forma de guardarCalificacionClienteParametros; el negocio lo valida
    // ventas.fn_calificacion_cliente_parametros_validaciones con sus propios textos.
    private function mensajesParametros(): array
    {
        return [
            'pol_id.required'                                 => 'No se recibió la política.',
            'pol_id.integer'                                  => 'La política no es válida.',
            'pol_id.min'                                      => 'La política no es válida.',
            'pol_id.max'                                      => 'La política no es válida.',
            'parametros.present'                              => 'No se recibieron los parámetros de calificación.',
            'parametros.array'                                => 'Los parámetros de calificación no tienen un formato válido.',
            'parametros.*.array'                              => 'Los parámetros de calificación no tienen un formato válido.',
            'parametros.*.id.integer'                         => 'Uno de los parámetros de calificación no es válido.',
            'parametros.*.id.max'                             => 'Uno de los parámetros de calificación no es válido.',
            'parametros.*.tipo_cliente_id.required'           => 'El tipo de cliente del parámetro de calificación es obligatorio.',
            'parametros.*.tipo_cliente_id.integer'            => 'El tipo de cliente del parámetro de calificación no es válido.',
            'parametros.*.tipo_cliente_id.min'                => 'El tipo de cliente del parámetro de calificación no es válido.',
            'parametros.*.tipo_cliente_id.max'                => 'El tipo de cliente del parámetro de calificación no es válido.',
            'parametros.*.actividad_cliente_id.required'      => 'La actividad del parámetro de calificación es obligatoria.',
            'parametros.*.actividad_cliente_id.integer'       => 'La actividad del parámetro de calificación no es válida.',
            'parametros.*.actividad_cliente_id.min'           => 'La actividad del parámetro de calificación no es válida.',
            'parametros.*.actividad_cliente_id.max'           => 'La actividad del parámetro de calificación no es válida.',
            'parametros.*.comportamiento_cliente_id.required' => 'El comportamiento del parámetro de calificación es obligatorio.',
            'parametros.*.comportamiento_cliente_id.integer'  => 'El comportamiento del parámetro de calificación no es válido.',
            'parametros.*.comportamiento_cliente_id.min'      => 'El comportamiento del parámetro de calificación no es válido.',
            'parametros.*.comportamiento_cliente_id.max'      => 'El comportamiento del parámetro de calificación no es válido.',
            'parametros.*.margen_financiero_general.required' => 'El margen financiero general del parámetro de calificación es obligatorio.',
            'parametros.*.margen_financiero_general.numeric'  => 'El margen financiero general del parámetro de calificación debe ser numérico.',
            'parametros.*.incremento.required'                => 'El incremento del parámetro de calificación es obligatorio.',
            'parametros.*.incremento.numeric'                 => 'El incremento del parámetro de calificación debe ser numérico.',
            'parametros.*.predeterminado.required'            => 'Falta indicar si el parámetro de calificación es predeterminado.',
            'parametros.*.predeterminado.boolean'             => 'El valor de predeterminado del parámetro de calificación no es válido.',
            'parametros.*.activo.required'                    => 'Falta indicar si el parámetro de calificación está activo.',
            'parametros.*.activo.boolean'                     => 'El valor de activo del parámetro de calificación no es válido.',
        ];
    }

    // Forma del pedido de Clonar parámetros (vista previa y clonado); el negocio lo valida
    // ventas.fn_calificacion_cliente_parametros_clonar_validaciones.
    private function reglasClonarParametros(): array
    {
        return [
            'pol_id'            => 'required|integer|min:1|max:2147483647',
            'pol_ids_destino'   => 'required|array|max:200',
            'pol_ids_destino.*' => 'required|integer|min:1|max:2147483647',
        ];
    }

    private function mensajesClonarParametros(): array
    {
        return [
            'pol_id.required'            => 'No se recibió la política origen.',
            'pol_id.integer'             => 'La política origen no es válida.',
            'pol_id.min'                 => 'La política origen no es válida.',
            'pol_id.max'                 => 'La política origen no es válida.',
            'pol_ids_destino.required'   => 'Elija al menos una política destino.',
            'pol_ids_destino.array'      => 'Las políticas destino no tienen un formato válido.',
            'pol_ids_destino.max'        => 'Puede clonar a 200 políticas como máximo a la vez.',
            'pol_ids_destino.*.required' => 'Una de las políticas destino no es válida.',
            'pol_ids_destino.*.integer'  => 'Una de las políticas destino no es válida.',
            'pol_ids_destino.*.min'      => 'Una de las políticas destino no es válida.',
            'pol_ids_destino.*.max'      => 'Una de las políticas destino no es válida.',
        ];
    }

    // Los pol_id destino del pedido como enteros (ya validados).
    private function idsDestinoClonar(Request $request): array
    {
        return array_values(array_map('intval', (array) $request->input('pol_ids_destino', [])));
    }
}
