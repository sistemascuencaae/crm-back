<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\Nota;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotaController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function addNota(Request $request)
    {
        try {
            $nota = Nota::create($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'created';
            $audit->auditable_type = Nota::class;
            $audit->auditable_id = $nota->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($nota);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'addNota';
            $audit->save();
            // END Auditoria

            $data = DB::select('select * from crm.nota where caso_id =' . $request->caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listNotaByCasoId($caso_id)
    {
        try {
            $notas = Nota::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notas));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateNota(Request $request, $id)
    {
        try {
            $nota = Nota::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $nota;
            $audit->old_values = json_encode($valorAntiguo);

            // $nota->update([
            //     "nombre" => $request->nombre,
            // ]);

            $nota->update($request->all());

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit->user_id = Auth::id();
            $audit->event = 'updated';
            $audit->auditable_type = Nota::class;
            $audit->auditable_id = $nota->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->new_values = json_encode($nota);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'editNota';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $nota));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteNota(Request $request, $id)
    {
        try {
            $nota = Nota::findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $valorAntiguo = $nota;

            $nota->delete();

            // START Bloque de código que genera un registro de auditoría manualmente
            $audit = new Audits();
            $audit->user_id = Auth::id();
            $audit->event = 'deleted';
            $audit->auditable_type = Nota::class;
            $audit->auditable_id = $nota->id;
            $audit->user_type = User::class;
            $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            $audit->old_values = json_encode($valorAntiguo);
            $audit->new_values = json_encode([]);
            $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            $audit->accion = 'deleteNota';
            $audit->save();
            // END Auditoria

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $nota));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}