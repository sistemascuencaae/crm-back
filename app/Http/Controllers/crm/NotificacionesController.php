<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Notificaciones;
use Illuminate\Http\Request;
use Exception;
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

    public function listByDepartamento($dep_id)
    {
        try {
            $notificacion = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
                ->where('dep_id', $dep_id)->orderBy('id', 'DESC')
                ->latest()->take(10)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function allByDepartamento($dep_id)
    {
        try {
            $notificacion = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
                ->where('dep_id', $dep_id)->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function editLeidoNotificacion(Request $request, $notificacion_id)
    {
        try {
            $notificacion = $request->all();

            $data = DB::transaction(function () use ($notificacion, $notificacion_id, $request) {

                $notificacion = Notificaciones::findOrFail($notificacion_id);

                $notificacion->update([
                    "leido" => $request->leido,
                ]);

                return Notificaciones::where('id', $notificacion->id)
                    ->with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
                    ->orderBy('id', 'DESC')->first();
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function editLeidoAllNotificaciones(Request $request, $dep_id)
    {
        try {
            $leido = $request->input('leido', true);

            $data = DB::transaction(function () use ($leido, $dep_id) {
                Notificaciones::where('dep_id', $dep_id)->update([
                    "leido" => $leido,
                ]);

                $notificacionesActualizadas = Notificaciones::with('caso', 'caso.user', 'caso.userCreador', 'caso.entidad', 'caso.resumen', 'caso.tareas', 'caso.actividad', 'caso.Etiqueta', 'caso.miembros.usuario.departamento', 'caso.Galeria', 'caso.Archivo', 'tablero', 'user_destino')
                    ->where('dep_id', $dep_id)
                    ->orderBy('id', 'DESC')
                    ->get();

                return $notificacionesActualizadas;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
