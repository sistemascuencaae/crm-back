<?php

namespace App\Http\Controllers\crm\credito;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Caso;
use Exception;
use Illuminate\Http\Request;

class ResumenController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listResumenCasosClienteByIdentificacion(Request $request)
    {
        try {
            $data = Caso::where('identificacion', $request->identificacion)
                ->select('id', 'fecha_inicio') // JGSJ devolver columnas especificas
                ->orderBy('id', 'desc')
                ->get();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e));
        }
    }
    
}
