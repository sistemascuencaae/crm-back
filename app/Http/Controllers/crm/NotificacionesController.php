<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Notificaciones;
use Illuminate\Http\Request;
use Exception;

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

    public function listAll()
    {
        try {
            $notificacion = Notificaciones::with('caso', 'tablero', 'user_destino')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notificacion));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
}