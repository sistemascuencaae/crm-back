<?php

namespace App\Http\Controllers\crm;

use App\Events\ComentariosEvent;
use App\Http\Controllers\Controller;
use App\Models\crm\Audits;
use App\Models\crm\Comentarios;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ComentariosController extends Controller
{
    // public function __construct()
    // {
    //     $this->middleware('auth:api');
    // }

    public function listaComentarios(Request $request)
    {
        //$userId = $request->input('user_id');
        $caso_id = $request->input('caso_id');
        $data = DB::select('select * from crm.comentarios where caso_id = ' . $caso_id);
        //broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }


    public function guardarComentario(Request $request)
    {

        $coment = Comentarios::create($request->all());

        // START Bloque de código que genera un registro de auditoría manualmente
        $audit = new Audits();
        $audit->user_id = Auth::id();
        $audit->event = 'created';
        $audit->auditable_type = Comentarios::class;
        $audit->auditable_id = $coment->id;
        $audit->user_type = User::class;
        $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
        $audit->url = $request->fullUrl();
        // Establecer old_values y new_values
        $audit->old_values = json_encode($coment);
        $audit->new_values = json_encode([]);
        $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
        $audit->accion = 'addComentario';
        $audit->save();
        // END Auditoria

        $data = DB::select('select * from crm.comentarios where caso_id = ' . $coment->caso_id);
        broadcast(new ComentariosEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }



}