<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Galeria;
use App\Models\crm\TutorialUsuario;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class TutorialUsuarioController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listTutorialesByUserId($user_id)
    {
        try {
            $data = TutorialUsuario::where('user_id', $user_id)
                        ->with('archivos', 'galerias')
                        ->get();
    
            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function addEditTutorialUsuarios(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
            
                // Eliminamos los usuarios
                if (!empty($request->usuariosEliminados)) {
                    foreach ($request->usuariosEliminados as $usuarioEliminado) {
                        $query = TutorialUsuario::where('user_id', $usuarioEliminado['user_id']);
                    
                        if (!empty($usuarioEliminado['galeria_id'])) {
                            $query->where('galeria_id', $usuarioEliminado['galeria_id']);
                        } elseif (!empty($usuarioEliminado['archivo_id'])) {
                            $query->where('archivo_id', $usuarioEliminado['archivo_id']);
                        }
                    
                        $query->delete();
                    }
                }
            
                // Insertamos los usuarios nuevos
                if (!empty($request->usuariosNuevos)) {
                    foreach ($request->usuariosNuevos as $usuarioNuevo) {
                        $query = TutorialUsuario::where('user_id', $usuarioNuevo['user_id']);
                    
                        if (!empty($usuarioNuevo['galeria_id'])) {
                            $query->where('galeria_id', $usuarioNuevo['galeria_id']);
                        } elseif (!empty($usuarioNuevo['archivo_id'])) {
                            $query->where('archivo_id', $usuarioNuevo['archivo_id']);
                        }
                    
                        $existing = $query->first();
                    
                        if (!$existing) {
                            TutorialUsuario::create([
                                'user_id' => $usuarioNuevo['user_id'],
                                'galeria_id' => $usuarioNuevo['galeria_id'] ?? null,
                                'archivo_id' => $usuarioNuevo['archivo_id'] ?? null,
                            ]);
                        }
                    }
                }
            
                return null;
            });
        
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    
    public function listUsuariosByArchivoGaleriaId(Request $request)
    {
        try {
            if ($request->galeria_id) {
                $data = TutorialUsuario::where('galeria_id', $request->galeria_id)
                            ->with('usuario.Departamento', 'usuario.perfil_analista', 'usuario.perfil', 'usuario.almacen')->get();
            } else {
                $data = TutorialUsuario::where('archivo_id', $request->archivo_id)
                            ->with('usuario.Departamento', 'usuario.perfil_analista', 'usuario.perfil', 'usuario.almacen')->get();
            }
    
            // Si la transacción fue exitosa, respondemos con un mensaje de éxito
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (Exception $e) {
            // En caso de error, respondemos con un mensaje de error
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}