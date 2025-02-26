<?php

namespace App\Http\Controllers\formularios2;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\formularios2\Formularios;
use App\Models\crm\formularios2\FormulariosUsuarios;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Formulario2UsuariosController extends Controller
{
    public function __construct()
    {
    }

    public function listFormulariosUsuarios()
    {
        try {
            $data = Formularios::where('id', '!=', 1)
                ->with('formularioUsuarios.usuario')
                // ->orderBy('id', 'asc') // Esto ordena los formularios por el ID
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function addEditFormulariosUsuarios(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {

                // Primero, eliminamos los usuarios que están en el array usuariosEliminados
                if (isset($request->usuariosEliminados) && count($request->usuariosEliminados) > 0) {
                    foreach ($request->usuariosEliminados as $usuarioEliminado) {
                        // Eliminamos el usuario de la tabla 'formularios_usuarios' por 'usu_id'
                        FormulariosUsuarios::where('usu_id', $usuarioEliminado['usu_id'])
                            ->where('form_id', $usuarioEliminado['form_id'])
                            ->delete();
                    }
                }

                // Luego, insertamos los usuarios nuevos que están en el array usuariosNuevos
                if (isset($request->usuariosNuevos) && count($request->usuariosNuevos) > 0) {
                    foreach ($request->usuariosNuevos as $usuarioNuevo) {
                        // Insertamos el nuevo usuario en la tabla 'formularios_usuarios'
                        FormulariosUsuarios::create([
                            'form_id' => $usuarioNuevo['form_id'],
                            'usu_id' => $usuarioNuevo['usu_id'],
                        ]);
                    }
                }

                // Si todo va bien, obtenemos los formularios actualizados
                return Formularios::where('id', '!=', 1)
                    ->with('formularioUsuarios.usuario')
                    // ->orderBy('id', 'asc') // Esto ordena los formularios por el ID
                    ->get();
            });

            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listFormulariosByUsuId($usu_id)
    {
        try {
            $data = FormulariosUsuarios::where('usu_id', $usu_id)
                ->with('formulario')
                ->select('form_id', 'usu_id')
                ->distinct()
                ->get();

            if ($data) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'No tiene ningún formulario asignado', ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function listRespuestasByFormId($form_id)
    {
        try {
            $query = DB::selectOne("SELECT * FROM crm.af_obtener_datos_formulario9(?)", [$form_id]);

            $data = json_decode($query->resultado, true);  // Convierto para que me devuelva el array de las respuestas, porque la funcion de LEO devuelve un string

            if (count($data) > 0) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'Este formulario no tiene respuestas', ''));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

    public function af_cliente_dfactura($identificacion)
    {
        try {
            // 2 es el número de años que va a consultar
            $data = DB::select("SELECT * FROM public.af_cliente_dfactura2(?)", [$identificacion]);

            if (count($data) > 0) {
                return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
            } else {
                return response()->json(RespuestaApi::returnResultado('error', 'No existe facturas para este cliente.', ''));
            }
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $e->getMessage()));
        }
    }

}
