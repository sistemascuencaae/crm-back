<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Models\crm\Notificacion;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    public function add(Request $request)
    {
        
      try {
        $data = Notificacion::create();




      } catch (\Throwable $th) {
        //throw $th;
      }
    }

}
