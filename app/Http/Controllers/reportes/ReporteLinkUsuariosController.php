<?php

namespace App\Http\Controllers\reportes;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\reportes\ReporteLinkUsuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Exception;

class ReporteLinkUsuariosController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api', ['except' => ['listAllClientesDynamo']]);
    }

    public function listUsuariosByReporteId($id)
    {
        try {
            $data = ReporteLinkUsuario::where('reporte_link_id', $id)
                        ->with('usuario.Departamento', 'usuario.perfil_analista', 'usuario.perfil', 'usuario.almacen')->get();
    
            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addEditReporteLinkUsuarios(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
    
                // Primero, eliminamos los usuarios que están en el array usuariosEliminados
                if (isset($request->usuariosEliminados) && count($request->usuariosEliminados) > 0) {
                    foreach ($request->usuariosEliminados as $usuarioEliminado) {
                        // Eliminamos el usuario de la tabla 'reporte_link_usuarios' por 'user_id'
                        ReporteLinkUsuario::where('user_id', $usuarioEliminado['user_id'])
                            ->where('reporte_link_id', $usuarioEliminado['reporte_link_id'])
                            ->delete();
                    }
                }
    
                // Luego, insertamos los usuarios nuevos que están en el array usuariosNuevos
                if (isset($request->usuariosNuevos) && count($request->usuariosNuevos) > 0) {
                    foreach ($request->usuariosNuevos as $usuarioNuevo) {
                        // Verificamos si el usuario ya existe en la tabla 'formularios_usuarios'
                        $existingUser = ReporteLinkUsuario::where('user_id', $usuarioNuevo['user_id'])
                            ->where('reporte_link_id', $usuarioNuevo['reporte_link_id'])
                            ->first();
    
                        // Si no existe, lo creamos
                        if (!$existingUser) {
                            ReporteLinkUsuario::create([
                                'reporte_link_id' => $usuarioNuevo['reporte_link_id'],
                                'user_id' => $usuarioNuevo['user_id'],
                            ]);
                        }
                    }
                }
    
                // retornamos null porque no voy hacer cambios en mi tabla de reportes link, solo modifico mis usuarios y nada mas
                return null;
            });
    
            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }
    
}