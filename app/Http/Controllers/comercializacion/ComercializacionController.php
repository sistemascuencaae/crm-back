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
        //$empleados = $request->input('empleados');
        $agencia = $request->input('agencia');
        $periodos = $request->input('periodos');
        $mes = $request->input('mes');
        $data = DB::table('crm.av_ventas_agencia as va')
            ->select(
                'va.cfa_id',
                'va.cfa_periodo',
                'va.mes',
                'va.almacen',
                'va.ent_emp_id',
                'va.emp_fae',
                DB::raw("CONCAT(ent.ent_nombres, ' ', ent.ent_apellidos) as empleado"),
                DB::raw("CASE WHEN va.politica <> 'CREDITO' THEN 'CONTADO' ELSE 'CREDITO' END as politica"),
                'va.fecha_comprobante',
                'va.comprobante',
                'va.factura_afectada',
                'va.venta_total'
            )
            ->join('entidad as ent', 'ent.ent_id', '=', 'va.ent_emp_id')
            ->where('va.almacen', $agencia)
            ->whereIn('va.cfa_periodo', $periodos)
            ->where('va.mes', $mes)
            ->where(function ($query) use ($periodos) {
                // $query->where(function ($query) use ($periodos) {
                //     $query->where('va.cfa_periodo', $periodos[0])
                //         ->where('va.comprobante', 'like', '%FAE%');
                // })
                $query->where(function ($query) use ($periodos) {
                    $query->where('va.cfa_periodo', $periodos[1])
                        ->where(function ($query) {
                            $query->where('va.comprobante', 'like', '%FAE%')
                                ->orWhere('va.comprobante', 'like', '%NCE%');
                        });
                });
            })
            ->get();

        return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
    }


    public function ventasAlmacen0(Request $request)
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
            ->whereIn('emp_abreviacion', $empleados)
            ->where('alm_nombre', $agencia)
            ->orWhere('alm_nombre', 'LUIS CORDERO 1')
            ->get();

        // Devuelve los resultados
        return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
    }
}
