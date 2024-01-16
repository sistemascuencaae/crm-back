<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
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
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {

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

                // $data = DB::select('select * from crm.nota where caso_id =' . $request->caso_id);
                $data = Nota::where('caso_id', $request->caso_id)->orderBy('id', 'desc')->get();

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            $log->logInfo(NotaController::class, 'Se guardo con exito la nota');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al guardar la nota', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listNotaByCasoId($caso_id)
    {
        $log = new Funciones();
        try {
            $notas = Nota::orderBy("id", "desc")->where('caso_id', $caso_id)->get();

            // ASI SE FORMATEA DIRECTAMENTE LAS FECHAS CUANDO ES UN ARRAY
            // // Formatear las fechas
            // $notas->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $notas->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $log->logInfo(NotaController::class, 'Se listo con exito las notas del caso #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $notas));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al listar las notas del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateNota(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {
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

                // // ASI SE FORMATEA LAS FECHAS CUANDO ES UN SOLO OBJETO
                // // Formatear las fechas
                // $nota->formatted_updated_at = Carbon::parse($nota->updated_at)->format('Y-m-d H:i:s');
                // $nota->formatted_created_at = Carbon::parse($nota->created_at)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($nota, $dateFields);

                return $nota;
            });

            $log->logInfo(NotaController::class, 'Se actualizo con exito la nota con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al actualizar la nota con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

    public function deleteNota(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {

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

                return $nota;
            });

            $log->logInfo(NotaController::class, 'Se elimino con exito la nota con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se elimino con éxito', $data));
        } catch (Exception $e) {
            $log->logError(NotaController::class, 'Error al eliminar la nota con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}