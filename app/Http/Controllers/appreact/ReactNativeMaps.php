<?php

namespace App\Http\Controllers\appreact;

use App\Http\Controllers\Controller;
use App\Http\Resources\crm\Funciones;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\Archivo;
use App\Models\crm\Audits;
use App\Models\crm\Galeria;
use App\Models\crm\RequerimientoCaso;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReactNativeMaps extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', ['except' => [
            'getCoordenadasAlm',
        ]]);
    }
    public function getCoordenadasAlm($almId)
    {
        try {
            $data = DB::select("SELECT * FROM crm.coordenadas_agencias where alm_id = ? order by 1 asc",[$almId]);
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito.', $data));
        } catch (\Throwable $th) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error al listar', $th));
        }
    }



}
