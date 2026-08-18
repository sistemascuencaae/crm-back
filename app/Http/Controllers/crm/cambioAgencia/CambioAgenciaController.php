<?php

namespace App\Http\Controllers\crm\cambioAgencia;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\UsuarioAlmacen;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CambioAgenciaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listAlmacenesDynamo()
    {
        try {
            $data = DB::select("SELECT
                                    alm.alm_id,
                                    alm.alm_nombre,
                                    pve.pve_id
                                from puntoventa pve
                                    join almacen alm on alm.alm_id = pve.alm_id
                                where pve.pve_numero = 501
                                    and pve.pve_activo = true
                                    and alm.alm_activo = true
                                order by alm.alm_nombre asc;
                            ");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    public function getVendedorByIdentificacion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'identificacion' => 'required|string|min:10'
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'La identificación debe tener al menos 10 dígitos', $validator->errors()));
            }

            $data = DB::selectOne("SELECT
                                    dynamo.usu_id AS usu_id_dynamo,
                                    crm.id AS usu_id_crm,
                                    COALESCE(dynamo.usu_alias, crm.usu_alias) AS usu_alias,
                                    COALESCE(dynamo.usu_nombre, crm.name) AS usu_nombre,
                                    COALESCE(dynamo.usu_apellido, crm.surname) AS usu_apellido,
                                    crm.almacen_actual_crm
                                FROM
                                    (SELECT usu_id, usu_alias, usu_nombre, usu_apellido
                                        FROM public.usuario
                                    WHERE cme_id = 345
                                        AND usu_activo = true
                                        AND usu_alias = ?) dynamo
                                FULL OUTER JOIN
                                    (SELECT u.id, u.usu_alias, u.name, u.surname, a.nombre AS almacen_actual_crm
                                        FROM crm.users u
                                        LEFT JOIN crm.agencia a ON a.codigo = u.alm_id::varchar
                                    WHERE u.profile_id = 38
                                        AND u.estado = true
                                        AND u.usu_alias = ?) crm
                                ON dynamo.usu_alias = crm.usu_alias
                            ", [$request->identificacion, $request->identificacion]);

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'El vendedor no existe o esta inactivo.', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editAgenciaVendedor(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'usu_id_dynamo' => 'nullable|integer',
                'usu_id_crm' => 'nullable|integer',
                'pve_id' => 'required|integer',
                'alm_id' => 'required|integer'
            ]);

            if ($validator->fails()) {
                return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->errors()));
            }

            $payload = $request->only(['pve_id', 'alm_id', 'usu_id_dynamo', 'usu_id_crm']);
            $payload['auditoria'] = $this->contextoAuditoriaForense($request);

            $resultado = DB::selectOne('SELECT crm.fn_vendedor_actualizar_agencia(?::jsonb) AS resultado', [json_encode($payload)]);

            $respuesta = json_decode($resultado->resultado, true);

            if ($respuesta['success']) {
                return response()->json(RespuestaApi::returnResultado('success', $respuesta['message'], null));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', $respuesta['message'], null));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la agencia', $e->getMessage()));
        }
    }

    private function contextoAuditoriaForense(Request $request): array
    {
        $u = auth('api')->user();

        return [
            'usuario_id' => $u->id ?? null,
            'usuario_login' => $u->usu_alias ?? null,
            'usuario_nombre' => $u ? trim(trim($u->surname ?? '') . ' ' . trim($u->name ?? '')) : null,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => (string) Str::uuid(),
        ];
    }
}

    // public function editAgenciaVendedor(Request $request)
    // {
    //     try {
    //         $validator = Validator::make($request->all(), [
    //             'usu_id_dynamo' => 'nullable|integer',
    //             'usu_id_crm' => 'nullable|integer',
    //             'pve_id' => 'required|integer',
    //             'alm_id' => 'required|integer'
    //         ]);

    //         if ($validator->fails()) {
    //             return response()->json(RespuestaApi::returnResultado('error', 'Datos inválidos', $validator->errors()));
    //         }

    //         DB::beginTransaction();

    //         if ($request->usu_id_dynamo) {
    //             DB::update("UPDATE public.usuario SET pve_id = ? WHERE usu_id = ?", [
    //                 $request->pve_id,
    //                 $request->usu_id_dynamo
    //             ]);
    //         }

    //         if ($request->usu_id_crm) {
    //             DB::update("UPDATE crm.users SET alm_id = ? WHERE id = ?", [
    //                 $request->alm_id,
    //                 $request->usu_id_crm
    //             ]);

    //             // 7. Elimina todos los permisos de las agencias del vendedor.
    //             UsuarioAlmacen::where('user_id', $request->usu_id_crm)->delete();

    //             // 8. Crea el permiso de la agencia.
    //             UsuarioAlmacen::create([
    //                 'alm_id' => $request->alm_id,
    //                 'user_id' => $request->usu_id_crm,
    //             ]);
    //         }

    //         DB::commit();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Agencia actualizada correctamente', null));
    //     } catch (Exception $e) {
    //         DB::rollBack();
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar la agencia', $e->getMessage()));
    //     }
    // }