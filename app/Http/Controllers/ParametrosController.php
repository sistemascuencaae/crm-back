<?php

namespace App\Http\Controllers;

use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\TryCatch;

class ParametrosController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:api');
    }

    public function direccion()
    {
        try {
            $paises = DB::select("SELECT * FROM crm.crm_pais");
            $provincias = DB::select("SELECT * FROM crm.crm_provincia");
            $cantones = DB::select("SELECT * FROM crm.crm_canton");
            $parroquias = DB::select("SELECT * FROM crm.crm_parroquia");
            $data = (object) [
                "paises" => $paises,
                "provincias" => $provincias,
                "cantones" => $cantones,
                "parroquias" => $parroquias
            ];
           return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (\Throwable $th) {
            return $this->getErrCustom($th->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }

    public function direccionParroquias($cantonId)
    {
        try {
            $parroquias = DB::select("SELECT * FROM crm.crm_parroquia where canton_id = '$cantonId'");
            $data = (object) [
                "parroquias" => $parroquias,
            ];
            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con exito', $data));
        } catch (\Throwable $th) {
            return $this->getErrCustom($th->getMessage(), 'Error: la información no se logro conseguir: ');
        }
    }


}
