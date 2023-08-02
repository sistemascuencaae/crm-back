<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Models\crm\Notificaciones;
use Illuminate\Http\Request;

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
            $data = Notificaciones::with('caso', 'tablero', 'user_destino')->get();

            // echo json_encode($data);

            return response()->json($data);
            // $data = $request->all();
            // broadcast(new NotificacionesCrmEvent($data));
        } catch (\Throwable $th) {
            //throw $th;
        }
    }
}
