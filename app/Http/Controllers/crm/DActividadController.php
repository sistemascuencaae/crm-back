<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Audits;
use App\Models\crm\DTipoActividad;
use App\Models\crm\Miembros;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DActividadController extends Controller
{
    // public function listActividadesByIdCasoId($caso_id)
    // {
    //     try {
    //         $actividades = DTipoActividad::where('caso_id', $caso_id)
    //             ->with(
    //                 'cTipoActividad.tablero',
    //                 'estado_actividad',
    //                 'cTipoResultadoCierre',
    //                 'usuario.departamento'
    //             )
    //             ->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function listActividadesByIdCasoId($caso_id)
    // {
    //     try {
    //         $actividades = DTipoActividad::where('caso_id', $caso_id)
    //             ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
    //             ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")
    //             ->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function addDTipoActividad(Request $request)
    // {
    //     try {
    //         $dta = DTipoActividad::create($request->all());

    //         // $actividades = DTipoActividad::orderBy('id', 'DESC')->get();

    //         $data = DTipoActividad::with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->where('caso_id', $dta->caso_id)->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function updateDActividad(Request $request, $id)
    // {
    //     try {
    //         $actividad = DTipoActividad::findOrFail($id);

    //         $actividad->update($request->all());

    //         $data = DTipoActividad::where('id', $id)->with('cTipoActividad.tablero.tablero.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->first();
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    // trae por las activiades por user_id y las activiades publicas de otro usuario
    // public function listActividadesByIdCasoId($caso_id, $user_id)
    // {
    //     try {
    //         $actividades = DTipoActividad::where('caso_id', $caso_id)
    //             ->where(function ($query) use ($user_id) {
    //                 $query->where('user_id', $user_id)
    //                     ->orWhere('acc_publico', true);
    //             })
    //             ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
    //             // ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")

    //             ->selectRaw("*, 
    //             CASE 
    //                 WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
    //                 ELSE descripcion 
    //             END AS descripcion_pos_descripcion")



    //             ->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }


    // LISTA PARA USUARIO COMUN

    public function listActividadesByDepIdCasoId($caso_id, $dep_id)
    {
        $log = new Funciones();

        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)
                ->where(function ($query) use ($dep_id) {
                    $query->whereHas('usuario', function ($subquery) use ($dep_id) {
                        $subquery->where('dep_id', $dep_id);
                    })
                        ->orWhere('acc_publico', true);
                })
                ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
                ->selectRaw("*, 
            CASE 
                WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                ELSE descripcion 
            END AS descripcion_pos_descripcion")
                ->orderBy('id', 'DESC')->get();

            // // Formatear las fechas
            // $actividades->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_inicio = Carbon::parse($item->fecha_inicio)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_fin = Carbon::parse($item->fecha_fin)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_conclusion = Carbon::parse($item->fecha_conclusion)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $actividades->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $log->logInfo(DActividadController::class, 'Se listo con exito las actividades del departamento con el ID: ' . $dep_id . ' del caso #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al listar las actividades del departamento con el ID: ' . $dep_id . ' del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // LISTA PARA SUPER USUARIO
    public function listAllActividadesByCasoId($caso_id)
    {
        $log = new Funciones();

        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)
                ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
                ->selectRaw("*, 
            CASE 
                WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                ELSE descripcion 
            END AS descripcion_pos_descripcion")
                ->orderBy('id', 'DESC')->get();

            // // Formatear las fechas
            // $actividades->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_inicio = Carbon::parse($item->fecha_inicio)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_fin = Carbon::parse($item->fecha_fin)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_conclusion = Carbon::parse($item->fecha_conclusion)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $actividades->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $log->logInfo(DActividadController::class, 'Se listo con exito todas las actividades del caso #' . $caso_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listó con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al listar todas las actividades del caso #' . $caso_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }


    public function addDTipoActividad(Request $request)
    {
        $log = new Funciones();

        try {
            $usuarioMiembro = $request->input('usuario');

            $data = DB::transaction(function () use ($usuarioMiembro, $request) {

                $dta = DTipoActividad::create($request->all());

                $AuditActividad = DTipoActividad::with('cTipoActividad.tablero', 'estado_actividad', 'usuario.departamento', 'cTipoResultadoCierre')->findOrFail($dta->id);
                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'created';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $AuditActividad['id'];
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode($AuditActividad);
                $audit->new_values = json_encode([]);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'addDTipoActividad';
                $audit->caso_id = $AuditActividad['caso_id'];
                $audit->save();
                // END Auditoria


                // $data = DTipoActividad::with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
                //     ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")
                //     ->where('caso_id', $dta->caso_id)
                //     ->orderBy('id', 'DESC')
                //     ->get();

                // Obtener la lista actualizada de actividades después de agregar una nueva
                $data = DTipoActividad::where(function ($query) use ($request) {
                    $query->where('user_id', $request->input('user_id'))
                        ->orWhere('acc_publico', true);
                })
                    ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
                    ->where('caso_id', $dta->caso_id)
                    // ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")

                    ->selectRaw("*, 
                    CASE 
                        WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                        ELSE descripcion 
                    END AS descripcion_pos_descripcion")

                    ->orderBy('id', 'DESC')
                    ->get();

                $miembro = new Miembros();
                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $dta->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                // // Formatear las fechas
                // $data->transform(function ($item) {
                //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
                //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_inicio = Carbon::parse($item->fecha_inicio)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_fin = Carbon::parse($item->fecha_fin)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_conclusion = Carbon::parse($item->fecha_conclusion)->format('Y-m-d H:i:s');
                //     return $item;
                // });

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            $log->logInfo(DActividadController::class, 'Se guardo con exito la actividad');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al guardar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateDActividad(Request $request, $id)
    {
        $log = new Funciones();

        try {

            $usuarioMiembro = $request->input('usuario');

            $actividad = DTipoActividad::findOrFail($id);

            $AuditActividad = DTipoActividad::with('cTipoActividad.tablero', 'estado_actividad', 'usuario.departamento', 'cTipoResultadoCierre')->findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $AuditActividad;
            $audit->old_values = json_encode($valorAntiguo);

            $data = DB::transaction(function () use ($usuarioMiembro, $actividad, $request, $audit) {

                $actividad->update($request->all());

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $actividad->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();

                $data = DTipoActividad::where('id', $actividad->id)->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento')
                    // ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")

                    ->selectRaw("*, 
                CASE 
                    WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                    ELSE descripcion 
                    END AS descripcion_pos_descripcion")

                    ->first();

                // Establecer old_values y new_values
                $audit->new_values = json_encode($data);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                // $audit->accion = 'editDActividad';
                $audit->accion = 'editDTipoActividad';
                $audit->caso_id = $actividad->caso_id;
                $audit->save();
                // END Auditoria

                $miembro = new Miembros();

                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $actividad->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                // Formatear las fechas
                // $data->formatted_updated_at = Carbon::parse($data->updated_at)->format('Y-m-d H:i:s');
                // $data->formatted_created_at = Carbon::parse($data->created_at)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_inicio = Carbon::parse($data->fecha_inicio)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_fin = Carbon::parse($data->fecha_fin)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_conclusion = Carbon::parse($data->fecha_conclusion)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($data, $dateFields);

                return $data;
            });

            $log->logInfo(DActividadController::class, 'Se actualizo con exito la actividad, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al actualizar la actividad, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ESTOS ENDPOPINTS SON PARA CUANDO ME VOY A LA TABLA DE MIS ACTIVIDADES

    public function listActividadesByUserId($user_id)
    {
        $log = new Funciones();

        try {
            $actividades = DTipoActividad::where('user_id', $user_id)->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento', 'caso:id,cliente_id') // Aquí especificamos que solo queremos el campo 'cliente_id' de la tabla 'caso')
                ->selectRaw("*, 
                CASE 
                    WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                    ELSE descripcion 
                END AS descripcion_pos_descripcion")
                ->orderBy('id', 'DESC')->get();

            // // Formatear las fechas
            // $actividades->transform(function ($item) {
            //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
            //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_inicio = Carbon::parse($item->fecha_inicio)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_fin = Carbon::parse($item->fecha_fin)->format('Y-m-d H:i:s');
            //     $item->formatted_fecha_conclusion = Carbon::parse($item->fecha_conclusion)->format('Y-m-d H:i:s');
            //     return $item;
            // });

            // Especificar las propiedades que representan fechas en tu objeto Nota
            $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
            // Utilizar la función map para transformar y obtener una nueva colección
            $actividades->map(function ($item) use ($dateFields) {
                $funciones = new Funciones();
                $funciones->formatoFechaItem($item, $dateFields);
                return $item;
            });

            $log->logInfo(DActividadController::class, 'Se listo con exito las actividades del usuario con el ID: ' . $user_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al listar las actividades del usuario con el ID: ' . $user_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // public function addDTipoActividadTabla(Request $request, $user_id)
    // {
    //     try {
    //         $dta = DTipoActividad::create($request->all());

    //         $data = DTipoActividad::where('user_id', $user_id)
    //             ->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->where('caso_id', $dta->caso_id)->orderBy('id', 'DESC')->get();

    //         return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    // public function updateDActividadTabla(Request $request, $id, $user_id)
    // {
    //     try {
    //         $actividad = DTipoActividad::findOrFail($id);

    //         $actividad->update($request->all());

    //         $data = DTipoActividad::where('user_id', $user_id)
    //             ->where('id', $id)->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->first();
    //         return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
    //     } catch (Exception $e) {
    //         return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
    //     }
    // }

    public function addDTipoActividadTabla(Request $request, $user_id)
    {
        $log = new Funciones();

        try {
            $usuarioMiembro = $request->input('usuario');

            $data = DB::transaction(function () use ($usuarioMiembro, $request, $user_id) {
                $dta = DTipoActividad::create($request->all());

                $AuditActividad = DTipoActividad::with('cTipoActividad.tablero', 'estado_actividad', 'usuario.departamento', 'cTipoResultadoCierre')->findOrFail($dta->id);
                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'created';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $AuditActividad['id'];
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode($AuditActividad);
                $audit->new_values = json_encode([]);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'addDTipoActividad';
                $audit->caso_id = $AuditActividad['caso_id'];
                $audit->save();
                // END Auditoria

                $data = DTipoActividad::where('user_id', $user_id)
                    ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento', 'caso:id,cliente_id')
                    ->selectRaw("*, 
                CASE 
                    WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                    ELSE descripcion 
                END AS descripcion_pos_descripcion")
                    ->orderBy('id', 'DESC')->get();
                // ->where('caso_id', $dta->caso_id)

                $miembro = new Miembros();
                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $dta->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                // // Formatear las fechas
                // $data->transform(function ($item) {
                //     $item->formatted_updated_at = Carbon::parse($item->updated_at)->format('Y-m-d H:i:s');
                //     $item->formatted_created_at = Carbon::parse($item->created_at)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_inicio = Carbon::parse($item->fecha_inicio)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_fin = Carbon::parse($item->fecha_fin)->format('Y-m-d H:i:s');
                //     $item->formatted_fecha_conclusion = Carbon::parse($item->fecha_conclusion)->format('Y-m-d H:i:s');
                //     return $item;
                // });

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
                // Utilizar la función map para transformar y obtener una nueva colección
                $data->map(function ($item) use ($dateFields) {
                    $funciones = new Funciones();
                    $funciones->formatoFechaItem($item, $dateFields);
                    return $item;
                });

                return $data;
            });

            $log->logInfo(DActividadController::class, 'Se guardo con exito la actividad');

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al guardar la actividad', $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateDActividadTabla(Request $request, $id, $user_id)
    {
        $log = new Funciones();

        try {
            $usuarioMiembro = $request->input('usuario');
            $actividad = DTipoActividad::findOrFail($id);

            $AuditActividad = DTipoActividad::with('cTipoActividad.tablero', 'estado_actividad', 'usuario.departamento', 'cTipoResultadoCierre')->findOrFail($id);
            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $AuditActividad;
            $audit->old_values = json_encode($valorAntiguo);

            $data = DB::transaction(function () use ($usuarioMiembro, $actividad, $request, $user_id, $audit) {

                $actividad->update($request->all());

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit->user_id = Auth::id();
                $audit->event = 'updated';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $actividad->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();

                $data = DTipoActividad::where('user_id', $user_id)
                    ->where('id', $actividad->id)->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento', 'caso:id,cliente_id')
                    ->selectRaw("*, 
                CASE 
                    WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                    ELSE descripcion 
                END AS descripcion_pos_descripcion")
                    ->first();

                // Establecer old_values y new_values
                $audit->new_values = json_encode($data);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                // $audit->accion = 'editDActividad';
                $audit->accion = 'editDTipoActividad';
                $audit->caso_id = $actividad->caso_id;
                $audit->save();
                // END Auditoria

                $miembro = new Miembros();
                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $actividad->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                // // Formatear las fechas
                // $data->formatted_updated_at = Carbon::parse($data->updated_at)->format('Y-m-d H:i:s');
                // $data->formatted_created_at = Carbon::parse($data->created_at)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_inicio = Carbon::parse($data->fecha_inicio)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_fin = Carbon::parse($data->fecha_fin)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_conclusion = Carbon::parse($data->fecha_conclusion)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($data, $dateFields);

                return $data;
            });

            $log->logInfo(DActividadController::class, 'Se cerro con exito la actividad, con el ID: ' . $id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al cerrar la actividad, con el ID: ' . $id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // LISTA PARA EL CALENDARIO
    public function listActividadesIniciadasByUserId($user_id)
    {
        $log = new Funciones();

        try {
            $actividades = DTipoActividad::where('user_id', $user_id)
                ->whereHas('estado_actividad', function ($query) {
                    $query->where('nombre', 'Iniciado');
                })
                ->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento', 'caso:id,cliente_id')
                ->orderBy('id', 'DESC')
                ->get();

            $log->logInfo(DActividadController::class, 'Se listo con exito las actividades INICIADAS del usuario con el ID: ' . $user_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al listar las actividades INICIADAS del usuario con el ID: ' . $user_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }



    // Editar el acceso publico o privado de una actividad
    public function editAccesoActividad(Request $request, $actividad_id)
    {
        $log = new Funciones();

        try {
            $actividad = $request->all();

            $data = DB::transaction(function () use ($actividad, $actividad_id, $request) {

                $actividad = DTipoActividad::findOrFail($actividad_id);

                $actividad->update([
                    "acc_publico" => $request->acc_publico,
                ]);

                $data = DTipoActividad::where('id', $actividad->id)->with('cTipoActividad.tablero', 'estado_actividad', 'cTipoResultadoCierre', 'usuario.departamento', 'caso:id,cliente_id')
                    // ->selectRaw("*, descripcion || ' | ' || COALESCE(pos_descripcion, '') AS descripcion_pos_descripcion")

                    ->selectRaw("*, 
                CASE 
                    WHEN pos_descripcion IS NOT NULL THEN descripcion || ' | ' || pos_descripcion 
                    ELSE descripcion 
                END AS descripcion_pos_descripcion")

                    ->first();

                // // Formatear las fechas
                // $data->formatted_updated_at = Carbon::parse($data->updated_at)->format('Y-m-d H:i:s');
                // $data->formatted_created_at = Carbon::parse($data->created_at)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_inicio = Carbon::parse($data->fecha_inicio)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_fin = Carbon::parse($data->fecha_fin)->format('Y-m-d H:i:s');
                // $data->formatted_fecha_conclusion = Carbon::parse($data->fecha_conclusion)->format('Y-m-d H:i:s');

                // Especificar las propiedades que representan fechas en tu objeto Nota
                $dateFields = ['created_at', 'updated_at', 'fecha_inicio', 'fecha_fin', 'fecha_conclusion'];
                $funciones = new Funciones();
                $funciones->formatoFechaItem($data, $dateFields);

                return $data;
            });

            $log->logInfo(DActividadController::class, 'Se actualizo con exito el acceso de la actividad, con el ID: ' . $actividad_id);

            return response()->json(RespuestaApi::returnResultado('success', 'Se actualizo con éxito', $data));
        } catch (Exception $e) {
            $log->logError(DActividadController::class, 'Error al actualizar el acceso de la actividad, con el ID: ' . $actividad_id, $e);

            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}