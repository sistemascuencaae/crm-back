<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Notificaciones;
use Illuminate\Http\Request;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificacionesController extends Controller
{
    public function add(Request $request)
    {
        try {
            $data = $request->all();
            broadcast(new NotificacionesCrmEvent($data));
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
    public function list()
    {
        try {
        } catch (\Throwable $th) {
            //throw $th;
        }
    }

    // Este metodo ya no se ocupa porque se cambio este listNotificacionesCasoByUser_id
    // public function listByDepartamento($dep_id)
    // {
    //     $log = new Funciones();
    //     try {
    //         $notificacion = Notificaciones::with('caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'tablero', 'user_destino')
    //             ->where('dep_id', $dep_id)->orderBy('id', 'DESC')
    //             ->latest()->take(20)->get();

    //         $log->logInfo(NotaController::class, 'Se listo con exito las notificaciones del departamento con el ID: ' . $dep_id);

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
    //     } catch (Exception $e) {
    //         $log->logError(NotaController::class, 'Error al listar las notificaciones del departamento con el ID: ' . $dep_id, $e);

    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // hay que cambiar esto de las notificaciones a por user_id
    public function allByDepartamento($dep_id)
    {
        // $log = new Funciones();
        // try {
        //     $notificacion = Notificaciones::with('caso.req_caso', 'caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'tablero', 'user_destino')
        //         ->where('dep_id', $dep_id)->orderBy('id', 'DESC')->get();

        //     $log->logInfo(NotaController::class, 'Se listo con exito las notificaciones del departamento con el ID: ' . $dep_id);

        //     return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        // } catch (Exception $e) {
        //     $log->logError(NotaController::class, 'Error al listar las notificaciones del departamento con el ID: ' . $dep_id, $e);

        //     return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        // }

        try {
           // JGSJ usuario desde el backEnd
            $user = Auth::user();

            $notificacion = Notificaciones::with('caso.req_caso', 'caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen',
                                                    'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria',
                                                    'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'caso.agencia', 'tablero', 'user_destino')
                                            ->whereHas('caso.miembros', function ($query) use ($user) {
                                                $query->where('user_id', $user->id);
                                            })
                                            ->orderBy('id', 'DESC')
                                            ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editLeidoNotificacion(Request $request, $notificacion_id)
    {
        $log = new Funciones();
        try {
            $notificacion = $request->all();

            $data = DB::transaction(function () use ($notificacion, $notificacion_id, $request) {

                $notificacion = Notificaciones::findOrFail($notificacion_id);

                $notificacion->update([
                    "leido" => $request->leido,
                ]);

                return Notificaciones::where('id', $notificacion->id)
                    ->with('caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'caso.agencia', 'tablero', 'user_destino')
                    ->orderBy('id', 'DESC')->first();
            });

            $log->logInfo(NotaController::class, 'Se actualizo con exito la notificacion con el ID: ' . $notificacion_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al actualizar la notificacion con el ID: ' . $notificacion_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editLeidoAllNotificaciones(Request $request, $dep_id)
    {
        $log = new Funciones();
        try {
            $leido = $request->input('leido', true);

            $data = DB::transaction(function () use ($leido, $dep_id) {
                Notificaciones::where('dep_id', $dep_id)->update([
                    "leido" => $leido,
                ]);

                $notificacionesActualizadas = Notificaciones::with('caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'caso.agencia', 'tablero', 'user_destino')
                    ->where('dep_id', $dep_id)
                    ->orderBy('id', 'DESC')
                    ->get();

                return $notificacionesActualizadas;
            });

            $log->logInfo(NotaController::class, 'Se actualizo con exito todas las notificaciones del departamento con el ID: ' . $dep_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al actualizar todas las notificaciones del departamento con el ID: ' . $dep_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function listNotificacionesCasoByUser_id()
    {
        try {
            // JGSJ usuario desde el backEnd
            $user = Auth::user();

            // $notificacion = Notificaciones::with('caso.user', 'caso.userCreador', 'caso.clienteCrm', 'caso.resumen',
            //                                         'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria',
            //                                         'caso.Archivo', 'caso.estadodos', 'caso.req_caso', 'caso.agencia', 'tablero', 'user_destino')
            //                                 ->whereHas('caso.miembros', function ($query) use ($user) {
            //                                     $query->where('user_id', $user->id);
            //                                 })
            //                                 ->orderBy('id', 'DESC')
            //                                 ->latest()->take(20)->get();

            $notificacion = Notificaciones::with('caso.miembros', 'tablero', 'user_destino')
                                            ->whereHas('caso.miembros', function ($query) use ($user) {
                                                $query->where('user_id', $user->id);
                                            })
                                            ->orderBy('id', 'DESC')
                                            ->latest()->take(10)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        } catch (Exception $e) {

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}
