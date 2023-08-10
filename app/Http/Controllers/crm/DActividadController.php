<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\DTipoActividad;
use App\Models\crm\Miembros;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DActividadController extends Controller
{
    public function listActividadesByIdCasoId($caso_id)
    {
        try {
            $actividades = DTipoActividad::where('caso_id', $caso_id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

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

    //         $data = DTipoActividad::with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->where('caso_id', $dta->caso_id)->orderBy('id', 'DESC')->get();

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

    //         $data = DTipoActividad::where('id', $id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->first();
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

                $data = DTipoActividad::with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')
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

            $data = DB::transaction(function () use ($usuarioMiembro, $actividad, $request) {

                $actividad->update($request->all());

                $data = DTipoActividad::where('id', $actividad->id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->first();

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
            $actividades = DTipoActividad::where('user_id', $user_id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();

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
    //             ->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->where('caso_id', $dta->caso_id)->orderBy('id', 'DESC')->get();

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
    //             ->where('id', $id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->first();
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

                $data = DTipoActividad::where('user_id', $user_id)
                    ->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->orderBy('id', 'DESC')->get();
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

            $data = DB::transaction(function () use ($usuarioMiembro, $actividad, $request, $user_id) {

                $actividad->update($request->all());

                $data = DTipoActividad::where('user_id', $user_id)
                    ->where('id', $actividad->id)->with('cTipoActividad', 'cTipoResultadoCierre', 'usuario.departamento')->first();
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
}