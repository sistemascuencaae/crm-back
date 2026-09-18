<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Validator;

class DocumentacionClienteController extends Controller
{
    // Lista paginado los casos (documentación) de una agencia, para reimprimir documentos.
    // Respaldado por crm.fn_cliente_listar_casos_paginacion(p_agencia, p_pagina, p_tamanio, p_busqueda):
    // solo tipos de caso 149 y 151, ordenado por caso DESC. Paginación server-side (default 20).
    // busqueda (opcional) filtra por nº de caso / identificación / cliente (ILIKE).
    // La agencia la envía el front desde el usuario logueado (== crm.caso.codigo_agencia).
    public function listar_casos_clientes_by_agencia(Request $request)
    {
        try {
            $agencia  = trim((string) $request->query('codigo_agencia', ''));
            $pagina   = max((int) $request->query('pagina', 1), 1);
            $tamanio  = max((int) $request->query('tamanio', 20), 1);
            $busqueda = trim((string) $request->query('busqueda', ''));

            if ($agencia === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'La agencia es obligatoria', null));
            }

            $registros = DB::select('SELECT * FROM crm.fn_cliente_listar_casos_paginacion(?, ?, ?, ?)', [$agencia, $pagina, $tamanio, $busqueda]);
            $total = $registros[0]->total_registros ?? 0;

            return response()->json(RespuestaApi::returnResultado('success', 'Casos listados con éxito', [
                'registros' => $registros,
                'total' => (int) $total,
                'pagina' => $pagina,
                'tamanio' => $tamanio,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron listar los casos', $th->getMessage()));
        }
    }

    // Reimpresión de la ÚLTIMA Solicitud de Cupo generada EN ESTE CASO (crm.solicitudes_credito.caso_id).
    // Solo reimprime lo que se generó en el caso: NO genera en vivo ni guarda nada.
    // Si el caso no tiene ninguna solicitud generada -> tiene_solicitudes=false (el front lo avisa).
    public function reimprimir_solicitud_cupo(Request $request)
    {
        try {
            $casoId = (int) $request->query('caso_id', 0);

            if ($casoId <= 0) {
                return response()->json(RespuestaApi::returnResultado('error', 'El caso es obligatorio', null));
            }

            // cli_id de la última solicitud generada en ESTE caso (fecha DESC -> la más reciente).
            $row = DB::selectOne(
                'SELECT cli_id FROM crm.solicitudes_credito WHERE caso_id = ? ORDER BY fecha_impresion DESC LIMIT 1',
                [$casoId]
            );

            if (!$row) {
                return response()->json(RespuestaApi::returnResultado('success', 'El caso no tiene solicitudes generadas', [
                    'tiene_solicitudes' => false,
                ]));
            }

            $cliId = (int) $row->cli_id;

            // Snapshot completo (header + referencias/telefonos) de la última solicitud de este caso.
            $snap = DB::select(
                'SELECT * FROM crm.fn_cliente_solicitud_credito_listar_paginacion(?, ?, ?, ?, ?)',
                [$cliId, 1, 1, null, $casoId]
            );

            if (empty($snap)) {
                return response()->json(RespuestaApi::returnResultado('success', 'El caso no tiene solicitudes generadas', [
                    'tiene_solicitudes' => false,
                ]));
            }

            $r = $snap[0];
            $r->referencias = $r->referencias ? json_decode($r->referencias, true) : [];
            $r->telefonos = $r->telefonos ? json_decode($r->telefonos, true) : [];
            $r->direcciones = $r->direcciones ? json_decode($r->direcciones, true) : [];

            return response()->json(RespuestaApi::returnResultado('success', 'Reimpresión de la última solicitud del caso', [
                'tiene_solicitudes' => true,
                'origen' => 'snapshot',
                'registro' => $r,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudo reimprimir la solicitud de cupo', $th->getMessage()));
        }
    }

    // Datos del titular EN TIEMPO REAL (apellidos/nombres/identificación actuales) por identificación,
    // para el documento "Autorización de Tratamiento de Datos". Resuelve desde public.cliente ⨝ public.entidad
    // (mismo criterio de "cliente" que la reimpresión). Si no existe -> existe_cliente=false.
    public function datos_titular_autorizacion(Request $request)
    {
        try {
            $identificacion = trim((string) $request->query('identificacion', ''));

            if ($identificacion === '') {
                return response()->json(RespuestaApi::returnResultado('error', 'La identificación es obligatoria', null));
            }

            $row = DB::selectOne(
                'SELECT e.ent_apellidos, e.ent_nombres, e.ent_identificacion
                 FROM public.cliente c
                 INNER JOIN public.entidad e ON e.ent_id = c.ent_id
                 WHERE e.ent_identificacion = ? LIMIT 1',
                [$identificacion]
            );

            if (!$row) {
                return response()->json(RespuestaApi::returnResultado('success', 'El cliente no existe', [
                    'existe_cliente' => false,
                ]));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Datos del titular obtenidos', [
                'existe_cliente' => true,
                'apellidos' => $row->ent_apellidos,
                'nombres' => $row->ent_nombres,
                'identificacion' => $row->ent_identificacion,
            ]));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'No se pudieron obtener los datos del titular', $th->getMessage()));
        }
    }
}
