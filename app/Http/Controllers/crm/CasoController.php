<?php

namespace App\Http\Controllers\crm;

use App\Events\TableroEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CasoController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function add(Request $request)
    {
        try {
            $result = Caso::create($request->all());
            $data = Caso::with('user', 'entidad', 'resumen')->where('id', $result['id'])->first();
            return response()->json(RespuestaApi::returnResultado('success', 'Caso creado con exito', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al guardar datos', $th->getMessage()));
        }
    }

    public function list()
    {
        $data = Caso::with('caso.user', 'caso.entidad')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de fases se consigion con exito', $data));
    }
    public function edit(Request $request)
    {
        try {
            $caso = Caso::find($request->input('id'));
            $caso->update($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'El caso se actualizo con exito', $caso));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al actualizar', $e->getMessage()));
        }
    }

    public function listCasoById($id)
    {
        $data = Caso::with('user', 'entidad')->where('id', $id)->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El caso se listo con éxito', $data));
    }


    public function bloqueoCaso(Request $request)
    {
        $data = [];
        $casoId = $request->input("casoId");
        $tabId = $request->input("tableroId");
        $userId = $request->input("userId");
        $bloqueado = $request->input("bloqueado");
        $bloqueado_user = $request->input("bloqueado_user");
        $caso = Caso::find($casoId);
        if ($caso) {
            $caso->bloqueado = $bloqueado;
            $caso->bloqueado_user = $bloqueado_user;
            $caso->save();
            $data = DB::select('SELECT ca.*, ta.id as tablero_id FROM public.users us
            inner join crm.caso ca on ca.user_id = us.id
            INNER JOIN crm.fase fa on fa.id = ca.fas_id
            INNER JOIN crm.tablero ta on ta.id = fa.tab_id
            where ca.id = '.$casoId);
        }
        broadcast(new TableroEvent($data));
        return response()->json([
            "res" => 200,
            "data" => $data
        ]);
    }
}










