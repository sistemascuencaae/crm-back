<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RenegociacionController extends Controller
{
    public function listDoctranOpenceo($ddo_doctran)
    {
        try {

            $data = DB::select("SELECT * from public.ddocumento doc where ddo_doctran = '$ddo_doctran' order by  doc.ddo_num_pago asc");

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}
