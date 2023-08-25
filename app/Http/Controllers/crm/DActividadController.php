<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
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
    public function listActividadesByIdCasoId($caso_id)
    {
        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

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

    public function addDTipoActividad(Request $request)
    {
        try {
            $usuarioMiembro = $request->input('usuario');

            $data = DB::transaction(function () use ($usuarioMiembro, $request) {

                $dta = DTipoActividad::create($request->all());

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'created';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $dta->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode($dta);
                $audit->new_values = json_encode([]);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'addDTipoActividad';
                $audit->save();
                // END Auditoria

                $data = DTipoActividad::with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')
                    ->where('caso_id', $dta->caso_id)
                    ->orderBy('id', 'DESC')
                    ->get();

                $miembro = new Miembros();
                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $dta->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateDActividad(Request $request, $id)
    {
        try {
            $usuarioMiembro = $request->input('usuario');

            $actividad = DTipoActividad::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $actividad;
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
                // Establecer old_values y new_values
                $audit->new_values = json_encode($actividad);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                // $audit->accion = 'editDActividad';
                $audit->accion = 'editDTipoActividad';
                $audit->save();
                // END Auditoria

                $data = DTipoActividad::where('id', $actividad->id)->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->first();

                $miembro = new Miembros();

                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $actividad->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                return $data;
            });

            return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // ESTOS ENDPOPINTS SON PARA CUANDO ME VOY A LA TABLA DE MIS ACTIVIDADES

    public function listActividadesByUserId($user_id)
    {
        try {
            $actividades = DTipoActividad::where('user_id', $user_id)->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
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
        try {
            $usuarioMiembro = $request->input('usuario');

            $data = DB::transaction(function () use ($usuarioMiembro, $request, $user_id) {
                $dta = DTipoActividad::create($request->all());

                // START Bloque de código que genera un registro de auditoría manualmente
                $audit = new Audits();
                $audit->user_id = Auth::id();
                $audit->event = 'created';
                $audit->auditable_type = DTipoActividad::class;
                $audit->auditable_id = $dta->id;
                $audit->user_type = User::class;
                $audit->ip_address = $request->ip(); // Obtener la dirección IP del cliente
                $audit->url = $request->fullUrl();
                // Establecer old_values y new_values
                $audit->old_values = json_encode($dta);
                $audit->new_values = json_encode([]);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'addDTipoActividadTableroMisActividades';
                $audit->save();
                // END Auditoria

                $data = DTipoActividad::where('user_id', $user_id)
                    ->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();
                // ->where('caso_id', $dta->caso_id)

                $miembro = new Miembros();
                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $dta->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                return $data;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se guardo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    public function updateDActividadTabla(Request $request, $id, $user_id)
    {
        try {
            $usuarioMiembro = $request->input('usuario');
            $actividad = DTipoActividad::findOrFail($id);

            // Obtener el old_values (valor antiguo)
            $audit = new Audits();
            $valorAntiguo = $actividad;
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
                // Establecer old_values y new_values
                $audit->new_values = json_encode($actividad);
                $audit->user_agent = $request->header('User-Agent'); // Obtener el valor del User-Agent
                $audit->accion = 'editDTipoActividadTableroMisActividades';
                $audit->save();
                // END Auditoria

                $data = DTipoActividad::where('user_id', $user_id)
                    ->where('id', $actividad->id)->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->first();
                $miembro = new Miembros();

                $miembro->user_id = $usuarioMiembro['id'];
                $miembro->caso_id = $actividad->caso_id;

                $miemb = Miembros::where('user_id', $miembro->user_id)->first();
                if (!$miemb) {
                    $miembro->save();
                }

                return $data;
            });
            return response()->json(RespuestaApi::returnResultado('success', 'Se cerro la actividad con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

    // LISTA PARA EL CALENDARIO
    public function listActividadesIniciadasByUserId($user_id)
    {
        try {
            $actividades = DTipoActividad::where('user_id', $user_id)->where('estado_actividad', 'Iniciado')->with('cTipoActividad.tablero', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $actividades));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }

}