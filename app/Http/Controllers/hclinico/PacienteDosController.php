<?php

namespace App\Http\Controllers\hclinico;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\Paciente;
use Illuminate\Http\Request;

class PacienteDosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'byIdentificacion',
        ]]);
    }

    public function byIdentificacion($identificacion){
        $results = Paciente::where('pac_identificacion',$identificacion)->first();
        return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito.', $results));
    }
}
