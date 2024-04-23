<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\CTipoActividad;
use App\Models\crm\DTipoActividad;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CActividadController extends Controller
{
    public function addCTipoActividad(Request $request)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request) {

                $actividad = CTipoActividad::create($request->all());

                // $actividades = CTipoActividad::orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

                // // START Bloque de código que genera un registro de auditoría manualmente
                // $audit = new Audits();
                // $audit->user_id = Auth::id();
                // $audit->event = 'created';
                // $audit->auditable_type = CTipoActividad::class;
                // $audit->auditable_id = $actividad->id;
                // $audit->user_type = User::class;
                // $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                // $audit->url = $request->fullUrl();
                // // Establecer old_values y new_values
                // $audit->old_values = json_encode($actividad);
                // $audit->new_values = json_encode([]);
                // $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                // $audit->accion = 'addCTipoActividad';
                // $audit->save();
                // // END Auditoria

                $actividades = CTipoActividad::where('tab_id', $actividad->tab_id)->orderBy('id', 'DESC')->get();

                // // Formatear las fechas
                // $actividades->transform(function ($item) {
                //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
                //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                //     return $item;
                // });

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $actividades->map(function ($item) use ($dateFields) {
                    // $this->formatoFechaItem($item, $dateFields);
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $actividades;
            });

            $log->logInfo(CActividadController::class, 'Se guardo con exito la actividad');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(CActividadController::class, 'Error al guadar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoActividadByIdTablero($tab_id)
    {
        $log = new Funciones();
        try {
            $actividades = CTipoActividad::where('tab_id', $tab_id)->orderBy('estado', 'DESC')->orderBy('id', 'DESC')->get();

            // // Formatear las fechas
            // $actividades->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $actividades->map(function ($item) use ($dateFields) {
                // $this->formatoFechaItem($item, $dateFields);
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $log->logInfo(CActividadController::class, 'Se listo con exito los tipos de actividades por tab_id: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(CActividadController::class, 'Error al listar los tipos de actividades por tab_id: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function listCTipoActividadByIdTableroEstadoActivo($tab_id)
    {
        $log = new Funciones();
        try {
            $actividades = CTipoActividad::where('tab_id', $tab_id)->where('estado', true)->orderBy('id', 'DESC')->get();

            $log->logInfo(CActividadController::class, 'Se listo con exito los tipos de actividades en estado activo, por el tab_id: ' . $tab_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(CActividadController::class, 'Error al listar los tipos de actividades en estado activo, por el tab_id: ' . $tab_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addCActividad(Request $request)
    // {
    //     try {
    //         $cAct = $request->all();
    //         $data = DB::transaction(function () use ($cAct) {
    //             $cActividad = CTipoActividad::create($cAct);
    //             for ($i = 0; $i < sizeof($cAct['actividades']); $i++) {
    //                 $d = DTipoActividad::create([
    //                     "cta_id" => $cActividad['id'],
    //                     "nombre" => $cAct['actividades'][$i]['nombre'],
    //                     // "usuario" => $cAct['actividades'][$i]['usuario'],
    //                     "descripcion" => $cAct['actividades'][$i]['descripcion'],
    //                     "fecha_inicio" => $cAct['actividades'][$i]['fecha_inicio'],
    //                     "fecha_fin" => $cAct['actividades'][$i]['fecha_fin'],
    //                     "fecha_termino" => $cAct['actividades'][$i]['fecha_termino'],
    //                     "requerido" => $cAct['actividades'][$i]['requerido'],
    //                     "estado" => $cAct['actividades'][$i]['estado']
    //                 ]);
    //             }

    //             // return CTipoTarea::with('dTipoTarea')->orderBy("id", "desc")->where('id', $cTarea->id)->get();
    //             return CTipoActividad::with('dTipoActividad')->orderBy("id", "desc")->get();
    //         });

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo la Actividad con éxito', $data));

    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function editCTipoActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $data = DB::transaction(function () use ($request, $id) {

                $actividad = CTipoActividad::find($id);

                // // Obtener el old_values (valor antiguo)
                // $audit = new Audits();
                // $valorAntiguo = $actividad;
                // $audit->old_values = json_encode($valorAntiguo);

                $actividad->update($request->all());

                // // START Bloque de código que genera un registro de auditoría manualmente
                // $audit->user_id = Auth::id();
                // $audit->event = 'updated';
                // $audit->auditable_type = CTipoActividad::class;
                // $audit->auditable_id = $actividad->id;
                // $audit->user_type = User::class;
                // $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                // $audit->url = $request->fullUrl();
                // // Establecer old_values y new_values
                // $audit->new_values = json_encode($actividad);
                // $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                // $audit->accion = 'editCTipoActividad';
                // $audit->save();
                // // END Auditoria


                // $actividad->refresh();

                // // Formatear las fechas
                // $actividad->formatted_updated_at = Carbon::parse($actividad->updated_at)->format('Y-m-d H:i:s');
                // $actividad->formatted_created_at = Carbon::parse($actividad->created_at)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($actividad, $dateFields);

                return $actividad;
            });

            $log->logInfo(CActividadController::class, 'Se actualizo con exito el tipo de actividad, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));

        } catch (Exception $e) {
            $log->logError(CActividadController::class, 'Error al actualizar el tipo de actividad, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function deleteCTipoActividad(Request $request, $id)
    {
        $log = new Funciones();
        try {
            $actividad = CTipoActividad::find($id);

            // Verificar si existe una relación en la tabla dtipo_actividad // existe relacion
            $existeRelacionConCtipoActividad = DTipoActividad::where('cta_id', $id)->exists();
            if ($existeRelacionConCtipoActividad) {
                // Si existe una relación, retornar un mensaje de error
                return response()->json(RespuestaApi::returnResultado('error', 'No se puede eliminar el tipo de actividad porque ya esta asignada a una actividad', ''));
            }

            // Obtener el old_values (valor antiguo)
            // $valorAntiguo = $actividad;

            // Si no existe una relación en la tabla dtipo_actividad, eliminar la actividad
            $actividad->delete();

            // START Bloque de código que genera un registro de auditoría manualmente
            // $audit = new Audits();
            // $audit->user_id = Auth::id();
            // $audit->event = 'deleted';
            // $audit->auditable_type = CTipoActividad::class;
            // $audit->auditable_id = $actividad->id;
            // $audit->user_type = User::class;
            // $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
            // $audit->url = $request->fullUrl();
            // Establecer old_values y new_values
            // $audit->old_values = json_encode($valorAntiguo);
            // $audit->new_values = json_encode([]);
            // $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
            // $audit->accion = 'deleteCTipoActividad';
            // $audit->save();
            // END Auditoria

            $log->logInfo(CActividadController::class, 'Se eliminó con éxito el tipo de actividad con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se eliminó con éxito', $actividad));

        } catch (Exception $e) {
            $log->logError(CActividadController::class, 'Error al eliminar el tipo de actividad con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}