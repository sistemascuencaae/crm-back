<?php

namespace App\Http\Controllers\openceo;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\openceo\Almacen;
use App\Models\openceo\Empleado;
use App\Models\openceo\Usuario;
use Illuminate\Support\Facades\DB;
use App\Models\openceo\Entidad;
use Illuminate\Http\Request;
use Exception;

class EntidadDynamoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'buscarEntidadEmpleado',
                'listAlmacenesPuntoVenta',
                'listAllMenusDynamo',
            ]
        ]);
    }

    public function buscarEntidadEmpleado($identificacion)
    {
        try {
            $cadena = substr($identificacion, 0, 10); // corta los 10 primeros digitos

            $data = Entidad::where('ent_identificacion', 'like', '%' . $cadena . '%')
                ->with('empleado', 'empleado.usuario')
                ->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAlmacenesPuntoVenta()
    {
        // En el Dynamo para listar no filtra por activos o inactivos, traen todo en el crear usuario
        try {
            $data = Almacen::with('puntoventa')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listAllMenusDynamo()
    {
        // Mismo SQL de Icreativa para listar los menús al rato de crear un usuario
        try {
            $data = DB::select("SELECT t.cme_id, t.cme_nombre FROM Cmenu t 
                                        WHERE (t.cme_id IN (SELECT DISTINCT (d.cme_padre) FROM Dmenu d WHERE d.cme_padre IS NOT null  and d.cme_id IS NOT NULL and d.dme_activo is true) 
					                    or t.cme_id not in(select distinct (d2.cme_id) from Dmenu d2 where d2.dme_activo is true and d2.cme_id is not null)) and t.cme_activo is true 
					                    order by t.cme_nombre
		                                ");

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addUsuarioEntidadEmpleado(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                $entidad = Entidad::where('ent_identificacion', $request->identificacion)
                    ->with('empleado', 'empleado.usuario')
                    ->first();

                // ** mapeado mapeo al crear **
                // ** Siempre voy a crear el usuario **
                $usuarioCreado = Usuario::create([
                    "usu_nombre" => $request->usu_nombre,
                    "usu_apellido" => $request->usu_apellido,
                    "usu_alias" => $request->usu_alias,
                    "usu_contrasena" => $request->usu_contrasena,
                    "usu_rol" => $request->usu_rol,
                    "usu_activo" => $request->usu_Activo,
                    "cme_id" => $request->cme_id,
                    "usu_vigencia_desde" => NULL,
                    "usu_vigencia_hasta" => NULL,
                    "locked" => false,
                    "pve_id" => $request->pve_id,
                    "usu_ip" => NULL,
                    "usu_administrador" => $request->usu_administrador,
                    "idi_id" => NULL,
                    "usu_tipo" => NULL,
                    "usu_tlf" => NULL,
                    "usu_base" => NULL,
                    "usu_alias_completo" => NULL,
                    "pti_id" => 8, // ! este 8 veo que va quemado en la tabla para todos los usuarios 
                    "usu_gestion" => NULL,
                    "usu_bloqueo" => NULL,
                    "usu_mostrar_bloqueo" => NULL,
                    "usu_habilitado" => false,
                    "usu_num_gestiones" => NULL,
                    "usu_tipo_analista" => $request->usu_tipo_analista,
                ]);

                if ($usuarioCreado) {
                }










                // validacion para ver si existe la entidad
                if (!$entidad) {
                    $respEnt = Entidad::create($request->all());
                }


                // validacion para ver si existe el empleado
                if (!$entidad->empleado) {
                    $respEmp = Empleado::create($request->all());
                } else {
                    // actualizo el nuevo usuario en el empleado
                }


                // validacion para ver si existe ya un usuario atado al empleado
                if (!$entidad->empleado->usuario) {
                    // actualizo el usu_id del empleado
                } else {
                    // si ya existe tengo que actualizar el usu_id del empleado
                }
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listEmpleadoByIdentificacion($identificacion)
    {
        try {
            $cadena = substr(trim($identificacion), 0, 10);

            $data = DB::selectOne("SELECT e.ent_identificacion, e.ent_nombres, e.ent_apellidos
                                    FROM entidad e
                                    WHERE SUBSTRING(TRIM(e.ent_identificacion), 1, 10) = ?
                                    LIMIT 1", [$cadena]);

            if (!$data) {
                $data = DB::selectOne("SELECT e.ent_identificacion, e.ent_nombres, e.ent_apellidos
                                        FROM cliente c
                                            JOIN entidad e ON e.ent_id = c.ent_id
                                        WHERE SUBSTRING(TRIM(c.cli_codigo), 1, 10) = ?
                                        LIMIT 1", [$cadena]);
            }

            if (!$data) {
                return response()->json(RespuestaApi::returnResultado('error', 'No se encontró ninguna entidad con esa identificación', null));
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
}
