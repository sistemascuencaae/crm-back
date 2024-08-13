<?php

namespace App\Http\Controllers\comercializacion;

use App\Http\Controllers\Controller;
use App\Http\Resources\RespuestaApi;
use App\Models\comercializacion\VentasxAgencia;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ComercializacionController extends Controller
{
    public function storeVentas()
    {
        try {

            $almacenes = DB::select("SELECT alm_codigo||'-'||alm_nombre as alm_nombre from public.almacen where alm_activo = true order by alm_nombre");
            // $empleados = DB::select(
            //     "SELECT emp.emp_id, emp.emp_abreviacion as codigo, (ent.ent_apellidos || ' ' || ent.ent_nombres) as nombre FROM public.empleado emp
            // inner join public.entidad ent on ent.ent_id = emp.ent_id
            // where emp.emp_activo = true"
            // );

            $data = (object)[
                "almacenes" => $almacenes,
                //"empleados" => $empleados
            ];

            return response()->json(RespuestaApi::returnResultado('success', 'Listado con exito', $data));
        } catch (Exception $e) {
            return response()->json(RespuestaApi::returnResultado('exception', 'Error del servidor', $e->getmessage()));
        }
    }



    public function ventasAlmacen1(Request $request)
    {

        // Recupera el array enviado desde Angular
        //$empleados = $request->input('empleados');
        $agencia = $request->input('agencia');
        $periodo = $request->input('periodo');
        $mes = $request->input('mes');
        $data = DB::table('av_reporte_ventasxagencia as va')
            ->select(
                // 'va.cfa_id',
                // 'va.cfa_periodo',
                // 'va.mes',
                // 'va.almacen',
                // 'va.ent_emp_id',
                // 'va.emp_fae',
                // DB::raw("CONCAT(ent.ent_nombres, ' ', ent.ent_apellidos) as empleado"),
                // DB::raw("CASE WHEN va.politica <> 'CREDITO' THEN 'CONTADO' ELSE 'CREDITO' END as politica"),
                // 'va.fecha_comprobante',
                // 'va.comprobante',
                // 'va.factura_afectada',
                //'111 as cfa_id',
                DB::raw("0 as cfa_id"),
                DB::raw("va.periodo as cfa_periodo"),
                DB::raw("extract (month from va.fecha) as mes"),
                'va.almacen',
                'va.id_agente_factura as ent_emp_id',
                'va.emp_abreviacion as emp_fae',
                'va.agente_factura as empleado',
                DB::raw("CASE WHEN va.interes > 0 THEN 'CREDITO' ELSE 'CONTADO' END as politica"),
                'va.fecha',
                'va.comprobante',
                'va.factura_afectada',
                DB::raw("va.subtotal_descuentos_interes as venta_total")
            )
            ->where('va.periodo', '=', $periodo)
            ->whereMonth('va.fecha', $mes)
            ->where('va.almacen', $agencia)
            ->where('va.pve_numero', 501)
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

    public function ventasAlmacen(Request $request)
    {
        $periodo = $request->input('periodo');
        $almCodigo = $request->input('almCodigo');
        $mes = $request->input('mes');

        $data = VentasxAgencia::where('periodo', $periodo)
        ->where('alm_codigo', $almCodigo)
        ->where('pve_numero', 501)
        ->whereMonth('fecha', $mes) // Filtra por el mes de la fecha
        ->orderBy('total', 'desc')
        ->get();

        // $data =DB::select("select * from public.av_reporte_ventasxagencia v where v.periodo = 2024
        //                     and extract (month from v.fecha) = 8
        //                     and v.alm_codigo = 3
        //                     and v.pve_numero=501
        //                     order by total desc;");

        return response()->json(RespuestaApi::returnResultado('success', 'Listado con éxito', $data));
    }
}
