<?php

namespace App\Http\Controllers\configuracion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\CHorario;
use App\Models\configuracion\DHorario;
use App\Models\configuracion\UsuarioCHorario;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Exception;

class HorarioController extends Controller
{
    public function __construct()
    {
    }
    
    public function listAllHorarios()
    {
        try {
            $data = CHorario::selectRaw("*, (CASE WHEN crm.chorario.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                                ->orderBy("nombre", "asc")->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function addCDHorario(Request $request)
    {
        try {
            $data = DB::transaction(function () use ($request) {
                // Crear CHorario
                $cHorario = CHorario::create([
                    'nombre' => $request->nombre,
                    'estado' => $request->estado
                ]);
            
                // Guardar DHorario
                if (!empty($request->input('dhorario'))) {
                    foreach ($request->input('dhorario') as $item) {
                        DHorario::create([
                            'chorario_id' => $cHorario->id,
                            'dia'         => $item['dia'],
                            'hora_inicio' => $item['hora_inicio'],
                            'hora_fin'    => $item['hora_fin'],
                            'estado'      => $item['estado'],
                        ]);
                    }
                }
            
                return CHorario::selectRaw("*, (CASE WHEN crm.chorario.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                                ->orderBy("nombre", "asc")->get();
            });
        
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    public function listDhorarioById($id) {
        try {
            $data = DHorario::where('chorario_id', $id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function editCDHorario(Request $request, $chorario_id)
    {
        try {
            $data = DB::transaction(function () use ($request, $chorario_id) {

                CHorario::where('id', $chorario_id)->update([
                    'nombre' => $request->nombre,
                    'estado' => $request->estado
                ]);

                // Elimina los horarios anteriores
                DHorario::where('chorario_id', $chorario_id)->delete();

                // Guardar DHorario
                if (!empty($request->input('dhorario'))) {
                    foreach ($request->input('dhorario') as $item) {
                        DHorario::create([
                            'chorario_id' => $chorario_id,
                            'dia'         => $item['dia'],
                            'hora_inicio' => $item['hora_inicio'],
                            'hora_fin'    => $item['hora_fin'],
                            'estado'      => $item['estado'],
                        ]);
                    }
                }
            
                return CHorario::selectRaw("*, (CASE WHEN crm.chorario.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                                ->orderBy("nombre", "asc")->get();
            });
        
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }

    public function deleteCDHorario($chorario_id)
    {
        try {
            $data = DB::transaction(function () use ($chorario_id) {

                // Elimina los horarios anteriores de dhorario
                DHorario::where('chorario_id', $chorario_id)->delete();

                // Elimina los horarios anteriores de dhorario
                CHorario::where('id', $chorario_id)->delete();

                return CHorario::selectRaw("*, (CASE WHEN crm.chorario.estado = false THEN 'Inactivo' ELSE 'Activo' END) AS estado2")
                                ->orderBy("nombre", "asc")->get();
            });
        
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito.', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e->getMessage()));
        }
    }






    // ASIGNAR UN HORARIO A UN USUARIO

    public function listHorariosActivos()
    {
        try {
            $data = CHorario::where("estado", true)
                            ->with([
                                'dhorario' => function ($query) {
                                    $query->selectRaw("*,
                                                        CASE 
                                                            WHEN crm.dhorario.estado = false THEN 'Inactivo'
                                                            ELSE 'Activo'
                                                        END AS estado2,
                                                        CASE 
                                                            WHEN crm.dhorario.dia = 0 THEN 'Domingo'
                                                            WHEN crm.dhorario.dia = 1 THEN 'Lunes'
                                                            WHEN crm.dhorario.dia = 2 THEN 'Martes'
                                                            WHEN crm.dhorario.dia = 3 THEN 'Miércoles'
                                                            WHEN crm.dhorario.dia = 4 THEN 'Jueves'
                                                            WHEN crm.dhorario.dia = 5 THEN 'Viernes'
                                                            WHEN crm.dhorario.dia = 6 THEN 'Sábado'
                                                            ELSE 'Desconocido'
                                                        END AS dia2
                                                    ");
                                }
                            ])
                            ->orderBy("nombre", "asc")
                            ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function getHorarioUsuario($user_id){
        try {
            $usuarioHorario = UsuarioCHorario::where('user_id', $user_id)->first();

            if ($usuarioHorario) {
                $horario = CHorario::where('id', $usuarioHorario->chorario_id)
                                    ->with([
                                                    'dhorario' => function ($query) {
                                                        $query->selectRaw("*,
                                                                            CASE 
                                                                                WHEN crm.dhorario.estado = false THEN 'Inactivo'
                                                                                ELSE 'Activo'
                                                                            END AS estado2,
                                                                            CASE 
                                                                                WHEN crm.dhorario.dia = 0 THEN 'Domingo'
                                                                                WHEN crm.dhorario.dia = 1 THEN 'Lunes'
                                                                                WHEN crm.dhorario.dia = 2 THEN 'Martes'
                                                                                WHEN crm.dhorario.dia = 3 THEN 'Miércoles'
                                                                                WHEN crm.dhorario.dia = 4 THEN 'Jueves'
                                                                                WHEN crm.dhorario.dia = 5 THEN 'Viernes'
                                                                                WHEN crm.dhorario.dia = 6 THEN 'Sábado'
                                                                                ELSE 'Desconocido'
                                                                            END AS dia2
                                                                        ");
                                                    }
                                                ])
                                    ->first();
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $horario));

            } else {
                return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', null));
            }

        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function editUserHorario(Request $request)
{
    try {
        return DB::transaction(function () use ($request) {
            $usu_existe = UsuarioCHorario::where('user_id', $request->user_id)
                ->first();

            if ($usu_existe) {
                $usu_existe->update([
                    'chorario_id' => $request->chorario_id,
                ]);

                return response()->json(RespuestaApi::returnResultado('success', 'Se actualizó con éxito', $usu_existe));
            } else {
                $nuevo = UsuarioCHorario::create([
                    'user_id' => $request->user_id,
                    'chorario_id' => $request->chorario_id,
                ]);

                return response()->json(RespuestaApi::returnResultado('success', 'Se guardó con éxito', $nuevo));
            }
        });
    } catch (Exception $e) {
        return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
    }
}


}

