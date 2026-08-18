<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

// CRUD de Politicas (Cliente = pol_tipocli 1 / Proveedor = 2) sobre public.politica,
// la MISMA tabla que lee el ERP openceo.
//
// Todo el negocio vive en funciones crm.fn_politica_* (ver PLANES/POLITICAS): este
// controller solo valida la forma del request, arma el jsonb con el bloque
// 'auditoria' y traduce la respuesta. Las funciones auditan una vez por operacion
// en auditoria.logs_cambios, dentro del mismo commit.
class PoliticaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    // Listado paginado de la grilla. Trae ACTIVAS E INACTIVAS (el CRM si permite
    // reactivar; el JSF del ERP solo mostraba activas). tipo null = los dos universos.
    public function listPoliticasPaginado(Request $request)
    {
        try {
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));
            $tipo     = $request->query('tipo');
            $tipo     = ($tipo === null || $tipo === '') ? null : (int) $tipo;

            $registros = DB::select(
                'SELECT * FROM crm.fn_politica_listar_paginado(?, ?, ?, ?)',
                [$pagina, $tamanio, $busqueda, $tipo]
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

    // Ficha completa de una politica, para precargar el modal de editar.
    public function getPolitica($id)
    {
        try {
            $fila = DB::selectOne('SELECT crm.fn_politica_buscar(?) AS resultado', [(int) $id]);

            if (!$fila || !$fila->resultado) {
                return response()->json(RespuestaApi::returnResultado('error', 'La política no existe.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se obtuvo con éxito', json_decode($fila->resultado, true)));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo obtener la política', $e->getMessage()));
        }
    }

    public function addPolitica(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadPolitica($request);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_politica_crear(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'pol_id' => $respuesta['pol_id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo crear la política', $e->getMessage()));
        }
    }

    public function editPolitica(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), $this->reglasRequest());

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', $validator->errors()->first(), $validator->errors()));
            }

            $payload = $this->payloadPolitica($request);
            $payload['pol_id'] = (int) $id;
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_politica_modificar(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], [
                    'pol_id' => $respuesta['pol_id'] ?? null,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo actualizar la política', $e->getMessage()));
        }
    }

    // Activar / desactivar desde la grilla. La Principal NO se puede desactivar, y
    // al activar se revalida el nombre contra las activas del mismo tipo.
    public function cambiarEstadoPolitica(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'pol_activo' => 'required|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Estado inválido', $validator->errors()));
            }

            $payload = [
                'pol_id'     => (int) $id,
                'pol_activo' => $request->boolean('pol_activo'),
                'auditoria'  => $this->contextoAuditoriaForense($request),
            ];

            $resultado = DB::selectOne('SELECT crm.fn_politica_cambiar_estado(?::jsonb) AS resultado', [json_encode($payload)]);
            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], null));
            }

            return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cambiar el estado de la política', $e->getMessage()));
        }
    }

    // Auditoria de UNA politica (modal desde el listado). Resumen + historial
    // paginado, ambos desde auditoria.logs_cambios. Se gatea en el front con el
    // permiso crm.access.audit.
    public function politicaAuditoria(Request $request)
    {
        try {
            $polId    = (int) $request->query('pol_id', 0);
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 10), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($polId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'Política no válida', null));
            }

            $resumen = DB::selectOne('SELECT * FROM crm.fn_politica_auditoria_resumen(?)', [$polId]);
            $eventos = DB::select('SELECT * FROM crm.fn_politica_auditoria_listar_paginacion(?, ?, ?, ?)', [$polId, $pagina, $tamanio, $busqueda]);
            $total   = $eventos[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Auditoría cargada con éxito', [
                'resumen' => $resumen,
                'eventos' => $eventos,
                'total'   => (int) $total,
                'pagina'  => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo cargar la auditoría de la política', $e->getMessage()));
        }
    }

    // ------------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------------

    // Validacion de FORMA del request (tipos de dato). Las reglas de NEGOCIO viven
    // en crm.fn_politica_validaciones, no aqui.
    private function reglasRequest(): array
    {
        return [
            'pol_nombre'              => 'required|string|max:500',
            'pol_tipocli'             => 'required|integer|in:1,2',
            'pol_valordesde'          => 'required|numeric',
            'pol_valorhasta'          => 'required|numeric',
            'pol_lineacredito'        => 'required|integer',
            'pol_por_desc'            => 'required|numeric',
            // Modalidad: false = CONTADO, true = CREDITO. Es la que manda; el
            // % de contado pasa a ser consecuencia (en contado la funcion lo
            // fuerza a 100 y el formulario ni siquiera lo muestra).
            'pol_tipo_politica'       => 'required|boolean',
            'pol_por_pagconta'        => 'required|numeric',
            'pol_por_financia'        => 'nullable|numeric',
            'pol_por_propago'         => 'nullable|numeric',
            'pol_nropagos'            => 'nullable|integer',
            'pol_diasplazo'           => 'nullable|integer',
            'pol_diasgracia'          => 'nullable|integer',
            'pol_cuotasgratis'        => 'nullable|integer',
            'pol_agrupador'           => 'nullable|integer',
            'por_porcentaje_comision' => 'nullable|numeric',
            'pol_pordefecto'          => 'required|boolean',
            'pol_activo'              => 'required|boolean',
        ];
    }

    // Arma el jsonb que consumen las funciones. Los nulos se dejan pasar tal cual:
    // la funcion aplica su COALESCE y la regla de modalidad (contado/credito).
    private function payloadPolitica(Request $request): array
    {
        return [
            'pol_nombre'              => $request->input('pol_nombre'),
            'pol_tipocli'             => (int) $request->input('pol_tipocli'),
            'pol_valordesde'          => $request->input('pol_valordesde'),
            'pol_valorhasta'          => $request->input('pol_valorhasta'),
            'pol_lineacredito'        => $request->input('pol_lineacredito'),
            'pol_por_desc'            => $request->input('pol_por_desc'),
            'pol_tipo_politica'       => $request->boolean('pol_tipo_politica'),
            'pol_por_pagconta'        => $request->input('pol_por_pagconta'),
            'pol_por_financia'        => $request->input('pol_por_financia'),
            'pol_por_propago'         => $request->input('pol_por_propago'),
            'pol_nropagos'            => $request->input('pol_nropagos'),
            'pol_diasplazo'           => $request->input('pol_diasplazo'),
            'pol_diasgracia'          => $request->input('pol_diasgracia'),
            'pol_cuotasgratis'        => $request->input('pol_cuotasgratis'),
            'pol_agrupador'           => $request->input('pol_agrupador'),
            'por_porcentaje_comision' => $request->input('por_porcentaje_comision'),
            'pol_pordefecto'          => $request->boolean('pol_pordefecto'),
            'pol_activo'              => $request->boolean('pol_activo'),
        ];
    }

    // Contexto forense que las funciones guardan en auditoria.logs_cambios.
    // request_id enlaza todas las escrituras de una misma accion del usuario.
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
