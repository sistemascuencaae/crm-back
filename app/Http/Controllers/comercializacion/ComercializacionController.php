<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComercializacionController extends Controller
{
    public function storeVentas()
    {
        try {

            $almacenes = DB::select("SELECT * from public.almacen where alm_activo = true order by alm_nombre");
            $empleados = DB::select(
                "SELECT emp.emp_id, emp.emp_abreviacion as codigo, (ent.ent_apellidos || ' ' || ent.ent_nombres) as nombre FROM public.empleado emp
            inner join public.entidad ent on ent.ent_id = emp.ent_id
            where emp.emp_activo = true"
            );

            $data = (object)[
                "almacenes" => $almacenes,
                "empleados" => $empleados
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }

    public function ventasAlmacen(Request $request)
    {




        // Recupera el array enviado desde Angular
        $empleados = $request->input('empleados');
        $agencia = $request->input('agencia');
        $periodo = $request->input('periodo');
        $mes = $request->input('mes');

        // Realiza la consulta SQL utilizando el array
        $data = DB::table('crm.av_facturas_notascredito')
            ->where('mes', $mes)
            ->where('cfa_periodo', $periodo)
            ->where('alm_nombre',$agencia)
            ->whereIn('emp_abreviacion', $empleados)
            ->get();

        // Devuelve los resultados
        return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
    }
}
