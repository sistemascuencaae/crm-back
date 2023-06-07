<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Flujo;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FlujoController extends Controller
{


    public function __construct()
    {
        $this->middleware('auth:api');
    }


    public function create(Request $request)
    {

        

        try {
            $data = Flujo::create($request->all());
            return response()->json(RespuestaApi::returnResultado('success', 'Flujo creado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e));
        }
    }
    public function update(request $request)
    {
        DB::transaction(function () use ($request) {
            $flujo_id = $request->input('flujo_id');
            $posision = $request->input('posision');
        });
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de flujos se consigion con exito', $data));
    }
    public function list()
    {
        $data = Flujo::with('tarea')->get();
        return response()->json(RespuestaApi::returnResultado('success', 'El listado de flujos se consigion con exito', $data));
    }
}
