<?php

namespace App\Http\Controllers\crm;

use App\Events\NotificacionesCrmEvent;
use App\Http\Controllers\Controller;
use App\Models\crm\Notificacion;
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

}
