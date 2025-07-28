<?php

namespace App\Http\Controllers\gestiones;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\configuracion\Agencia;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GestionController extends Controller
{
    public function __construct()
    {
    }

    public function listGestionByIdentificacion($identificacion)
    {
        try {
            // $data = DB::select("SELECT *
            //                             from crm.aav_migracion_cartera_historica_xcuotas_juan c
            //                             where c.ent_identificacion = ?
            //                         ",[$identificacion]);
            
            $data = DB::select("SELECT * FROM crm.af_gestionmora(?) ORDER BY secuencia ASC",[$identificacion]);

            return response()->json(RespuestaApi::returnResultado('success', 'Se listo con éxito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('error', 'Error', $e->getMessage()));
        }
    }

}