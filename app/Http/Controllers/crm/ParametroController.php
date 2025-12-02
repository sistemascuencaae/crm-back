<?php

namespace App\Http\Controllers\crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\crm\TipoCasoFormulas;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ParametroController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function listFormulaByParametro()
    {
        try {
            $parametro = DB::table('crm.parametro')
                            ->where('abreviacion', 'TABACT')
                            ->first();

            $data = TipoCasoFormulas::where('tc_id', $parametro->valor)->first();

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function parametroFDias()
    {
        try {
            $data = DB::table('crm.parametro')
                            ->where('abreviacion', 'FDIAS')
                            ->first();

            if (!$data) {
                // Si no existe el parámetro, crear un objeto con valor por defecto
                $data = (object) [
                    'valor' => '0',
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }

    public function parametroFDiasMisCasos()
    {
        try {
            $data = DB::table('crm.parametro')
                            ->where('abreviacion', 'FDIAS2')
                            ->first();

            if (!$data) {
                // Si no existe el parámetro, crear un objeto con valor por defecto
                $data = (object) [
                    'valor' => '0',
                ];
            }

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', $e->getMessage(), $e));
        }
    }
}